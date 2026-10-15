<?php

declare(strict_types=1);

namespace App\Services\Inventario;

use App\Domain\Exceptions\OperacionNoPermitidaException;
use App\Enums\EstadoDocumento;
use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
use App\Enums\TipoMovimientoInventario;
use App\Models\AjusteInventario;
use App\Models\AjusteInventarioDetalle;
use App\Services\Precios\PrecioService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Aplica un ajuste de inventario. Un ajuste aplicado no se anula: si
 * hubo un error se corrige con otro ajuste en sentido contrario, que
 * deja rastro de ambos en el kardex.
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

            foreach ($ajuste->detalles as $detalle) {
                $producto = $detalle->producto;
                $cantidad = (float) $detalle->cantidad;

                if ($ajuste->tipo === TipoAjuste::Entrada) {
                    $costo = (float) $detalle->costo_unitario > 0
                        ? (float) $detalle->costo_unitario
                        : (float) $producto->costo_promedio;

                    $tipoMovimiento = $ajuste->motivo === MotivoAjuste::InventarioInicial
                        ? TipoMovimientoInventario::InventarioInicial
                        : TipoMovimientoInventario::AjusteEntrada;

                    $this->inventario->registrarEntrada($producto, $cantidad, $costo, $tipoMovimiento, $ajuste, $descripcion);

                    continue;
                }

                $this->inventario->registrarSalida($producto, $cantidad, TipoMovimientoInventario::AjusteSalida, $ajuste, $descripcion);
            }

            // Una entrada con costo mueve el promedio: los precios con margen se revisan.
            if ($ajuste->tipo === TipoAjuste::Entrada) {
                $this->precios->revisar(
                    $ajuste->detalles->map(fn (AjusteInventarioDetalle $detalle): int => $detalle->producto_id)->all(),
                    $descripcion,
                );
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
