<?php

declare(strict_types=1);

namespace App\Services\Precios;

use App\Domain\Exceptions\OperacionNoPermitidaException;
use App\Enums\EstadoCambioPrecio;
use App\Filament\Resources\CambiosPrecio\CambioPrecioResource;
use App\Models\CambioPrecio;
use App\Models\Producto;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils as ShieldUtils;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Mantiene al día los precios de los productos con margen, con la regla
 * del dueño:
 *
 *  - Si el costo promedio sube, el precio sube solo (queda en el historial).
 *  - Si baja, el precio NO baja: queda un cambio "por revisar" y se avisa
 *    a quien tiene el permiso Aprobar:CambioPrecio. Él decide.
 *
 * Los precios con margen se calculan sobre `costo_precio` (el costo con
 * que se fijaron los precios vigentes) y este servicio es el único que lo
 * mueve. Los productos con precio a mano no se tocan.
 */
final class PrecioService
{
    /** Diferencias de costo menores se ignoran (los costos van a 4 decimales). */
    private const EPSILON = 0.00005;

    private const PERMISO_APROBAR = 'Aprobar:CambioPrecio';

    /**
     * Revisa los productos cuyo costo pudo cambiar. Se llama dentro de la
     * misma transacción que movió el costo (compra, ajuste, anulación),
     * así que si esa operación falla, tampoco queda ningún cambio de precio.
     *
     * @param array<int, int> $productoIds
     */
    public function revisar(array $productoIds, string $origen): void
    {
        $ids = array_values(array_unique($productoIds));
        sort($ids);

        $avisos = 0;

        foreach ($ids as $id) {
            $producto = Producto::withTrashed()
                ->with(['unidadMedida', 'presentaciones.unidadMedida'])
                ->lockForUpdate()
                ->find($id);

            if ($producto instanceof Producto && $producto->usaMargen() && $this->revisarProducto($producto, $origen)) {
                $avisos++;
            }
        }

        if ($avisos > 0) {
            $this->avisar($avisos, $origen);
        }
    }

    /**
     * El dueño acepta bajar el precio al costo de la revisión.
     */
    public function aprobar(CambioPrecio $cambio, User $usuario): CambioPrecio
    {
        return DB::transaction(function () use ($cambio, $usuario): CambioPrecio {
            $cambio = $this->bloquearPendiente($cambio, 'aprobar');

            $producto = Producto::withTrashed()
                ->with(['unidadMedida', 'presentaciones.unidadMedida'])
                ->lockForUpdate()
                ->findOrFail($cambio->producto_id);

            $antes = $this->preciosCon($producto, (float) $producto->costo_precio);
            $producto->forceFill(['costo_precio' => $cambio->costo_nuevo])->save();
            $despues = $this->preciosCon($producto, (float) $cambio->costo_nuevo);

            $cambio->forceFill([
                ...$this->foto($antes, $despues),
                'estado'       => EstadoCambioPrecio::Aplicado,
                'revisado_por' => $usuario->id,
                'revisado_at'  => now(),
            ])->save();

            return $cambio;
        });
    }

    /**
     * El dueño decide mantener el precio. No se vuelve a preguntar por
     * ese mismo costo; solo si baja todavía más.
     */
    public function rechazar(CambioPrecio $cambio, User $usuario): CambioPrecio
    {
        return DB::transaction(function () use ($cambio, $usuario): CambioPrecio {
            $cambio = $this->bloquearPendiente($cambio, 'rechazar');

            $cambio->forceFill([
                'estado'       => EstadoCambioPrecio::Rechazado,
                'revisado_por' => $usuario->id,
                'revisado_at'  => now(),
            ])->save();

            return $cambio;
        });
    }

