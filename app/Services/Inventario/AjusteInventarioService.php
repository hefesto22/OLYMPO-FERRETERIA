<?php

declare(strict_types=1);

namespace App\Services\Inventario;

use App\Domain\Exceptions\OperacionNoPermitidaException;
use App\Enums\EstadoDocumento;
use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
use App\Enums\TipoMovimientoInventario;
use App\Models\AjusteInventario;
use App\Models\Producto;
use App\Services\Precios\PrecioService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Aplica un ajuste de inventario. Un ajuste aplicado no se anula: si
 * hubo un error se corrige con otro ajuste en sentido contrario, que
 * deja rastro de ambos en el kardex.
 *
 * En un conteo físico la diferencia se vuelve a sacar al aplicar, con la
 * existencia de ese momento: cada producto queda en lo contado aunque
 * se haya vendido algo mientras el ajuste estaba en borrador.
 */
final class AjusteInventarioService
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly PrecioService $precios,
    ) {}

    public function aplicar(AjusteInventario $ajuste): AjusteInventario
    {
        return DB::transaction(function () use ($ajuste): AjusteInventario {
            $ajuste = AjusteInventario::query()->whereKey($ajuste->getKey())->lockForUpdate()->firstOrFail();

            if ($ajuste->estado !== EstadoDocumento::Borrador) {
                throw OperacionNoPermitidaException::porEstado("el ajuste {$ajuste->numero}", $ajuste->estado->getLabel(), 'aplicar');
            }

            $ajuste->load('detalles.producto');

            if ($ajuste->detalles->isEmpty()) {
                throw OperacionNoPermitidaException::sinLineas("el ajuste {$ajuste->numero}");
            }

            $descripcion = "Ajuste {$ajuste->numero}: {$ajuste->motivo->getLabel()}";

            /** @var list<int> $conEntrada */
            $conEntrada = [];

            foreach ($ajuste->detalles as $detalle) {
                // La existencia de este momento, con la fila bloqueada hasta el final.
                $producto = Producto::withTrashed()->whereKey($detalle->producto_id)->lockForUpdate()->firstOrFail();
                $existencia = (float) $producto->stock;

                if ($ajuste->tipo === TipoAjuste::Conteo) {
                    $detalle->calcularConteo($existencia);
                } else {
                    $detalle->forceFill(['existencia_antes' => $existencia]);
                }

                $tipo = $detalle->tipoMovimiento($ajuste->tipo);
                $cantidad = (float) $detalle->cantidad;

                // Queda el costo con que se mueve: lo tecleado en una entrada o,
                // si no, el promedio de este momento (con eso se ve cuánto se perdió).
                if ($tipo !== TipoAjuste::Entrada || (float) $detalle->costo_unitario <= 0) {
                    $detalle->forceFill(['costo_unitario' => (float) $producto->costo_promedio]);
                }

                $detalle->saveQuietly();

                // Conteo igual a lo que había: no se mueve nada.
                if ($tipo === null || $cantidad <= 0) {
                    continue;
                }

                if ($tipo === TipoAjuste::Entrada) {
                    $costo = (float) $detalle->costo_unitario;

                    $tipoMovimiento = $ajuste->motivo === MotivoAjuste::InventarioInicial
                        ? TipoMovimientoInventario::InventarioInicial
                        : TipoMovimientoInventario::AjusteEntrada;

                    $this->inventario->registrarEntrada($producto, $cantidad, $costo, $tipoMovimiento, $ajuste, $descripcion);
                    $conEntrada[] = $producto->id;

                    continue;
                }

                $this->inventario->registrarSalida($producto, $cantidad, TipoMovimientoInventario::AjusteSalida, $ajuste, $descripcion);
            }

            // Una entrada con costo mueve el promedio: los precios con margen se revisan.
            if ($conEntrada !== []) {
                $this->precios->revisar($conEntrada, $descripcion);
            }

            $ajuste->forceFill([
                'estado'       => EstadoDocumento::Confirmado,
                'aplicado_at'  => now(),
                'aplicado_por' => Auth::id(),
            ])->save();

            return $ajuste;
        });
    }
}
