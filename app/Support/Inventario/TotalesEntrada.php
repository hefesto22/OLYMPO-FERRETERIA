<?php

declare(strict_types=1);

namespace App\Support\Inventario;

use App\Enums\TipoIsv;
use App\Models\Producto;

/**
 * Totales en vivo del formulario de entrada de inventario, con las mismas
 * fórmulas que EntradaInventarioDetalle::calcularImportes() aplica al guardar.
 */
final class TotalesEntrada
{
    /**
     * @param array<array-key, mixed>|null $lineas estado del repeater "detalles"
     *
     * @return array{subtotal: float, isv: float, total: float}
     */
    public static function desdeFormulario(?array $lineas): array
    {
        /** @var list<array{producto_id: int, cantidad: float, costo: float}> $filas */
        $filas = [];

        foreach ($lineas ?? [] as $linea) {
            if (! is_array($linea)) {
                continue;
            }

            $productoId = self::numero($linea['producto_id'] ?? null);

            if ($productoId <= 0) {
                continue;
            }

            $filas[] = [
                'producto_id' => (int) $productoId,
                'cantidad'    => self::numero($linea['cantidad'] ?? null),
                'costo'       => self::numero($linea['costo_unitario'] ?? null),
            ];
        }

        $ids = array_values(array_unique(array_column($filas, 'producto_id')));

        /** @var array<int, TipoIsv> $tiposIsv */
        $tiposIsv = Producto::withTrashed()
            ->whereIn('id', $ids)
            ->get(['id', 'tipo_isv'])
            ->mapWithKeys(fn (Producto $producto): array => [$producto->id => $producto->tipo_isv])
            ->all();

        $subtotal = 0.0;
        $isv = 0.0;

        foreach ($filas as $fila) {
            $tipoIsv = $tiposIsv[$fila['producto_id']] ?? null;

            if ($tipoIsv === null) {
                continue;
            }

            $importe = round($fila['cantidad'] * $fila['costo'], 2);
            $subtotal += $importe;
            $isv += round($importe * $tipoIsv->tasa(), 2);
        }

        $subtotal = round($subtotal, 2);
        $isv = round($isv, 2);

        return [
            'subtotal' => $subtotal,
            'isv'      => $isv,
            'total'    => round($subtotal + $isv, 2),
        ];
    }

    private static function numero(mixed $valor): float
    {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }
}