    /**
     * @return bool true si quedó un cambio NUEVO por revisar (hay que avisar)
     */
    private function revisarProducto(Producto $producto, string $origen): bool
    {
        $costoActual = round((float) $producto->costo_promedio, 4);
        $costoPrecio = round((float) $producto->costo_precio, 4);

        // El costo volvió a donde estaban calculados los precios (p. ej. se
        // anuló la compra que lo bajó): lo que esperaba revisión ya no aplica.
        if (abs($costoActual - $costoPrecio) < self::EPSILON) {
            $this->descartarPendiente($producto);

            return false;
        }

        // Primer costo del producto (existencia inicial o primera compra):
        // los precios se fijan sin preguntar.
        if ($costoPrecio <= 0) {
            $producto->forceFill(['costo_precio' => $costoActual])->save();

            return false;
        }

        $vigentes = $this->preciosCon($producto, $costoPrecio);
        $nuevos = $this->preciosCon($producto, $costoActual);
        $cambiaAlgo = $this->cambiaAlgo($vigentes, $nuevos);

        // Subió el costo, o bajó tan poco que con el redondeo nada cambia:
        // se aplica sin preguntar y lo que esperaba revisión queda sin efecto.
        if ($costoActual > $costoPrecio || ! $cambiaAlgo) {
            $producto->forceFill(['costo_precio' => $costoActual])->save();
            $this->descartarPendiente($producto);

            if ($cambiaAlgo) {
                CambioPrecio::query()->create([
                    ...$this->foto($vigentes, $nuevos),
                    'producto_id'    => $producto->id,
                    'estado'         => EstadoCambioPrecio::Automatico,
                    'costo_anterior' => $costoPrecio,
                    'costo_nuevo'    => $costoActual,
                    'origen'         => $origen,
                ]);
            }

            return false;
        }

        // El dueño ya decidió mantener el precio a este costo: no se le vuelve
        // a preguntar, y una baja mayor que esperaba revisión ya no aplica.
        if ($this->yaLoRechazo($producto, $costoPrecio, $costoActual)) {
            $this->descartarPendiente($producto);

            return false;
        }

        $pendiente = $producto->cambiosPrecio()->where('estado', EstadoCambioPrecio::Pendiente)->first();
        $esNuevo = $pendiente === null;
        $pendiente ??= new CambioPrecio(['producto_id' => $producto->id, 'estado' => EstadoCambioPrecio::Pendiente]);

        $pendiente->fill([
            ...$this->foto($vigentes, $nuevos),
            'costo_anterior' => $costoPrecio,
            'costo_nuevo'    => $costoActual,
            'origen'         => $origen,
        ])->save();

        return $esNuevo;
    }

    /**
     * Precios del producto con margen a un costo dado: público, mayoreo y
     * las presentaciones que no tienen precio propio.
     *
     * @return array{publico: float, mayoreo: float|null, presentaciones: array<int, array{etiqueta: string, precio: float}>}
     */
    private function preciosCon(Producto $producto, float $costo): array
    {
        $presentaciones = [];

        foreach ($producto->presentaciones as $presentacion) {
            if (! $presentacion->activo || $presentacion->precio_venta !== null) {
                continue;
            }

            $presentacion->setRelation('producto', $producto);

            $presentaciones[$presentacion->id] = [
                'etiqueta' => $presentacion->etiqueta(),
                'precio'   => $producto->precioConMargen($costo, factor: (float) $presentacion->factor) ?? 0.0,
            ];
        }

        $mayoreo = $producto->precioConMargen($costo, mayoreo: true);

        return [
            'publico'        => $producto->precioConMargen($costo) ?? 0.0,
            'mayoreo'        => $mayoreo !== null && $mayoreo > 0 ? $mayoreo : null,
            'presentaciones' => $presentaciones,
        ];
    }

