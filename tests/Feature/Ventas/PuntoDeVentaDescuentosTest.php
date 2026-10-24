<?php

declare(strict_types=1);

use App\Enums\RolFerreteria;
use App\Enums\TipoDescuento;
use App\Enums\TipoMovimientoInventario;
use App\Filament\Pages\PuntoDeVenta;
use App\Models\Caja;
use App\Models\ConfiguracionEmpresa;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\Inventario\InventarioService;
use App\Services\Ventas\TurnoCajaService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
| Descuento en el punto de venta: uno solo, a toda la venta, en % o en
| lempiras. Lo que se ve en pantalla es lo que queda guardado al cobrar.
*/

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    ConfiguracionEmpresa::actual()->update(['descuento_max_cajero' => 20]);

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

it('cobra con un solo descuento a toda la venta', function (): void {
    /** @var Producto $martillo */
    $martillo = Producto::factory()->create(['nombre' => 'Martillo de uña', 'precio_venta' => 115]);
    app(InventarioService::class)->registrarEntrada($martillo, 10, 50.00, TipoMovimientoInventario::InventarioInicial);

    $pos = Livewire::test(PuntoDeVenta::class)->call('agregar', $martillo->id);

    /** @var array<string, mixed> $lineas */
    $lineas = $pos->get('lineas');
    $clave = (string) array_key_first($lineas);

    // 2 × 115 = 230; menos 10 % a toda la venta = 207. Las líneas ya no traen descuento propio.
    $pos->set("lineas.{$clave}.cantidad", 2)
        ->assertDontSee('Descuento de Martillo de uña')
        ->set('venta.descuento_global', 10)
        ->assertSee('L. 207.00')
        ->assertSee('− L. 23.00')
        ->call('cobrar');

    $venta = Venta::query()->sole();
    $detalle = $venta->detalles()->sole();

    expect((float) $venta->total)->toBe(207.0)
        ->and((float) $venta->descuento)->toBe(23.0)
        ->and((float) $venta->descuento_global)->toBe(23.0)
        ->and($venta->descuento_global_tipo)->toBe(TipoDescuento::Porcentaje)
        ->and($detalle->descuentoDeLinea())->toBe(0.0);
});

it('avisa en pantalla cuando el descuento pasa el tope del cajero', function (): void {
    ConfiguracionEmpresa::actual()->update(['descuento_max_cajero' => 5]);
    $martillo = Producto::factory()->create(['nombre' => 'Martillo de uña', 'precio_venta' => 100]);

    Livewire::test(PuntoDeVenta::class)
        ->call('agregar', $martillo->id)
        ->set('venta.descuento_global', 10)
        ->assertSee('El descuento es de 10 % y tu máximo es 5 %.');
});
