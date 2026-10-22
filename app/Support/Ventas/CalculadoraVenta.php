<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Enums\TipoDescuento;
use App\Enums\TipoIsv;

/**
 * Cálculo de importes de venta con precios ISV INCLUIDO.
 *
 * Por línea: bruto = precio × cantidad; se resta el descuento (en % o en
 * lempiras por toda la línea); del total (con ISV) se separa la base
 * imponible y el ISV de modo que base + ISV = total exacto al centavo.
 *
 * En Honduras no circulan monedas: el bruto y el total de cada línea se
 * redondean al lempira hacia arriba (2.35 lb × L 30 = L 70.50 → L 71).
 * El descuento es lo que efectivamente se rebaja (bruto − total).
 *
 * El descuento a toda la venta (global) se reparte entre las líneas en
 * lempiras enteros (repartirGlobal), así el ISV se sigue separando por
 * tasa y cada línea queda en lempiras enteros.
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
    public static function linea(
        float $precioUnitario,
        float $cantidad,
        float $descuento,
        float $tasaIsv,
        TipoDescuento $tipoDescuento = TipoDescuento::Porcentaje,
    ): array {
        $bruto = self::alLempira($precioUnitario * $cantidad);
        $total = self::alLempira($bruto - self::rebaja($bruto, $descuento, $tipoDescuento));

        return self::desglose($bruto, $total, $tasaIsv);
    }

    /**
     * Lo que rebaja un descuento sobre un importe: X % de él o L X fijos,
     * nunca más que el importe.
     */
    public static function rebaja(float $importe, float $descuento, TipoDescuento $tipo): float
    {
        $descuento = max(0.0, $descuento);

        return $tipo === TipoDescuento::Monto
            ? min($descuento, $importe)
            : $importe * min($descuento, 100.0) / 100;
    }

    /**
     * El descuento expresado en % del importe, para compararlo con el tope
     * del rol (L 5 sobre L 100 = 5 %). Entre 0 y 100.
     */
    public static function porcentaje(float $importe, float $descuento, TipoDescuento $tipo): float
    {
        if ($tipo === TipoDescuento::Porcentaje) {
            return max(0.0, min($descuento, 100.0));
        }

        return $importe > 0 ? max(0.0, min($descuento / $importe * 100, 100.0)) : 0.0;
    }

    /**
     * Reparte un descuento a toda la venta entre las líneas.
     *
     * El total nuevo se redondea al lempira hacia arriba, como todo; lo
     * rebajado se reparte en lempiras enteros en proporción al total de
     * cada línea, y los lempiras que sobran del reparto van a las líneas
     * con mayor fracción (si empatan, a la primera).
     *
     * @param list<float> $totales total de cada línea, ya al lempira
     *
     * @return list<float> lo que se le rebaja a cada línea, en el mismo orden
     */
    public static function repartirGlobal(array $totales, float $descuento, TipoDescuento $tipo): array
    {
        $suma = round(array_sum($totales), 2);

        if ($suma <= 0 || $descuento <= 0) {
            return array_map(static fn (float $total): float => 0.0, $totales);
        }

        $rebajado = round($suma - self::alLempira($suma - self::rebaja($suma, $descuento, $tipo)), 2);
        $partes = [];
        $fracciones = [];

        foreach ($totales as $indice => $total) {
            $exacto = $rebajado * $total / $suma;
            $partes[$indice] = floor(round($exacto, 6));
            $fracciones[$indice] = $exacto - $partes[$indice];
        }

        // Los lempiras que faltan para llegar a lo rebajado, de uno en uno.
        arsort($fracciones);
        $faltan = (int) round($rebajado - array_sum($partes));

        foreach (array_keys($fracciones) as $indice) {
            if ($faltan <= 0) {
                break;
            }

            $partes[$indice] += 1.0;
            $faltan--;
        }

        ksort($partes);

        return array_values($partes);
    }

    /**
     * Una línea con su parte del descuento global ya restada: nuevo total,
     * descuento acumulado (bruto − total) y la base e ISV otra vez
     * separados.
     *
     * @return array{bruto: float, descuento: float, total: float, base: float, isv: float}
     */
    public static function conParteGlobal(float $bruto, float $total, float $parte, float $tasaIsv): array
    {
        return self::desglose($bruto, max(0.0, round($total - $parte, 2)), $tasaIsv);
    }

    /**
     * Al lempira hacia arriba. Primero a centavos para que el ruido de los
     * decimales (69.000000001) no sume un lempira de más.
     */
    public static function alLempira(float $monto): float
    {
        return $monto > 0 ? ceil(round($monto, 2)) : 0.0;
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

    /**
     * @return array{bruto: float, descuento: float, total: float, base: float, isv: float}
     */
    private static function desglose(float $bruto, float $total, float $tasaIsv): array
    {
        $base = round($total / (1 + $tasaIsv), 2);

        return [
            'bruto'     => $bruto,
            'descuento' => round($bruto - $total, 2),
            'total'     => $total,
            'base'      => $base,
            'isv'       => round($total - $base, 2),
        ];
    }
}