    /**
     * @param array{publico: float, mayoreo: float|null, presentaciones: array<int, array{etiqueta: string, precio: float}>} $antes
     * @param array{publico: float, mayoreo: float|null, presentaciones: array<int, array{etiqueta: string, precio: float}>} $despues
     */
    private function cambiaAlgo(array $antes, array $despues): bool
    {
        if ($antes['publico'] !== $despues['publico'] || $antes['mayoreo'] !== $despues['mayoreo']) {
            return true;
        }

        foreach ($despues['presentaciones'] as $id => $presentacion) {
            if (($antes['presentaciones'][$id]['precio'] ?? null) !== $presentacion['precio']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Columnas de precios antes/después para el historial.
     *
     * @param array{publico: float, mayoreo: float|null, presentaciones: array<int, array{etiqueta: string, precio: float}>} $antes
     * @param array{publico: float, mayoreo: float|null, presentaciones: array<int, array{etiqueta: string, precio: float}>} $despues
     *
     * @return array{precio_anterior: float, precio_nuevo: float, mayoreo_anterior: float|null, mayoreo_nuevo: float|null, detalle: list<array{etiqueta: string, antes: float, despues: float}>}
     */
    private function foto(array $antes, array $despues): array
    {
        $detalle = [];

        foreach ($despues['presentaciones'] as $id => $presentacion) {
            $detalle[] = [
                'etiqueta' => $presentacion['etiqueta'],
                'antes'    => $antes['presentaciones'][$id]['precio'] ?? $presentacion['precio'],
                'despues'  => $presentacion['precio'],
            ];
        }

        return [
            'precio_anterior'  => $antes['publico'],
            'precio_nuevo'     => $despues['publico'],
            'mayoreo_anterior' => $antes['mayoreo'],
            'mayoreo_nuevo'    => $despues['mayoreo'],
            'detalle'          => $detalle,
        ];
    }

    /**
     * El dueño ya dijo que no a bajar desde este mismo costo base, y el
     * costo no bajó más que lo que rechazó: no se le vuelve a preguntar.
     */
    private function yaLoRechazo(Producto $producto, float $costoPrecio, float $costoActual): bool
    {
        $ultimo = $producto->cambiosPrecio()
            ->whereIn('estado', [EstadoCambioPrecio::Aplicado, EstadoCambioPrecio::Rechazado])
            ->latest('id')
            ->first();

        return $ultimo instanceof CambioPrecio
            && $ultimo->estado === EstadoCambioPrecio::Rechazado
            && abs((float) $ultimo->costo_anterior - $costoPrecio) < self::EPSILON
            && $costoActual >= (float) $ultimo->costo_nuevo - self::EPSILON;
    }

    private function descartarPendiente(Producto $producto): void
    {
        $producto->cambiosPrecio()
            ->where('estado', EstadoCambioPrecio::Pendiente)
            ->update(['estado' => EstadoCambioPrecio::Descartado]);
    }

    private function bloquearPendiente(CambioPrecio $cambio, string $accion): CambioPrecio
    {
        $cambio = CambioPrecio::query()->whereKey($cambio->getKey())->lockForUpdate()->firstOrFail();

        if (! $cambio->estaPendiente()) {
            throw OperacionNoPermitidaException::porEstado('este cambio de precio', $cambio->estado->getLabel(), $accion);
        }

        return $cambio;
    }

    /**
     * Una sola notificación por operación, a quien puede aprobar bajas.
     */
    private function avisar(int $cantidad, string $origen): void
    {
        $destinatarios = User::query()
            ->where('is_active', true)
            ->where(static function (Builder $query): void {
                $query
                    ->whereHas('roles', static function (Builder $roles): void {
                        $roles->where('name', ShieldUtils::getSuperAdminName());
                    })
                    ->orWhereHas('roles.permissions', static function (Builder $permisos): void {
                        $permisos->where('name', self::PERMISO_APROBAR);
                    })
                    ->orWhereHas('permissions', static function (Builder $permisos): void {
                        $permisos->where('name', self::PERMISO_APROBAR);
                    });
            })
            ->get();

        if ($destinatarios->isEmpty()) {
            return;
        }

        Notification::make()
            ->warning()
            ->icon(Heroicon::OutlinedArrowTrendingDown)
            ->title($cantidad === 1 ? 'Bajó el costo de un producto' : "Bajó el costo de {$cantidad} productos")
            ->body("{$origen}. El precio de venta sigue igual hasta que decidas si lo bajas.")
            ->actions([
                Action::make('revisar')
                    ->label('Revisar precios')
                    ->button()
                    ->url(CambioPrecioResource::getUrl('index', panel: 'admin'))
                    ->markAsRead(),
            ])
            ->sendToDatabase($destinatarios);
    }
}
