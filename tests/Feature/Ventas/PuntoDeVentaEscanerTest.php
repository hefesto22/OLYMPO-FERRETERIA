<?php

declare(strict_types=1);

use App\Enums\RolFerreteria;
use App\Filament\Pages\PuntoDeVenta;
use App\Models\Caja;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Ventas\TurnoCajaService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
| Escáner con la cámara en el punto de venta: cada código leído agrega una
| unidad y responde qué pasó (se muestra sobre la cámara), sin buscar por
| nombre. Estas pruebas también dibujan la pantalla del mostrador.
*/

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');

    // Sin el permiso de la página, Filament responde 403 en vez de dibujarla.
    $rol = Role::findOrCreate(RolFerreteria::Cajero->value, 'web')
        ->givePermissionTo(Permission::findOrCreate('View:PuntoDeVenta', 'web'));

    /** @var User $cajero */
    $cajero = User::factory()->create();
    $cajero->assignRole($rol);

    /** @var Caja $caja */
    $caja = Caja::factory()->create(['punto_emision' => '001']);

    app(TurnoCajaService::class)->abrir($caja, $cajero, 0);

    test()->actingAs($cajero);
});

it('agrega el producto del código leído y suma uno si se vuelve a leer', function (): void {
    Producto::factory()->create(['nombre' => 'Martillo de uña', 'codigo_barras' => '7501031311309']);

    $pos = Livewire::test(PuntoDeVenta::class)
        ->call('escanear', '7501031311309')
        ->assertReturned(['ok' => true, 'texto' => 'Martillo de uña: 1 en el carrito'])
        ->call('escanear', ' 7501031311309 ')
        ->assertReturned(['ok' => true, 'texto' => 'Martillo de uña: 2 en el carrito']);

    /** @var array<string, array{producto_id: int, presentacion_id: int|null, cantidad: float|int}> $lineas */
    $lineas = $pos->get('lineas');

    expect($lineas)->toHaveCount(1)
        ->and((float) array_values($lineas)[0]['cantidad'])->toBe(2.0);
});

it('agrega la presentación cuando el código es de la presentación', function (): void {
    $producto = Producto::factory()->create(['nombre' => 'Clavo de 2 pulgadas']);
    $caja = $producto->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'nombre'           => 'Caja 50 lb',
        'factor'           => 50,
        'codigo_barras'    => '7409999000017',
    ]);

    $pos = Livewire::test(PuntoDeVenta::class)
        ->call('escanear', '7409999000017')
        ->assertReturned(['ok' => true, 'texto' => 'Clavo de 2 pulgadas, Caja 50 lb: 1 en el carrito']);

    /** @var array<string, array{producto_id: int, presentacion_id: int|null, cantidad: float|int}> $lineas */
    $lineas = $pos->get('lineas');
    $linea = array_values($lineas)[0];

    expect((int) $linea['producto_id'])->toBe($producto->id)
        ->and((int) $linea['presentacion_id'])->toBe($caja->id);
});

it('avisa sin agregar nada si ningún producto tiene ese código exacto', function (): void {
    Producto::factory()->create(['nombre' => 'Martillo 7501']);

    Livewire::test(PuntoDeVenta::class)
        ->call('escanear', '7501')
        ->assertReturned(['ok' => false, 'texto' => 'Ningún producto tiene el código 7501.'])
        ->assertSet('lineas', []);
});
