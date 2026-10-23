<?php

declare(strict_types=1);

namespace App\Services\Inventario;

use App\Domain\Exceptions\OperacionNoPermitidaException;
use App\Enums\EstadoDocumento;
use App\Enums\TipoMovimientoInventario;
use App\Models\EntradaInventario;
use App\Models\EntradaInventarioDetalle;
use App\Services\Precios\PrecioService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de una entrada de inventario: borrador → confirmada → (anulada).
 */
final class EntradaInventarioService
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly PrecioService $precios,
    ) {}

    /**
     * Recalcula subtotal, ISV y total desde las líneas guardadas.
     */
    public function recalcularTotales(EntradaInventario $entrada): EntradaInventario
    {
        $entrada->load('detalles');

        $subtotal = round($entrada->detalles->sum(fn (EntradaInventarioDetalle $detalle): float => (float) $detalle->subtotal), 2);
        $isv = round($entrada->detalles->sum(fn (EntradaInventarioDetalle $detalle): float => (float) $detalle->isv), 2);

        $entrada->forceFill([
            'subtotal' => $subtotal,
            'isv'      => $isv,
            'total'    => round($subtotal + $isv, 2),
        ])->save();

        return $entrada;
    }

    /**
     * Suma cada línea al inventario (en unidad base) y actualiza el costo
     * promedio. Una vez confirmada, la entrada ya no se edita.
     */
    public function confirmar(EntradaInventario $entrada): EntradaInventario
    {
        return DB::transaction(function () use ($entrada): EntradaInventario {
            $entrada = EntradaInventario::query()->whereKey($entrada->getKey())->lockForUpdate()->firstOrFail();

            if ($entrada->estado !== EstadoDocumento::Borrador) {
                throw OperacionNoPermitidaException::porEstado("la entrada {$entrada->numero}", $entrada->estado->getLabel(), 'confirmar');
            }

            $entrada->load('detalles.producto');

            if ($entrada->detalles->isEmpty()) {
                throw OperacionNoPermitidaException::sinLineas("la entrada {$entrada->numero}");
            }

            foreach ($entrada->detalles as $detalle) {
                // Recalcula importes con el factor e ISV vigentes al confirmar.
                $detalle->save();

                $this->inventario->registrarEntrada(
                    $detalle->producto,
                    (float) $detalle->cantidad_base,
                    (float) $detalle->costo_unitario_base,
                    TipoMovimientoInventario::Entrada,
                    $entrada,
                    "Entrada {$entrada->numero}",
                );
            }

            $this->recalcularTotales($entrada);

            // Con el costo nuevo: los precios con margen suben solos; si bajan, esperan al dueño.
            $this->precios->revisar($this->productoIds($entrada), "Entrada {$entrada->numero}");

            $entrada->forceFill([
                'estado'         => EstadoDocumento::Confirmado,
                'confirmada_at'  => now(),
                'confirmada_por' => Auth::id(),
            ])->save();

            return $entrada;
        });
    }

    /**
     * Revierte el efecto de una entrada confirmada: saca del inventario lo
     * que entró y quita su costo del promedio. Falla si parte de esa
     * mercadería ya se vendió (no alcanza el stock).
     */
    public function anular(EntradaInventario $entrada, string $motivo): EntradaInventario
    {
        return DB::transaction(function () use ($entrada, $motivo): EntradaInventario {
            $entrada = EntradaInventario::query()->whereKey($entrada->getKey())->lockForUpdate()->firstOrFail();

            if ($entrada->estado !== EstadoDocumento::Confirmado) {
                throw OperacionNoPermitidaException::porEstado("la entrada {$entrada->numero}", $entrada->estado->getLabel(), 'anular');
            }

            $entrada->load('detalles.producto');

            foreach ($entrada->detalles as $detalle) {
                $this->inventario->registrarSalida(
                    $detalle->producto,
                    (float) $detalle->cantidad_base,
                    TipoMovimientoInventario::AnulacionEntrada,
                    $entrada,
                    "Anulación de la entrada {$entrada->numero}",
                    costoUnitario: (float) $detalle->costo_unitario_base,
                    revertirCosto: true,
                );
            }

            $this->precios->revisar($this->productoIds($entrada), "Anulación de la entrada {$entrada->numero}");

            $entrada->forceFill([
                'estado'           => EstadoDocumento::Anulado,
                'anulada_at'       => now(),
                'anulada_por'      => Auth::id(),
                'motivo_anulacion' => $motivo,
            ])->save();

            return $entrada;
        });
    }

    /**
     * @return array<int, int>
     */
    private function productoIds(EntradaInventario $entrada): array
    {
        return $entrada->detalles->map(fn (EntradaInventarioDetalle $detalle): int => $detalle->producto_id)->all();
    }
}
