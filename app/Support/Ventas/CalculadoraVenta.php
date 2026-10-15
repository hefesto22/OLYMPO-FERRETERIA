<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Enums\TipoIsv;

/**
 * Cálculo de importes de venta con precios ISV INCLUIDO.
 *
 * Por línea: bruto = precio × cantidad; se resta el descuento; del total
 * (con ISV) se separa la base imponible y el ISV de modo que
 * base + ISV = total exacto al centavo.
 *
 * Es la única fórmula: la usan el punto de venta (para mostrar) y
 * VentaService (para guardar), así que lo que ve el cajero es lo que
 * queda en la factura.
 */
final class CalculadoraVenta
{
    /**
     * @return array{bruto: float, descuento: float, total: float, base: float, isv: float}
     */
    public static function linea(float $precioUnitario, float $cantidad, float $descuentoPct, float $tasaIsv): array
    {
        $descuentoPct = max(0.0, min($descuentoPct, 100.0));

        $bruto = round($precioUnitario * $cantidad, 2);
        $descuento = round($bruto * $descuentoPct / 100, 2);
        $total = round($bruto - $descuento, 2);
        $base = round($total / (1 + $tasaIsv), 2);
        $isv = round($total - $base, 2);

        return [
            'bruto'     => $bruto,
            'descuento' => $descuento,
            'total'     => $total,
            'base'      => $base,
            'isv'       => $isv,
        ];
    }

    /**
     * Desglose de la factura según el SAR.
     *
     * @param iterable<array{tipo_isv: TipoIsv, descuento: float, total: float, base: float, isv: float}> $lineas
     *
     * @return array{descuento: float, importe_exento: float, importe_gravado_15: float, importe_gravado_18: float, isv_15: float, isv_18: float, subtotal: float, total: float}
     */
    public static function totales(iterable $lineas): array
    {
        $totales = [
            'descuento'          => 0.0,
            'importe_exento'     => 0.0,
            'importe_gravado_15' => 0.0,
            'importe_gravado_18' => 0.0,
            'isv_15'             => 0.0,
            'isv_18'             => 0.0,
            'subtotal'           => 0.0,
            'total'              => 0.0,
        ];

        foreach ($lineas as $linea) {
            $totales['descuento'] += $linea['descuento'];
            $totales['subtotal'] += $linea['base'];
            $totales['total'] += $linea['total'];

            switch ($linea['tipo_isv']) {
                case TipoIsv::Exento:
                    $totales['importe_exento'] += $linea['base'];

                    break;
                case TipoIsv::Gravado15:
                    $totales['importe_gravado_15'] += $linea['base'];
                    $totales['isv_15'] += $linea['isv'];

                    break;
                case TipoIsv::Gravado18:
                    $totales['importe_gravado_18'] += $linea['base'];
                    $totales['isv_18'] += $linea['isv'];

                    break;
            }
        }

        return [
            'descuento'          => round($totales['descuento'], 2),
            'importe_exento'     => round($totales['importe_exento'], 2),
            'importe_gravado_15' => round($totales['importe_gravado_15'], 2),
            'importe_gravado_18' => round($totales['importe_gravado_18'], 2),
            'isv_15'             => round($totales['isv_15'], 2),
            'isv_18'             => round($totales['isv_18'], 2),
            'subtotal'           => round($totales['subtotal'], 2),
            'total'              => round($totales['total'], 2),
        ];
    }
}
