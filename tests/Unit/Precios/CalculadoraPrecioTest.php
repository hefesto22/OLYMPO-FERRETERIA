<?php

declare(strict_types=1);

use App\Enums\TipoMargen;
use App\Support\Precios\CalculadoraPrecio;

it('suma el margen en porcentaje y el ISV, y redondea al lempira hacia arriba', function (): void {
    // 18.80 + 30 % = 24.44; con ISV 15 % = 28.11 → L 29.
    expect(CalculadoraPrecio::precio(18.80, TipoMargen::Porcentaje, 30, 0.15))->toBe(29.0);
});

it('suma el margen en lempiras por unidad base', function (): void {
    // (18.80 + 5) × 1.15 = 27.37 → L 28.
    expect(CalculadoraPrecio::precio(18.80, TipoMargen::Monto, 5, 0.15))->toBe(28.0);
});

it('no agrega ISV a un producto exento', function (): void {
    // 18.80 × 1.30 = 24.44 → L 25.
    expect(CalculadoraPrecio::precio(18.80, TipoMargen::Porcentaje, 30, 0.0))->toBe(25.0);
});

it('nunca deja un precio por debajo de un lempira', function (): void {
    // Un clavo suelto: 0.188 × 1.30 × 1.15 = 0.28 → L 1.
    expect(CalculadoraPrecio::precio(18.80, TipoMargen::Porcentaje, 30, 0.15, 0.01))->toBe(1.0)
        ->and(CalculadoraPrecio::precio(18.80, TipoMargen::Monto, 5, 0.15, 0.01))->toBe(1.0);
});

it('no sube un precio que ya es exacto por el ruido de los decimales', function (): void {
    // 20 × 1.15 da 22.999999999999996 en coma flotante: el precio es L 23, no L 24.
    expect(CalculadoraPrecio::precio(20.00, TipoMargen::Porcentaje, 0, 0.15))->toBe(23.0)
        ->and(CalculadoraPrecio::redondear(27.0))->toBe(27.0);
});

it('calcula una presentación sobre su propio costo', function (): void {
    // Caja de 50 lb: 940 × 1.30 × 1.15 = 1405.30 → L 1406.
    expect(CalculadoraPrecio::precio(18.80, TipoMargen::Porcentaje, 30, 0.15, 50))->toBe(1406.0);
});

it('no calcula precio sin costo ni con precio a mano', function (): void {
    expect(CalculadoraPrecio::precio(0, TipoMargen::Porcentaje, 30, 0.15))->toBe(0.0)
        ->and(CalculadoraPrecio::precio(18.80, TipoMargen::Manual, 30, 0.15))->toBe(0.0)
        ->and(CalculadoraPrecio::redondear(0))->toBe(0.0);
});

it('explica el cálculo paso a paso', function (): void {
    $paso = CalculadoraPrecio::desglose(18.80, TipoMargen::Porcentaje, 30, 0.15);

    expect(round($paso['sin_isv'], 2))->toBe(24.44)
        ->and(round($paso['exacto'], 2))->toBe(28.11)
        ->and($paso['precio'])->toBe(29.0);
});
