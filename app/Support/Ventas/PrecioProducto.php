<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Enums\ListaPrecio;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Support\Precios\CalculadoraPrecio;

/**
 * Precio unitario de venta (ISV incluido) según la presentación y la lista.
 *
 *  - Unidad base: precio público o, en mayoreo, el precio mayoreo si existe.
 *  - Presentación con precio propio: ese precio.
 *  - Presentación sin precio propio (precio automático):
 *      · producto con margen → el margen propio de la presentación (o el
 *        del producto) sobre su costo: una bolsa de 1 lb o un clavo
 *        suelto cuestan lo que llevan;
 *      · producto a mano → el precio base × factor.
 *    Siempre en lempiras enteros y nunca menos de L 1.
 *  - En mayoreo, una presentación nunca sale más cara que en público
 *    (una caja ya trae su rebaja).
 *
 * El precio lo decide el servidor; desde caja solo se puede aplicar un
 * descuento, dentro del tope del rol.
 */
final class PrecioProducto
{
    public static function unitario(Producto $producto, ?ProductoPresentacion $presentacion, ListaPrecio $lista): float
    {
        $publicoBase = (float) $producto->precio_venta;
        $mayoreoBase = $producto->precio_mayoreo !== null ? (float) $producto->precio_mayoreo : null;
        $usarMayoreo = $lista === ListaPrecio::Mayoreo && $mayoreoBase !== null && $mayoreoBase > 0;

        if ($presentacion === null) {
            return $usarMayoreo ? round(min($publicoBase, (float) $mayoreoBase), 2) : round($publicoBase, 2);
        }

        [$publicoAutomatico, $mayoreoAutomatico] = self::automaticos($producto, $presentacion);

        $publico = $presentacion->precio_venta !== null
            ? round((float) $presentacion->precio_venta, 2)
            : $publicoAutomatico;

        if (! $usarMayoreo || $mayoreoAutomatico === null) {
            return $publico;
        }

        return min($publico, $mayoreoAutomatico);
    }

    /**
     * Precio automático (público y mayoreo) de una presentación.
     *
     * @return array{0: float, 1: float|null}
     */
    public static function automaticos(Producto $producto, ProductoPresentacion $presentacion): array
    {
        $factor = (float) $presentacion->factor;

        if ($producto->usaMargen()) {
            $costo = (float) $producto->costo_precio;

            return [
                $producto->precioPresentacionConMargen($costo, $presentacion) ?? 0.0,
                $producto->precioPresentacionConMargen($costo, $presentacion, mayoreo: true),
            ];
        }

        $mayoreoBase = $producto->precio_mayoreo !== null ? (float) $producto->precio_mayoreo : null;

        return [
            CalculadoraPrecio::redondear((float) $producto->precio_venta * $factor),
            $mayoreoBase !== null && $mayoreoBase > 0 ? CalculadoraPrecio::redondear($mayoreoBase * $factor) : null,
        ];
    }
}
