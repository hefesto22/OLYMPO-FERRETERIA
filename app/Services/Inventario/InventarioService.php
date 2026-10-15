<?php

declare(strict_types=1);

namespace App\Services\Inventario;

use App\Domain\Exceptions\CantidadInvalidaException;
use App\Domain\Exceptions\StockInsuficienteException;
use App\Enums\TipoMovimientoInventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Único punto del sistema que modifica existencias y costo promedio.
 *
 * Cada operación:
 *  1. Bloquea la fila del producto (SELECT … FOR UPDATE) para que dos
 *     cajeros vendiendo el mismo producto no pisen el stock del otro.
 *  2. Calcula el nuevo saldo (y el costo promedio ponderado si entra).
 *  3. Deja el movimiento en el kardex con saldo anterior y nuevo.
 *
 * Todo dentro de una transacción: si algo falla, no queda nada a medias.
 * Las cantidades llegan SIEMPRE en unidad base (quien llama convierte
 * cajas, rollos o cubetas con el factor de la presentación).
 *
 * Los productos sin control de stock (servicios) no generan movimiento:
 * los métodos devuelven null.
 */
final class InventarioService
{
    /** Tolerancia para comparar cantidades con 3 decimales. */
    private const EPSILON = 0.0005;

    public function registrarEntrada(
        Producto $producto,
        float $cantidad,
        float $costoUnitario,
        TipoMovimientoInventario $tipo,
        ?Model $documento = null,
        ?string $descripcion = null,
    ): ?MovimientoInventario {
        $this->validarCantidad($cantidad);

        if (! $tipo->esEntrada()) {
            throw new LogicException("El movimiento {$tipo->value} no es de entrada.");
        }

        return DB::transaction(function () use ($producto, $cantidad, $costoUnitario, $tipo, $documento, $descripcion): ?MovimientoInventario {
            $bloqueado = $this->bloquear($producto);

            if (! $bloqueado->controla_stock) {
                return null;
            }

            $stockAnterior = (float) $bloqueado->stock;
            $costoAnterior = (float) $bloqueado->costo_promedio;
            $stockNuevo = round($stockAnterior + $cantidad, 3);
            $costoUnitario = round(max($costoUnitario, 0.0), 4);
            $costoNuevo = $this->costoPromedioTrasEntrada($stockAnterior, $costoAnterior, $cantidad, $costoUnitario);

            $cambios = [
                'stock'          => $stockNuevo,
                'costo_promedio' => $costoNuevo,
            ];

            // El "último costo" solo lo fija una compra real o el inventario inicial;
            // una devolución o un ajuste no cambian lo que cuesta reponer el producto.
            if (in_array($tipo, [TipoMovimientoInventario::EntradaCompra, TipoMovimientoInventario::InventarioInicial], true)) {
                $cambios['ultimo_costo'] = $costoUnitario;
            }

            $bloqueado->forceFill($cambios)->save();

            return $this->crearMovimiento(
                $bloqueado,
                $tipo,
                $cantidad,
                $stockAnterior,
                $stockNuevo,
                $costoUnitario,
                $costoNuevo,
                $documento,
                $descripcion,
            );
        });
    }

