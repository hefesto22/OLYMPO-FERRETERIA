<?php

declare(strict_types=1);

use App\Enums\TipoMargen;
use App\Support\Precios\CalculadoraPrecio;

it('suma el ISV y el margen en porcentaje, y redondea al lempira hacia arriba', function (): void {
    // 18.80 con ISV 15 % = 21.62; + 30 % = 28.11 → L 29.
    expect(CalculadoraPrecio::precio(18.80, TipoMargen::Porcentaje, 30, 0.15))->toBe(29.0);
});

it('suma el margen en lempiras sobre lo que te sale con ISV', function (): void {
    // Costo neto L 10 → con ISV L 11.50; + L 3 = 14.50 → L 15.
    expect(CalculadoraPrecio::precio(10, TipoMargen::Monto, 3, 0.15))->toBe(15.0)
        // 18.80 × 1.15 = 21.62; + L 5 = 26.62 → L 27.
        ->and(CalculadoraPrecio::precio(18.80, TipoMargen::Monto, 5, 0.15))->toBe(27.0)
        // Exento: 10 + 3 = L 13.
        ->and(CalculadoraPrecio::precio(10, TipoMargen::Monto, 3, 0.0))->toBe(13.0);
});

it('no agrega ISV a un producto exento', function (): void {
    // 18.80 × 1.30 = 24.44 → L 25.
    expect(CalculadoraPrecio::precio(18.80, TipoMargen::Porcentaje, 30, 0.0))->toBe(25.0);
});

it('nunca deja un precio por debajo de un lempira', function (): void {
    // Un clavo suelto: 0.188 × 1.15 × 1.30 = 0.28 → L 1.
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

it('explica el cálculo paso a paso: costo, ISV, en cuánto te sale y margen', function (): void {
    $paso = CalculadoraPrecio::desglose(18.80, TipoMargen::Porcentaje, 30, 0.15);

    expect(round($paso['isv'], 2))->toBe(2.82)
        ->and(round($paso['con_isv'], 2))->toBe(21.62)
        ->and(round($paso['margen'], 2))->toBe(6.49)
        ->and(round($paso['exacto'], 2))->toBe(28.11)
        ->and($paso['precio'])->toBe(29.0);

    $enLempiras = CalculadoraPrecio::desglose(10, TipoMargen::Monto, 3, 0.15);

    expect(round($enLempiras['con_isv'], 2))->toBe(11.5)
        ->and($enLempiras['margen'])->toBe(3.0)
        ->and(round($enLempiras['exacto'], 2))->toBe(14.5)
        ->and($enLempiras['precio'])->toBe(15.0);
});

it('calcula la ganancia sobre lo que te sale con ISV', function (): void {
    // Te sale en 21.62; a L 29 le ganas L 7.38 = 34.1 %.
    expect(round((float) CalculadoraPrecio::gananciaMonto(29, 18.80, 0.15), 2))->toBe(7.38)
        ->and(round((float) CalculadoraPrecio::ganancia(29, 18.80, 0.15), 3))->toBe(0.341)
        ->and(CalculadoraPrecio::ganancia(29, 0, 0.15))->toBeNull()
        ->and(CalculadoraPrecio::gananciaMonto(0, 18.80, 0.15))->toBeNull();
});

it('toma como meta el margen propio de la presentación o el del producto', function (): void {
    expect(CalculadoraPrecio::margenObjetivo(TipoMargen::Porcentaje, 15, 30, 50, 940))->toBe(0.15)
        ->and(CalculadoraPrecio::margenObjetivo(TipoMargen::Porcentaje, null, 30, 50, 940))->toBe(0.3)
        ->and(CalculadoraPrecio::margenObjetivo(TipoMargen::Monto, null, 5, 50, 1000))->toBe(0.25)
        ->and(CalculadoraPrecio::margenObjetivo(TipoMargen::Monto, 150, 5, 50, 1000))->toBe(0.15)
        // En lempiras, sobre lo que te sale con ISV: 230 / (1000 × 1.15) = 20 %.
        ->and(CalculadoraPrecio::margenObjetivo(TipoMargen::Monto, 230, 5, 50, 1000, 0.15))->toBe(0.2)
        ->and(CalculadoraPrecio::margenObjetivo(TipoMargen::Manual, null, null, 1, 100))->toBeNull();
});
