<?php

declare(strict_types=1);

use App\Enums\TipoDescuento;
use App\Enums\TipoIsv;
use App\Support\Ventas\CalculadoraVenta;

it('separa el ISV de un precio que ya lo incluye', function (): void {
    expect(CalculadoraVenta::linea(115.00, 2, 0, 0.15))->toBe([
        'bruto'     => 230.0,
        'descuento' => 0.0,
        'total'     => 230.0,
        'base'      => 200.0,
        'isv'       => 30.0,
    ]);
});

it('aplica el descuento antes de separar el ISV y redondea el total al lempira', function (): void {
    // 115 − 10 % = 103.50 → L 104; el descuento efectivo queda en L 11.
    expect(CalculadoraVenta::linea(115.00, 1, 10, 0.15))->toBe([
        'bruto'     => 115.0,
        'descuento' => 11.0,
        'total'     => 104.0,
        'base'      => 90.43,
        'isv'       => 13.57,
    ]);
});

it('redondea al lempira hacia arriba una venta por peso', function (): void {
    // 2.35 lb × L 30 = L 70.50 → L 71.
    $linea = CalculadoraVenta::linea(30.00, 2.35, 0, 0.15);

    expect($linea['bruto'])->toBe(71.0)
        ->and($linea['total'])->toBe(71.0)
        ->and($linea['descuento'])->toBe(0.0);
});

it('no suma un lempira de más por el ruido de los decimales', function (): void {
    // 2.3 × 30 = 68.99999999999999 en coma flotante: es L 69.
    expect(CalculadoraVenta::linea(30.00, 2.3, 0, 0.15)['total'])->toBe(69.0)
        ->and(CalculadoraVenta::linea(115.00, 1, 100, 0.15)['total'])->toBe(0.0);
});

it('no calcula ISV en productos exentos', function (): void {
    $linea = CalculadoraVenta::linea(100.00, 1, 0, 0.0);

    expect($linea['base'])->toBe(100.0)
        ->and($linea['isv'])->toBe(0.0);
});

it('base más ISV da siempre el total exacto al centavo', function (): void {
    // 7 × 0.75 = 5.25 → L 6.
    $linea = CalculadoraVenta::linea(0.75, 7, 0, 0.15);

    expect($linea['total'])->toBe(6.0)
        ->and(round($linea['base'] + $linea['isv'], 2))->toBe(6.0);
});

it('desglosa los totales como pide el SAR', function (): void {
    $totales = CalculadoraVenta::totales([
        ['tipo_isv' => TipoIsv::Gravado15, 'descuento' => 0.0, 'total' => 230.0, 'base' => 200.0, 'isv' => 30.0],
        ['tipo_isv' => TipoIsv::Exento, 'descuento' => 0.0, 'total' => 100.0, 'base' => 100.0, 'isv' => 0.0],
        ['tipo_isv' => TipoIsv::Gravado15, 'descuento' => 11.5, 'total' => 103.5, 'base' => 90.0, 'isv' => 13.5],
    ]);

    expect($totales)->toBe([
        'descuento'          => 11.5,
        'importe_exento'     => 100.0,
        'importe_gravado_15' => 290.0,
        'importe_gravado_18' => 0.0,
        'isv_15'             => 43.5,
        'isv_18'             => 0.0,
        'subtotal'           => 390.0,
        'total'              => 433.5,
    ]);
});

it('aplica un descuento en lempiras por toda la línea', function (): void {
    // 2 × 115 = 230 − L 30 = 200.
    expect(CalculadoraVenta::linea(115.00, 2, 30, 0.15, TipoDescuento::Monto))->toBe([
        'bruto'     => 230.0,
        'descuento' => 30.0,
        'total'     => 200.0,
        'base'      => 173.91,
        'isv'       => 26.09,
    ]);
});

it('un descuento en lempiras nunca deja la línea en negativo', function (): void {
    $linea = CalculadoraVenta::linea(32.00, 1, 50, 0.15, TipoDescuento::Monto);

    expect($linea['total'])->toBe(0.0)
        ->and($linea['descuento'])->toBe(32.0);
});

it('expresa el descuento en porcentaje para compararlo con el tope', function (): void {
    expect(CalculadoraVenta::porcentaje(200.0, 10, TipoDescuento::Monto))->toBe(5.0)
        ->and(CalculadoraVenta::porcentaje(0.0, 10, TipoDescuento::Monto))->toBe(0.0)
        ->and(CalculadoraVenta::porcentaje(200.0, 7.5, TipoDescuento::Porcentaje))->toBe(7.5)
        ->and(CalculadoraVenta::porcentaje(200.0, 150, TipoDescuento::Porcentaje))->toBe(100.0);
});

it('reparte el descuento global en proporción al total de cada línea', function (): void {
    // 200 − 10 % = 180: los L 20 se reparten 10, 5 y 5.
    expect(CalculadoraVenta::repartirGlobal([100.0, 50.0, 50.0], 10, TipoDescuento::Porcentaje))->toBe([10.0, 5.0, 5.0]);
});

it('reparte en lempiras enteros y el lempira que sobra va a la mayor fracción', function (): void {
    // L 100 sobre 71 + 32 + 1450: 4.57, 2.06 y 93.37 → 5, 2 y 93.
    $partes = CalculadoraVenta::repartirGlobal([71.0, 32.0, 1450.0], 100, TipoDescuento::Monto);

    expect($partes)->toBe([5.0, 2.0, 93.0])
        ->and(array_sum($partes))->toBe(100.0);
});

it('redondea al lempira hacia arriba el total con descuento global', function (): void {
    // 1650 − 3 % = 1600.50 → L 1601: se rebajan L 49.
    expect(CalculadoraVenta::repartirGlobal([1650.0], 3, TipoDescuento::Porcentaje))->toBe([49.0])
        ->and(CalculadoraVenta::repartirGlobal([1650.0, 32.0], 0, TipoDescuento::Porcentaje))->toBe([0.0, 0.0])
        ->and(CalculadoraVenta::repartirGlobal([], 10, TipoDescuento::Monto))->toBe([]);
});

it('vuelve a separar base e ISV de la línea con su parte del global', function (): void {
    expect(CalculadoraVenta::conParteGlobal(1650.0, 1650.0, 49.0, 0.15))->toBe([
        'bruto'     => 1650.0,
        'descuento' => 49.0,
        'total'     => 1601.0,
        'base'      => 1392.17,
        'isv'       => 208.83,
    ]);
});
