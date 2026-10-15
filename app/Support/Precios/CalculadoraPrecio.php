<?php

declare(strict_types=1);

namespace App\Support\Precios;

use App\Enums\TipoMargen;

/**
 * Precio de venta a partir del costo y el margen. Una sola fórmula para
 * el formulario del producto, el punto de venta y la revisión de precios.
 *
 *   precio = (costo + margen) + ISV, redondeado al lempira hacia arriba
 *
 * En Honduras no circulan monedas, así que todo precio calculado es un
 * número entero y nunca baja de L 1 (un clavo suelto cuesta L 1).
 *
 * El costo es por unidad base y sin ISV. `$factor` convierte a una
 * presentación: una bolsa de 1 lb es factor 1, un clavo suelto 0.01. El
 * margen en lempiras también es por unidad base, así que se multiplica
 * por el factor igual que el costo.
 */
final class CalculadoraPrecio
{
    public static function precio(float $costoUnitario, TipoMargen $tipo, float $margen, float $tasaIsv, float $factor = 1.0): float
    {
        return self::redondear(self::desglose($costoUnitario, $tipo, $margen, $tasaIsv, $factor)['exacto']);
    }

    /**
     * Paso a paso, para explicarle al usuario de dónde sale el precio.
     *
     * @return array{costo: float, sin_isv: float, exacto: float, precio: float}
     */
    public static function desglose(float $costoUnitario, TipoMargen $tipo, float $margen, float $tasaIsv, float $factor = 1.0): array
    {
        $costo = $costoUnitario * $factor;

        if ($costo <= 0 || $tipo === TipoMargen::Manual) {
            return ['costo' => max($costo, 0.0), 'sin_isv' => 0.0, 'exacto' => 0.0, 'precio' => 0.0];
        }

        $margen = max($margen, 0.0);

        $sinIsv = $tipo === TipoMargen::Porcentaje
            ? $costo * (1 + $margen / 100)
            : ($costoUnitario + $margen) * $factor;

        $exacto = $sinIsv * (1 + $tasaIsv);

        return [
            'costo'   => $costo,
            'sin_isv' => $sinIsv,
            'exacto'  => $exacto,
            'precio'  => self::redondear($exacto),
        ];
    }

    /**
     * Al lempira hacia arriba, mínimo L 1. Primero se redondea a centavos
     * para que el ruido de los decimales (22.999999… o 23.000001) no
     * cambie un precio que ya es exacto.
     */
    public static function redondear(float $precio): float
    {
        if ($precio <= 0) {
            return 0.0;
        }

        return max(1.0, ceil(round($precio, 2)));
    }
}
