<?php

declare(strict_types=1);

namespace App\Support\Precios;

use App\Enums\TipoMargen;

/**
 * Precio de venta a partir del costo y el margen. Una sola fórmula para
 * el formulario del producto, el punto de venta y la revisión de precios.
 *
 *   te sale en = costo + ISV
 *   precio     = te sale en + margen, redondeado al lempira hacia arriba
 *
 * El margen se saca sobre lo que te sale el producto con ISV: con costo
 * neto de L 10 y 15 % de ISV te sale en L 11.50; con L 3 de margen el
 * precio es L 14.50 → L 15. En porcentaje da lo mismo sumar el ISV antes
 * o después; en lempiras, el margen queda limpio sobre los L 11.50.
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
     * Paso a paso, para explicarle al usuario de dónde sale el precio:
     * costo neto, su ISV, en cuánto te sale, el margen y el precio.
     *
     * @return array{costo: float, isv: float, con_isv: float, margen: float, exacto: float, precio: float}
     */
    public static function desglose(float $costoUnitario, TipoMargen $tipo, float $margen, float $tasaIsv, float $factor = 1.0): array
    {
        $costo = $costoUnitario * $factor;

        if ($costo <= 0 || $tipo === TipoMargen::Manual) {
            return ['costo' => max($costo, 0.0), 'isv' => 0.0, 'con_isv' => 0.0, 'margen' => 0.0, 'exacto' => 0.0, 'precio' => 0.0];
        }

        $margen = max($margen, 0.0);
        $conIsv = $costo * (1 + $tasaIsv);

        $montoMargen = $tipo === TipoMargen::Porcentaje
            ? $conIsv * $margen / 100
            : $margen * $factor;

        $exacto = $conIsv + $montoMargen;

        return [
            'costo'   => $costo,
            'isv'     => $conIsv - $costo,
            'con_isv' => $conIsv,
            'margen'  => $montoMargen,
            'exacto'  => $exacto,
            'precio'  => self::redondear($exacto),
        ];
    }

    /**
     * Ganancia en lempiras de un precio con ISV: lo que queda después de
     * pagar lo que te sale el producto con ISV. Null si falta precio o
     * costo.
     */
    public static function gananciaMonto(float $precioConIsv, float $costo, float $tasaIsv): ?float
    {
        if ($precioConIsv <= 0 || $costo <= 0) {
            return null;
        }

        return $precioConIsv - $costo * (1 + $tasaIsv);
    }

    /**
     * Ganancia como fracción (0.30 = 30 %) de lo que te sale el producto
     * con ISV. Null si falta precio o costo.
     */
    public static function ganancia(float $precioConIsv, float $costo, float $tasaIsv): ?float
    {
        $monto = self::gananciaMonto($precioConIsv, $costo, $tasaIsv);

        return $monto !== null ? $monto / ($costo * (1 + $tasaIsv)) : null;
    }

    /**
     * Ganancia mínima esperada (fracción sobre el costo con ISV) de una
     * presentación: su margen propio o el del producto. En lempiras, el
     * margen propio es por presentación y el del producto por unidad base.
     * Null si el producto lleva precio a mano o falta el dato.
     */
    public static function margenObjetivo(TipoMargen $tipo, ?float $margenPropio, ?float $margenProducto, float $factor, float $costoPresentacion, float $tasaIsv = 0.0): ?float
    {
        $margen = $margenPropio ?? $margenProducto;

        if ($tipo === TipoMargen::Manual || $margen === null) {
            return null;
        }

        if ($tipo === TipoMargen::Porcentaje) {
            return $margen / 100;
        }

        if ($costoPresentacion <= 0) {
            return null;
        }

        $monto = $margenPropio !== null ? $margenPropio : $margen * $factor;

        return $monto / ($costoPresentacion * (1 + $tasaIsv));
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
