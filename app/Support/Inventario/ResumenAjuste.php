<?php

declare(strict_types=1);

namespace App\Support\Inventario;

use App\Enums\TipoAjuste;

/**
 * Qué le pasa al inventario con lo que se está tecleando en un ajuste,
 * antes de aplicarlo: cuánto cambia cada producto, cuánto queda y cuánto
 * vale ese cambio. Usa las mismas reglas que al aplicar (en un conteo, lo
 * contado contra la existencia de hoy). Solo calcula.
 *
 * El valor va al costo neto y con el ISV que se pagó al comprar: una
 * merma se pierde con todo y su ISV.
 *
 * @phpstan-type LineaAjuste array{unidad: string, existencia: float, cambio: float, queda: float, valor: float, isv: float, total: float, exento: bool}
 */
final class ResumenAjuste
{
    /**
     * `valor` es al costo neto (sin ISV), `isv` el que se pagó por eso y
     * `total` la suma: lo que se pierde o lo que entra.
     *
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

        $tasa = $producto->tipo_isv->tasa();
        $valor = round(abs($cambio) * $costo, 2);
        $isv = round($valor * $tasa, 2);

        return [
            'unidad'     => $producto->unidadMedida->abreviatura,
            'existencia' => $existencia,
            'cambio'     => $cambio,
            'queda'      => round($existencia + $cambio, 6),
            'valor'      => $valor,
            'isv'        => $isv,
            'total'      => round($valor + $isv, 2),
            'exento'     => $tasa <= 0,
        ];
    }

    /**
     * `entra` y `sale` van al costo neto; `entra_isv` y `sale_isv`, el ISV
     * que se pagó por eso.
     *
     * @param array<array-key, mixed>|null $lineas estado del repeater "detalles"
     *
     * @return array{tipo: string, productos: int, suben: int, bajan: int, iguales: int, entra: float, entra_isv: float, sale: float, sale_isv: float}
     */
    public static function desdeFormulario(TipoAjuste $tipo, ?array $lineas): array
    {
        $resumen = [
            'tipo'      => $tipo->value,
            'productos' => 0,
            'suben'     => 0,
            'bajan'     => 0,
            'iguales'   => 0,
            'entra'     => 0.0,
            'entra_isv' => 0.0,
            'sale'      => 0.0,
            'sale_isv'  => 0.0,
        ];

        foreach ($lineas ?? [] as $linea) {
            $calculo = is_array($linea) ? self::linea($tipo, $linea) : null;

            if ($calculo === null) {
                continue;
            }

            $resumen['productos']++;

            if ($calculo['cambio'] > 0) {
                $resumen['suben']++;
                $resumen['entra'] += $calculo['valor'];
                $resumen['entra_isv'] += $calculo['isv'];
            } elseif ($calculo['cambio'] < 0) {
                $resumen['bajan']++;
                $resumen['sale'] += $calculo['valor'];
                $resumen['sale_isv'] += $calculo['isv'];
            } else {
                $resumen['iguales']++;
            }
        }

        $resumen['entra'] = round($resumen['entra'], 2);
        $resumen['entra_isv'] = round($resumen['entra_isv'], 2);
        $resumen['sale'] = round($resumen['sale'], 2);
        $resumen['sale_isv'] = round($resumen['sale_isv'], 2);

        return $resumen;
    }
}
