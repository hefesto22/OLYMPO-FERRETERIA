<?php

declare(strict_types=1);

use App\Services\Inventario\InventarioService;

it('toma el costo de la entrada cuando no hay existencia', function (): void {
    expect((new InventarioService)->costoPromedioTrasEntrada(0, 0, 10, 7.25))->toBe(7.25);
});

it('ignora una existencia negativa al promediar', function (): void {
    expect((new InventarioService)->costoPromedioTrasEntrada(-4, 3.0, 10, 6.0))->toBe(6.0);
});

it('pondera el costo por las cantidades', function (): void {
    // (10 × 5 + 30 × 7) / 40
    expect((new InventarioService)->costoPromedioTrasEntrada(10, 5.0, 30, 7.0))->toBe(6.5);
});

it('saca del promedio el costo de una entrada anulada', function (): void {
    // (40 × 6.5 − 30 × 7) / 10
    expect((new InventarioService)->costoPromedioTrasReversion(40, 6.5, 30, 7.0))->toBe(5.0);
});

it('conserva el costo si la anulación deja la existencia en cero', function (): void {
    expect((new InventarioService)->costoPromedioTrasReversion(30, 7.0, 30, 7.0))->toBe(7.0);
});
