<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Enums\ListaPrecio;
use App\Models\Producto;
use App\Models\ProductoPresentacion;

/**
 * Precio unitario de venta (ISV incluido) según la presentación y la lista.
 *
 *  - Unidad base: precio público o, en mayoreo, el precio mayoreo si existe.
 *  - Presentación: su precio propio o, si no tiene, el base × factor.
 *    En mayoreo: el mayoreo base × factor, pero nunca más caro que el
 *    precio público de la presentación (una caja ya trae su rebaja).
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

        $factor = (float) $presentacion->factor;
        $publico = $presentacion->precio_venta !== null
            ? round((float) $presentacion->precio_venta, 2)
            : round($publicoBase * $factor, 2);

        if (! $usarMayoreo) {
            return $publico;
        }

        return min($publico, round((float) $mayoreoBase * $factor, 2));
    }
}
