<?php

declare(strict_types=1);

use App\Enums\RolFerreteria;
use App\Enums\TipoMovimientoInventario;
use App\Filament\Pages\PuntoDeVenta;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\RangoCai;
use App\Models\User;
use App\Models\Venta;
use App\Services\Inventario\InventarioService;
use App\Services\Ventas\TurnoCajaService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
| Datos de la venta en el carrito (documento, precio y cliente): con el
| primer producto pasan a una línea de resumen para que la lista de lo que
| se lleva tenga espacio, y se abren solos si algo de ahí no pasa al cobrar.
| La factura lleva el nombre y el RTN del cliente elegido.
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

it('cierra los datos de la venta con el primer producto y los abre al vaciar', function (): void {
    $martillo = Producto::factory()->create(['nombre' => 'Martillo de uña']);
    $brocha = Producto::factory()->create(['nombre' => 'Brocha 3"']);

    Livewire::test(PuntoDeVenta::class)
        ->assertSet('datosAbiertos', true)
        ->call('agregar', $martillo->id)
        ->assertSet('datosAbiertos', false)
        ->assertDispatched('linea-agregada')
        ->call('vaciar')
        ->assertSet('datosAbiertos', true)
        ->call('agregar', $martillo->id)
        ->assertSet('datosAbiertos', false)
        // Si el cajero los abre, agregar más productos no los vuelve a cerrar.
        ->set('datosAbiertos', true)
        ->call('agregar', $brocha->id)
        ->assertSet('datosAbiertos', true);
});

it('resume el documento, el cliente y el RTN cuando los datos están cerrados', function (): void {
    $martillo = Producto::factory()->create(['nombre' => 'Martillo de uña']);
    $cliente = Cliente::factory()->create(['nombre' => 'Constructora Santa Rosa', 'rtn' => '05011999000123']);

    $pos = Livewire::test(PuntoDeVenta::class)
        ->set('venta.tipo_documento', 'factura')
        ->set('venta.cliente_id', $cliente->id)
        ->call('agregar', $martillo->id)
        ->assertSeeInOrder(['Factura', 'CONSTRUCTORA SANTA ROSA', 'RTN 05011999000123']);

    /** @var PuntoDeVenta $pagina */
    $pagina = $pos->instance();

    expect($pagina->resumenDatos())->toBe([
        'factura' => true,
        'mayoreo' => false,
        'cliente' => 'CONSTRUCTORA SANTA ROSA',
        'rtn'     => '05011999000123',
    ]);
});

it('factura con el nombre y el RTN del cliente elegido', function (): void {
    $caja = Caja::query()->where('punto_emision', '001')->firstOrFail();
    RangoCai::factory()->create(['caja_id' => $caja->id, 'punto_emision' => '001']);

    $martillo = Producto::factory()->create(['nombre' => 'Martillo de uña', 'precio_venta' => 115]);
    app(InventarioService::class)->registrarEntrada($martillo, 10, 50.00, TipoMovimientoInventario::InventarioInicial);

    $cliente = Cliente::factory()->create(['nombre' => 'Constructora Santa Rosa', 'rtn' => '05011999000123']);

    Livewire::test(PuntoDeVenta::class)
        ->call('agregar', $martillo->id)
        ->set('venta.tipo_documento', 'factura')
        ->set('venta.cliente_id', $cliente->id)
        ->call('cobrar')
        ->assertHasNoErrors()
        ->assertDispatched('imprimir-ticket');

    $venta = Venta::query()->sole();

    expect($venta->esFactura())->toBeTrue()
        ->and($venta->cliente_id)->toBe($cliente->id)
        ->and($venta->cliente_nombre)->toBe('CONSTRUCTORA SANTA ROSA')
        ->and($venta->cliente_rtn)->toBe('05011999000123');
});

it('guarda el nombre y la dirección del cliente en mayúsculas', function (): void {
    $cliente = Cliente::factory()->create(['nombre' => '  ferretería ñandú ', 'direccion' => 'barrio el centro, santa rosa de copán']);

    expect($cliente->refresh()->nombre)->toBe('FERRETERÍA ÑANDÚ')
        ->and($cliente->direccion)->toBe('BARRIO EL CENTRO, SANTA ROSA DE COPÁN');
});

it('abre los datos de la venta si algo de ahí no pasa al cobrar', function (): void {
    $martillo = Producto::factory()->create(['nombre' => 'Martillo de uña']);

    Livewire::test(PuntoDeVenta::class)
        ->call('agregar', $martillo->id)
        ->set('venta.lista_precio', null)
        ->assertSet('datosAbiertos', false)
        ->call('cobrar')
        ->assertHasErrors()
        ->assertSet('datosAbiertos', true);
});