    /**
     * @param float|null $costoUnitario costo del movimiento; null = costo promedio actual
     * @param bool $revertirCosto true al anular una compra: saca su costo del promedio
     */
    public function registrarSalida(
        Producto $producto,
        float $cantidad,
        TipoMovimientoInventario $tipo,
        ?Model $documento = null,
        ?string $descripcion = null,
        ?float $costoUnitario = null,
        bool $revertirCosto = false,
    ): ?MovimientoInventario {
        $this->validarCantidad($cantidad);

        if ($tipo->esEntrada()) {
            throw new LogicException("El movimiento {$tipo->value} no es de salida.");
        }

        return DB::transaction(function () use ($producto, $cantidad, $tipo, $documento, $descripcion, $costoUnitario, $revertirCosto): ?MovimientoInventario {
            $bloqueado = $this->bloquear($producto);

            if (! $bloqueado->controla_stock) {
                return null;
            }

            $stockAnterior = (float) $bloqueado->stock;
            $costoAnterior = (float) $bloqueado->costo_promedio;

            if (! $this->permiteStockNegativo() && ($cantidad - $stockAnterior) > self::EPSILON) {
                throw StockInsuficienteException::para(
                    $bloqueado->nombre,
                    $cantidad,
                    $stockAnterior,
                    $bloqueado->unidadMedida->abreviatura,
                );
            }

            $stockNuevo = round($stockAnterior - $cantidad, 3);
            $costoMovimiento = round($costoUnitario ?? $costoAnterior, 4);
            $costoNuevo = $revertirCosto
                ? $this->costoPromedioTrasReversion($stockAnterior, $costoAnterior, $cantidad, $costoMovimiento)
                : $costoAnterior;

            $bloqueado->forceFill([
                'stock'          => $stockNuevo,
                'costo_promedio' => $costoNuevo,
            ])->save();

            return $this->crearMovimiento(
                $bloqueado,
                $tipo,
                $cantidad,
                $stockAnterior,
                $stockNuevo,
                $costoMovimiento,
                $costoNuevo,
                $documento,
                $descripcion,
            );
        });
    }

    /**
     * Promedio ponderado tras una entrada. Si no había existencia (o era
     * negativa), el costo nuevo es el de la entrada.
     */
    public function costoPromedioTrasEntrada(float $stockAnterior, float $costoAnterior, float $cantidad, float $costoUnitario): float
    {
        if ($stockAnterior <= 0) {
            return round($costoUnitario, 4);
        }

        $valorTotal = ($stockAnterior * $costoAnterior) + ($cantidad * $costoUnitario);

        return round($valorTotal / ($stockAnterior + $cantidad), 4);
    }

    /**
     * Quita del promedio el valor de una entrada que se anula. Si ya no
     * queda existencia, o el cálculo daría negativo (hubo ventas a otro
     * costo entre medio), se conserva el costo actual.
     */
    public function costoPromedioTrasReversion(float $stockAnterior, float $costoAnterior, float $cantidad, float $costoUnitario): float
    {
        $stockNuevo = $stockAnterior - $cantidad;

        if ($stockNuevo <= self::EPSILON) {
            return round($costoAnterior, 4);
        }

        $valorRestante = ($stockAnterior * $costoAnterior) - ($cantidad * $costoUnitario);

        if ($valorRestante < 0) {
            return round($costoAnterior, 4);
        }

        return round($valorRestante / $stockNuevo, 4);
    }

    private function bloquear(Producto $producto): Producto
    {
        return Producto::withTrashed()
            ->with('unidadMedida')
            ->whereKey($producto->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function crearMovimiento(
        Producto $producto,
        TipoMovimientoInventario $tipo,
        float $cantidad,
        float $stockAnterior,
        float $stockNuevo,
        float $costoUnitario,
        float $costoPromedio,
        ?Model $documento,
        ?string $descripcion,
    ): MovimientoInventario {
        return MovimientoInventario::query()->create([
            'producto_id'    => $producto->id,
            'tipo'           => $tipo,
            'cantidad'       => round($cantidad, 3),
            'stock_anterior' => $stockAnterior,
            'stock_nuevo'    => $stockNuevo,
            'costo_unitario' => $costoUnitario,
            'costo_promedio' => $costoPromedio,
            'documento_type' => $documento?->getMorphClass(),
            'documento_id'   => $documento?->getKey(),
            'descripcion'    => $descripcion,
            'user_id'        => Auth::id(),
        ]);
    }

    private function validarCantidad(float $cantidad): void
    {
        if ($cantidad <= 0) {
            throw CantidadInvalidaException::noPositiva($cantidad);
        }
    }

    private function permiteStockNegativo(): bool
    {
        return (bool) config('ferreteria.inventario.permitir_stock_negativo', false);
    }
}
