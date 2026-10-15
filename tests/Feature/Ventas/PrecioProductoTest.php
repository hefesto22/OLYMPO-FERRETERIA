<?php

declare(strict_types=1);

use App\Enums\ListaPrecio;
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
