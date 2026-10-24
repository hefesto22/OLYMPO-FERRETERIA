<?php

declare(strict_types=1);

use App\Enums\TipoIsv;
use App\Models\Producto;
use Database\Seeders\CatalogoDemoFerreteria;
use Database\Seeders\DemoFerreteriaSeeder;

/*
| La demo trae lo que se vende en una ferretería: por unidad, por peso,
| por metro lineal, cuadrado y cúbico, y por pie tablar. Se puede volver
| a correr: solo agrega lo que falta.
*/

it('siembra todo el catálogo con su existencia y sus precios, sin duplicar al repetir', function (): void {
    test()->seed(DemoFerreteriaSeeder::class);

    expect(Producto::query()->count())->toBe(count(CatalogoDemoFerreteria::productos()));

    $arena = Producto::query()->with('unidadMedida')->where('nombre', 'Arena de río')->sole();
    $pasto = Producto::query()->with('unidadMedida')->where('nombre', 'Pasto sintético 20 mm')->sole();
    $metroLineal = $pasto->presentaciones()->where('nombre', 'Metro lineal (rollo de 2 m de ancho)')->sole();
    $madera = Producto::query()->with('unidadMedida')->where('nombre', 'Madera de pino rústica')->sole();
    $fertilizante = Producto::query()->where('nombre', 'Fertilizante 15-15-15')->sole();

    // Arena: L 420 el m³ + 15 % = L 483, + 30 % de margen = L 627.90 → L 628.
    expect($arena->unidadMedida->abreviatura)->toBe('M3')
        ->and((float) $arena->stock)->toBe(30.0)
        ->and((float) $arena->precio_venta)->toBe(628.0)
        ->and($pasto->unidadMedida->abreviatura)->toBe('M2')
        ->and((float) $metroLineal->factor)->toBe(2.0)
        ->and($madera->unidadMedida->abreviatura)->toBe('PT')
        ->and($fertilizante->tipo_isv)->toBe(TipoIsv::Exento);

    test()->seed(DemoFerreteriaSeeder::class);

    expect(Producto::query()->count())->toBe(count(CatalogoDemoFerreteria::productos()));
});
