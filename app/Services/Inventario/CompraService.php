<?php

declare(strict_types=1);

namespace App\Services\Inventario;

use App\Domain\Exceptions\OperacionNoPermitidaException;
use App\Enums\EstadoDocumento;
use App\Enums\TipoMovimientoInventario;
use App\Models\Compra;
use App\Models\CompraDetalle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de una compra: borrador → confirmada → (anulada).
 */
final class CompraService
{
    public function __construct(
        private readonly InventarioService $inventario,
    ) {}

    /**
     * Recalcula subtotal, ISV y total desde las líneas guardadas.
     */
    public function recalcularTotales(Compra $compra): Compra
    {
        $compra->load('detalles');

        $subtotal = round($compra->detalles->sum(fn (CompraDetalle $detalle): float => (float) $detalle->subtotal), 2);
        $isv = round($compra->detalles->sum(fn (CompraDetalle $detalle): float => (float) $detalle->isv), 2);

        $compra->forceFill([
            'subtotal' => $subtotal,
            'isv'      => $isv,
            'total'    => round($subtotal + $isv, 2),
        ])->save();

        return $compra;
    }

    /**
     * Suma cada línea al inventario (en unidad base) y actualiza el costo
     * promedio. Una vez confirmada, la compra ya no se edita.
     */
    public function confirmar(Compra $compra): Compra
    {
        return DB::transaction(function () use ($compra): Compra {
            $compra = Compra::query()->whereKey($compra->getKey())->lockForUpdate()->firstOrFail();

            if ($compra->estado !== EstadoDocumento::Borrador) {
                throw OperacionNoPermitidaException::porEstado("la compra {$compra->numero}", $compra->estado->getLabel(), 'confirmar');
            }

            $compra->load('detalles.producto');

            if ($compra->detalles->isEmpty()) {
                throw OperacionNoPermitidaException::sinLineas("la compra {$compra->numero}");
            }

            foreach ($compra->detalles as $detalle) {
                // Recalcula importes con el factor e ISV vigentes al confirmar.
                $detalle->save();

                $this->inventario->registrarEntrada(
                    $detalle->producto,
                    (float) $detalle->cantidad_base,
                    (float) $detalle->costo_unitario_base,
                    TipoMovimientoInventario::EntradaCompra,
                    $compra,
                    "Compra {$compra->numero}",
                );
            }

            $this->recalcularTotales($compra);

            $compra->forceFill([
                'estado'         => EstadoDocumento::Confirmado,
                'confirmada_at'  => now(),
                'confirmada_por' => Auth::id(),
            ])->save();

            return $compra;
        });
    }

    /**
     * Revierte el efecto de una compra confirmada: saca del inventario lo
     * que entró y quita su costo del promedio. Falla si parte de esa
     * mercadería ya se vendió (no alcanza el stock).
     */
    public function anular(Compra $compra, string $motivo): Compra
    {
        return DB::transaction(function () use ($compra, $motivo): Compra {
            $compra = Compra::query()->whereKey($compra->getKey())->lockForUpdate()->firstOrFail();

            if ($compra->estado !== EstadoDocumento::Confirmado) {
                throw OperacionNoPermitidaException::porEstado("la compra {$compra->numero}", $compra->estado->getLabel(), 'anular');
            }

            $compra->load('detalles.producto');

            foreach ($compra->detalles as $detalle) {
                $this->inventario->registrarSalida(
                    $detalle->producto,
                    (float) $detalle->cantidad_base,
                    TipoMovimientoInventario::AnulacionCompra,
                    $compra,
                    "Anulación de la compra {$compra->numero}",
                    costoUnitario: (float) $detalle->costo_unitario_base,
                    revertirCosto: true,
                );
            }

            $compra->forceFill([
                'estado'           => EstadoDocumento::Anulado,
                'anulada_at'       => now(),
                'anulada_por'      => Auth::id(),
                'motivo_anulacion' => $motivo,
            ])->save();

            return $compra;
        });
    }
}
