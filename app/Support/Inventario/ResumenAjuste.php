<?php

declare(strict_types=1);

namespace App\Support\Inventario;

use App\Enums\TipoAjuste;

/**
 * Qué le pasa al inventario con lo que se está tecleando en un ajuste,
 * antes de aplicarlo: cuánto cambia cada producto, cuánto queda y cuánto
 * vale ese cambio al costo. Usa las mismas reglas que al aplicar (en un
 * conteo, lo contado contra la existencia de hoy). Solo calcula.
 *
 * @phpstan-type LineaAjuste array{unidad: string, existencia: float, cambio: float, queda: float, valor: float}
 */
final class ResumenAjuste
{
    /**
     * @param array<array-key, mixed> $linea estado de una fila del repeater "detalles"
     *
     * @return LineaAjuste|null null si falta el producto o la cantidad
     */
    public static function linea(TipoAjuste $tipo, array $linea): ?array
    {
        $producto = OpcionesProducto::producto($linea['producto_id'] ?? null);

        if ($producto === null || ! $producto->controla_stock) {
            return null;
        }

        $existencia = (float) $producto->stock;
        $costo = (float) $producto->costo_promedio;

        if ($tipo === TipoAjuste::Conteo) {
            if (! is_numeric($linea['contado'] ?? null)) {
                return null;
            }

            $cambio = round((float) $linea['contado'] - $existencia, 6);
        } else {
            $cantidad = is_numeric($linea['cantidad'] ?? null) ? (float) $linea['cantidad'] : 0.0;

            if ($cantidad <= 0) {
                return null;
            }

            $cambio = $tipo === TipoAjuste::Salida ? -$cantidad : $cantidad;
            $costoTecleado = is_numeric($linea['costo_unitario'] ?? null) ? (float) $linea['costo_unitario'] : 0.0;

            if ($tipo === TipoAjuste::Entrada && $costoTecleado > 0) {
                $costo = $costoTecleado;
            }
        }

        return [
            'unidad'     => $producto->unidadMedida->abreviatura,
            'existencia' => $existencia,
            'cambio'     => $cambio,
            'queda'      => round($existencia + $cambio, 6),
            'valor'      => round(abs($cambio) * $costo, 2),
        ];
    }

    /**
     * @param array<array-key, mixed>|null $lineas estado del repeater "detalles"
     *
     * @return array{tipo: string, productos: int, suben: int, bajan: int, iguales: int, entra: float, sale: float}
     */
    public static function desdeFormulario(TipoAjuste $tipo, ?array $lineas): array
    {
        $resumen = ['tipo' => $tipo->value, 'productos' => 0, 'suben' => 0, 'bajan' => 0, 'iguales' => 0, 'entra' => 0.0, 'sale' => 0.0];

        foreach ($lineas ?? [] as $linea) {
            $calculo = is_array($linea) ? self::linea($tipo, $linea) : null;

            if ($calculo === null) {
                continue;
            }

            $resumen['productos']++;

            if ($calculo['cambio'] > 0) {
                $resumen['suben']++;
                $resumen['entra'] += $calculo['valor'];
            } elseif ($calculo['cambio'] < 0) {
                $resumen['bajan']++;
                $resumen['sale'] += $calculo['valor'];
            } else {
                $resumen['iguales']++;
            }
        }

        $resumen['entra'] = round($resumen['entra'], 2);
        $resumen['sale'] = round($resumen['sale'], 2);

        return $resumen;
    }
}
