<?php

declare(strict_types=1);

use App\Enums\EquivalenciaPresentacion;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;

function productoEnLibras(): Producto
{
    /** @var Producto $producto */
    $producto = Producto::factory()->create([
        'unidad_medida_id' => UnidadMedida::factory()->fraccionable()->create(['abreviatura' => 'LB'])->id,
    ]);

    return $producto;
}

it('calcula el factor cuando se indica cuántas caben en la unidad base', function (): void {
    /** @var ProductoPresentacion $suelto */
    $suelto = productoEnLibras()->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'factor_cantidad'  => 100,
        'factor_modo'      => EquivalenciaPresentacion::PorUnidadBase,
    ]);

    expect((float) $suelto->factor)->toBe(0.01)
        ->and($suelto->equivalencia())->toBe('100 por LB');
});

it('conserva la precisión con cantidades que no dan un factor exacto', function (): void {
    /** @var ProductoPresentacion $suelto */
    $suelto = productoEnLibras()->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'factor_cantidad'  => 106,
        'factor_modo'      => EquivalenciaPresentacion::PorUnidadBase,
    ]);

    expect((float) $suelto->refresh()->factor)->toBe(0.00943396)
        ->and($suelto->equivalencia())->toBe('106 por LB');
});

it('toma el factor como lo tecleado cuando solo se indica el factor', function (): void {
    /** @var ProductoPresentacion $caja */
    $caja = productoEnLibras()->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'nombre'           => 'Caja 50 lb',
        'factor'           => 50,
    ]);

    expect((float) $caja->factor_cantidad)->toBe(50.0)
        ->and($caja->factor_modo)->toBe(EquivalenciaPresentacion::Contiene)
        ->and($caja->equivalencia())->toBe('50 LB');
});

it('recalcula el factor al cambiar la forma de captura', function (): void {
    /** @var ProductoPresentacion $presentacion */
    $presentacion = productoEnLibras()->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'factor_cantidad'  => 4,
        'factor_modo'      => EquivalenciaPresentacion::Contiene,
    ]);

    $presentacion->update(['factor_modo' => EquivalenciaPresentacion::PorUnidadBase]);

    expect((float) $presentacion->refresh()->factor)->toBe(0.25);
});
