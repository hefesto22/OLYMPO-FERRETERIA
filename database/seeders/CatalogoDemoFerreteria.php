<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * Catálogo de demostración: lo que se vende en una ferretería hondureña,
 * por categoría, con su unidad base, costo, existencia y presentaciones.
 * Lo usa DemoFerreteriaSeeder.
 *
 * Se vende por unidad, por peso (libra, quintal), por volumen (galón,
 * litro), por metro lineal (cable, cadena, malla ciclón, poliducto),
 * por metro cuadrado (cerámica, pasto sintético, malla sombra), por
 * metro cúbico (arena, piedrín, selecto) y por pie tablar (madera).
 *
 * Cada producto: cat, marca, und (unidad base), costo (sin ISV, por unidad
 * base), stock inicial y mínimo. El precio sale de `margen` [% público,
 * % mayoreo]; si no trae margen, `precio` y `mayoreo` van a mano (ISV
 * incluido). `isv`: 'exento' para insumos agrícolas (si no, 15 %).
 *
 * Presentaciones [und, cantidad, nombre, precio, forma, margen]: "Caja
 * 1.96 m²" contiene 1.96 de la unidad base; forma 'por_base' = "100 por
 * LB". Precio null = automático (con su margen propio si lo trae).
 *
 * @phpstan-type PresentacionDemo array{0: string, 1: float|int, 2: string, 3?: float|int|null, 4?: string, 5?: float|int}
 * @phpstan-type ProductoDemo array{cat: string, marca: string|null, und: string, costo: float|int, stock: float|int, min: float|int, precio?: float|int, mayoreo?: float|int|null, margen?: array{0: float|int, 1: float|int|null}, pres?: list<PresentacionDemo>, servicio?: bool, isv?: string}
 */
final class CatalogoDemoFerreteria
{
    /**
     * Los productos del catálogo, por nombre. Vienen de
     * `datos/catalogo-ferreteria.php`: en una constante de esta clase
     * PHPStan pierde la forma de cada producto (son muchos), así que aquí
     * se le da.
     *
     * @return array<string, ProductoDemo>
     */
    public static function productos(): array
    {
        /** @var array<string, ProductoDemo> $productos */
        $productos = require __DIR__.'/datos/catalogo-ferreteria.php';

        return $productos;
    }
}
