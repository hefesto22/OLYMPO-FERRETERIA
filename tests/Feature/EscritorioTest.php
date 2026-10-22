<?php

declare(strict_types=1);

use App\Enums\TipoMovimientoInventario;
use App\Filament\Pages\Escritorio;
use App\Filament\Widgets\Pendientes;
use App\Filament\Widgets\ResumenVentas;
use App\Filament\Widgets\UltimasVentas;
use App\Filament\Widgets\VentasPorDia;
use App\Models\Caja;
use App\Models\Producto;
use App\Models\User;
use App\Services\Inventario\InventarioService;
use App\Services\Ventas\Datos\DatosVenta;
use App\Services\Ventas\Datos\LineaVenta;
use App\Services\Ventas\TurnoCajaService;
use App\Services\Ventas\VentaService;
use App\Support\Ventas\ResumenDeVentas;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
| Escritorio: los números del día, la gráfica, lo pendiente y las últimas
| ventas. Cada widget se muestra solo a quien tiene el permiso.
*/

/**
 * @param list<string> $permisos
 */
function usuarioConPermisos(array $permisos): User
{
    $rol = Role::findOrCreate('escritorio-'.md5(implode(',', $permisos)), 'web');

    foreach ($permisos as $permiso) {
        $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
    }

    /** @var User $usuario */
    $usuario = User::factory()->create(['name' => 'Mauricio Cruz']);
    $usuario->assignRole($rol);

    return $usuario;
}

function venderMartillo(User $cajero, int $cantidad = 2): void
{
    /** @var Caja $caja */
    $caja = Caja::factory()->create();
    app(TurnoCajaService::class)->abrir($caja, $cajero, 0);

    /** @var Producto $martillo */
    $martillo = Producto::factory()->create(['nombre' => 'Martillo de uña', 'precio_venta' => 115]);
    app(InventarioService::class)->registrarEntrada($martillo, 10, 50.00, TipoMovimientoInventario::InventarioInicial);

    app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($martillo->id, $cantidad)]), $cajero);
}

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('saluda por el nombre y ofrece abrir el punto de venta a quien puede vender', function (): void {
    test()->actingAs(usuarioConPermisos(['View:PuntoDeVenta']));

    Livewire::test(Escritorio::class)
        ->assertSee('Mauricio')
        ->assertSee('Abrir punto de venta');
});

it('muestra las ventas solo a quien puede verlas', function (): void {
    test()->actingAs(usuarioConPermisos(['View:PuntoDeVenta']));

    expect(ResumenVentas::canView())->toBeFalse()
        ->and(VentasPorDia::canView())->toBeFalse()
        ->and(UltimasVentas::canView())->toBeFalse()
        ->and(Pendientes::canView())->toBeFalse();

    test()->actingAs(usuarioConPermisos(['ViewAny:Venta']));

    expect(ResumenVentas::canView())->toBeTrue()
        ->and(VentasPorDia::canView())->toBeTrue()
        ->and(UltimasVentas::canView())->toBeTrue();
});

it('suma las ventas de hoy y lista las últimas', function (): void {
    $dueno = usuarioConPermisos(['ViewAny:Venta', 'ViewAny:Producto']);
    venderMartillo($dueno);
    test()->actingAs($dueno);

    Livewire::test(ResumenVentas::class)
        ->assertSee('Ventas de hoy')
        ->assertSee('L. 230.00')
        ->assertSee('1 venta');

    Livewire::test(UltimasVentas::class)
        ->assertSee('REC-000001')
        ->assertSee('L. 230.00');

    Livewire::test(VentasPorDia::class)
        ->assertSee('Ventas de los últimos 14 días');
});

it('cuenta los días sin ventas en cero', function (): void {
    $dueno = usuarioConPermisos(['ViewAny:Venta']);
    venderMartillo($dueno, 1);

    $hoy = CarbonImmutable::now();
    $dias = ResumenDeVentas::porDia($hoy->subDays(6), $hoy);

    expect($dias)->toHaveCount(7)
        ->and($dias[$hoy->format('Y-m-d')])->toBe(['total' => 115.0, 'cantidad' => 1])
        ->and($dias[$hoy->subDay()->format('Y-m-d')])->toBe(['total' => 0.0, 'cantidad' => 0])
        ->and(ResumenDeVentas::variacion(150.0, 100.0))->toBe(50.0)
        ->and(ResumenDeVentas::variacion(150.0, 0.0))->toBeNull();
});

it('lista los productos por pedir en los pendientes', function (): void {
    test()->actingAs(usuarioConPermisos(['ViewAny:Producto']));
    Producto::factory()->create(['nombre' => 'Cinta métrica 5 m', 'stock_minimo' => 5]);

    Livewire::test(Pendientes::class)
        ->assertSee('Productos por pedir')
        ->assertSee('Cinta métrica 5 m')
        ->assertDontSee('Precios por revisar');
});
