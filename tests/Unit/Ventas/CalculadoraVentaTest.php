<?php

declare(strict_types=1);

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

it('aplica el descuento antes de separar el ISV', function (): void {
    expect(CalculadoraVenta::linea(115.00, 1, 10, 0.15))->toBe([
        'bruto'     => 115.0,
        'descuento' => 11.5,
        'total'     => 103.5,
        'base'      => 90.0,
        'isv'       => 13.5,
    ]);
});

it('no calcula ISV en productos exentos', function (): void {
    $linea = CalculadoraVenta::linea(100.00, 1, 0, 0.0);

    expect($linea['base'])->toBe(100.0)
        ->and($linea['isv'])->toBe(0.0);
});

it('base más ISV da siempre el total exacto al centavo', function (): void {
    $linea = CalculadoraVenta::linea(0.75, 7, 0, 0.15);

    expect($linea['total'])->toBe(5.25)
        ->and(round($linea['base'] + $linea['isv'], 2))->toBe(5.25);
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
