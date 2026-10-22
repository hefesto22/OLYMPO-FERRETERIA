<?php

declare(strict_types=1);

use App\Enums\ListaPrecio;
use App\Enums\TipoMargen;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Support\Ventas\PrecioProducto;

it('usa el precio público o el de mayoreo en la unidad base', function (): void {
    $clavo = (new Producto)->forceFill(['precio_venta' => 28.00, 'precio_mayoreo' => 25.50]);

    expect(PrecioProducto::unitario($clavo, null, ListaPrecio::Publico))->toBe(28.0)
        ->and(PrecioProducto::unitario($clavo, null, ListaPrecio::Mayoreo))->toBe(25.5);
});

it('usa el precio público si el producto no tiene mayoreo', function (): void {
    $tornillo = (new Producto)->forceFill(['precio_venta' => 0.75, 'precio_mayoreo' => null]);

    expect(PrecioProducto::unitario($tornillo, null, ListaPrecio::Mayoreo))->toBe(0.75);
});

it('usa el precio propio de la presentación', function (): void {
    $tornillo = (new Producto)->forceFill(['precio_venta' => 0.75, 'precio_mayoreo' => null]);
    $bolsa = (new ProductoPresentacion)->forceFill(['factor' => 100, 'precio_venta' => 65.00]);

    expect(PrecioProducto::unitario($tornillo, $bolsa, ListaPrecio::Publico))->toBe(65.0);
});

it('multiplica por el factor si la presentación no tiene precio', function (): void {
    $pintura = (new Producto)->forceFill(['precio_venta' => 420.00, 'precio_mayoreo' => null]);
    $cubeta = (new ProductoPresentacion)->forceFill(['factor' => 5, 'precio_venta' => null]);

    expect(PrecioProducto::unitario($pintura, $cubeta, ListaPrecio::Publico))->toBe(2100.0);
});

it('en mayoreo nunca encarece una presentación que ya trae su rebaja', function (): void {
    $clavo = (new Producto)->forceFill(['precio_venta' => 28.00, 'precio_mayoreo' => 25.50]);
    $caja = (new ProductoPresentacion)->forceFill(['factor' => 50, 'precio_venta' => 1250.00]);

    // 25.50 × 50 = 1275 > 1250: se queda el precio de la caja.
    expect(PrecioProducto::unitario($clavo, $caja, ListaPrecio::Mayoreo))->toBe(1250.0);
});

it('en mayoreo calcula la presentación sin precio propio con el mayoreo base', function (): void {
    $clavo = (new Producto)->forceFill(['precio_venta' => 28.00, 'precio_mayoreo' => 25.50]);
    $caja = (new ProductoPresentacion)->forceFill(['factor' => 50, 'precio_venta' => null]);

    expect(PrecioProducto::unitario($clavo, $caja, ListaPrecio::Publico))->toBe(1400.0)
        ->and(PrecioProducto::unitario($clavo, $caja, ListaPrecio::Mayoreo))->toBe(1275.0);
});

it('redondea al lempira el precio automático de una presentación', function (): void {
    $suelto = (new ProductoPresentacion)->forceFill(['factor' => 0.01, 'precio_venta' => null]);
    $media = (new ProductoPresentacion)->forceFill(['factor' => 0.5, 'precio_venta' => null]);

    // 28 × 0.01 = 0.28 → L 1; 27.50 × 0.5 = 13.75 → L 14.
    expect(PrecioProducto::unitario((new Producto)->forceFill(['precio_venta' => 28.00, 'precio_mayoreo' => null]), $suelto, ListaPrecio::Publico))->toBe(1.0)
        ->and(PrecioProducto::unitario((new Producto)->forceFill(['precio_venta' => 27.50, 'precio_mayoreo' => null]), $media, ListaPrecio::Publico))->toBe(14.0);
});

it('con margen calcula cada presentación sobre su propio costo', function (): void {
    $clavo = (new Producto)->forceFill([
        'margen_tipo'    => TipoMargen::Porcentaje,
        'margen_publico' => 30,
        'margen_mayoreo' => 20,
        'costo_precio'   => 18.80,
        'precio_venta'   => 29.00,
        'precio_mayoreo' => 26.00,
    ]);

    $bolsa = (new ProductoPresentacion)->forceFill(['factor' => 1, 'precio_venta' => null]);
    $suelto = (new ProductoPresentacion)->forceFill(['factor' => 0.01, 'precio_venta' => null]);
    $caja = (new ProductoPresentacion)->forceFill(['factor' => 50, 'precio_venta' => null]);

    // Caja: 940 × 1.30 × 1.15 = 1405.30 → L 1406; en mayoreo 940 × 1.20 × 1.15 = 1297.20 → L 1298.
    expect(PrecioProducto::unitario($clavo, $bolsa, ListaPrecio::Publico))->toBe(29.0)
        ->and(PrecioProducto::unitario($clavo, $suelto, ListaPrecio::Publico))->toBe(1.0)
        ->and(PrecioProducto::unitario($clavo, $caja, ListaPrecio::Publico))->toBe(1406.0)
        ->and(PrecioProducto::unitario($clavo, $caja, ListaPrecio::Mayoreo))->toBe(1298.0);
});

it('una presentación con margen propio usa ese margen', function (): void {
    $clavo = (new Producto)->forceFill([
        'margen_tipo'    => TipoMargen::Porcentaje,
        'margen_publico' => 30,
        'margen_mayoreo' => 20,
        'costo_precio'   => 18.80,
        'precio_venta'   => 29.00,
        'precio_mayoreo' => 26.00,
    ]);

    $caja = (new ProductoPresentacion)->forceFill(['factor' => 50, 'precio_venta' => null, 'margen' => 15]);

    // 940 × 1.15 × 1.15 = 1243.15 → L 1244. El mayoreo (20 %) saldría más caro: queda igual.
    expect(PrecioProducto::unitario($clavo, $caja, ListaPrecio::Publico))->toBe(1244.0)
        ->and(PrecioProducto::unitario($clavo, $caja, ListaPrecio::Mayoreo))->toBe(1244.0);
});

it('con margen en lempiras el margen propio es por presentación', function (): void {
    $clavo = (new Producto)->forceFill([
        'margen_tipo'    => TipoMargen::Monto,
        'margen_publico' => 5,
        'margen_mayoreo' => null,
        'costo_precio'   => 18.80,
        'precio_venta'   => 28.00,
        'precio_mayoreo' => null,
    ]);

    $caja = (new ProductoPresentacion)->forceFill(['factor' => 50, 'precio_venta' => null, 'margen' => 150]);

    // (940 + 150) × 1.15 = 1253.50 → L 1254.
    expect(PrecioProducto::unitario($clavo, $caja, ListaPrecio::Publico))->toBe(1254.0);
});
