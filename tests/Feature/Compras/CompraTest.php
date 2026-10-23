<?php

declare(strict_types=1);

use App\Enums\CategoriaGasto;
use App\Enums\TipoDocumentoCompra;
use App\Filament\Resources\Compras\Pages\ManageCompras;
use App\Filament\Resources\Compras\Widgets\ResumenComprasMes;
use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Proveedor;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
| Compras guarda la factura o el recibo del proveedor solo con sus
| totales: lo que se gastó y el ISV que se descuenta de las ventas. No
| toca el inventario; los productos entran en Entradas de inventario.
*/

/**
 * @param list<string> $permisos
 */
function usuarioDeCompras(array $permisos = ['ViewAny:Compra', 'Create:Compra', 'Update:Compra']): User
{
    $rol = Role::findOrCreate('compras-'.md5(implode(',', $permisos)), 'web');

    foreach ($permisos as $permiso) {
        $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
    }

    /** @var User $usuario */
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('suma el ISV y el total de una factura con gravado al 15 %, al 18 % y exento', function (): void {
    /** @var Compra $compra */
    $compra = Compra::factory()->create([
        'gravado_15' => 1000,
        'isv_15'     => 150,
        'gravado_18' => 200,
        'isv_18'     => 36,
        'exento'     => 50,
    ]);

    expect((float) $compra->isv)->toBe(186.0)
        ->and((float) $compra->total)->toBe(1436.0)
        ->and($compra->numero)->toStartWith('C-')
        ->and($compra->isvAcreditable())->toBe(186.0)
        ->and(MovimientoInventario::query()->count())->toBe(0);
});

it('un recibo solo guarda lo pagado y no descuenta ISV', function (): void {
    /** @var Compra $compra */
    $compra = Compra::factory()->recibo(450)->create(['gravado_15' => 100, 'isv_15' => 15]);

    expect((float) $compra->gravado_15)->toBe(0.0)
        ->and((float) $compra->isv)->toBe(0.0)
        ->and((float) $compra->total)->toBe(450.0)
        ->and($compra->isvAcreditable())->toBe(0.0);
});

it('sin el RTN del proveedor el ISV de la factura no se descuenta', function (): void {
    /** @var Compra $compra */
    $compra = Compra::factory()->create(['rtn_proveedor' => null]);

    expect((float) $compra->isv)->toBe(150.0)
        ->and($compra->isvAcreditable())->toBe(0.0);
});

it('le guarda el RTN al proveedor que no lo tenía, sin cambiar uno que ya tiene', function (): void {
    $sinRtn = Proveedor::factory()->create(['rtn' => null]);
    $conRtn = Proveedor::factory()->create(['rtn' => '08019999000011']);

    Compra::factory()->create(['proveedor_id' => $sinRtn->id, 'rtn_proveedor' => '08011999123456'])->guardarRtnEnProveedor();
    Compra::factory()->create(['proveedor_id' => $conRtn->id, 'rtn_proveedor' => '08011999654321'])->guardarRtnEnProveedor();

    expect($sinRtn->refresh()->rtn)->toBe('08011999123456')
        ->and($conRtn->refresh()->rtn)->toBe('08019999000011');
});

it('registra una factura en el modal: trae el RTN del proveedor, calcula el ISV y suma el total', function (): void {
    test()->actingAs(usuarioDeCompras());
    $proveedor = Proveedor::factory()->create(['rtn' => '08011999123456']);

    Livewire::test(ManageCompras::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'tipo_documento' => TipoDocumentoCompra::Factura->value,
            'fecha'          => now()->toDateString(),
            'numero_factura' => '000-001-01-00001234',
            'categoria'      => CategoriaGasto::Mercaderia->value,
        ])
        ->set('mountedActions.0.data.proveedor_id', $proveedor->id)
        ->assertSet('mountedActions.0.data.rtn_proveedor', '08011999123456')
        ->set('mountedActions.0.data.gravado_15', '1000')
        ->assertSet('mountedActions.0.data.isv_15', 150.0)
        ->assertMountedActionModalSee('L. 1,150.00')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $compra = Compra::query()->sole();

    expect($compra->tipo_documento)->toBe(TipoDocumentoCompra::Factura)
        ->and((float) $compra->gravado_15)->toBe(1000.0)
        ->and((float) $compra->isv)->toBe(150.0)
        ->and((float) $compra->total)->toBe(1150.0)
        ->and($compra->rtn_proveedor)->toBe('08011999123456');
});

it('registra un recibo solo con lo pagado, sin pedir proveedor ni número', function (): void {
    test()->actingAs(usuarioDeCompras());

    // Otro recibo sin número ni proveedor no cuenta como repetido.
    Compra::factory()->recibo(80)->create(['proveedor_id' => null]);

    Livewire::test(ManageCompras::class)
        ->callAction(CreateAction::class, data: [
            'tipo_documento' => TipoDocumentoCompra::Recibo->value,
            'fecha'          => now()->toDateString(),
            'categoria'      => CategoriaGasto::Transporte->value,
            'exento'         => 350,
        ])
        ->assertHasNoFormErrors();

    /** @var Compra $compra */
    $compra = Compra::query()->latest('id')->firstOrFail();

    expect($compra->tipo_documento)->toBe(TipoDocumentoCompra::Recibo)
        ->and($compra->proveedor_id)->toBeNull()
        ->and((float) $compra->total)->toBe(350.0)
        ->and((float) $compra->isv)->toBe(0.0);
});

it('no deja registrar dos veces la misma factura del mismo proveedor', function (): void {
    test()->actingAs(usuarioDeCompras());
    $existente = Compra::factory()->create(['numero_factura' => '000-001-01-00000077']);

    Livewire::test(ManageCompras::class)
        ->callAction(CreateAction::class, data: [
            'tipo_documento' => TipoDocumentoCompra::Factura->value,
            'fecha'          => now()->toDateString(),
            'numero_factura' => '000-001-01-00000077',
            'categoria'      => CategoriaGasto::Mercaderia->value,
            'proveedor_id'   => $existente->proveedor_id,
            'gravado_15'     => 100,
            'isv_15'         => 15,
        ])
        ->assertHasFormErrors(['numero_factura']);

    expect(Compra::query()->count())->toBe(1);
});

it('muestra el total del mes, el ISV que se descuenta y las facturas sin RTN', function (): void {
    test()->actingAs(usuarioDeCompras());

    Compra::factory()->create(['gravado_15' => 1000, 'isv_15' => 150]);
    Compra::factory()->create(['rtn_proveedor' => null, 'gravado_15' => 200, 'isv_15' => 30]);
    Compra::factory()->recibo(100)->create();

    // 1,150 + 230 + 100 = 1,480; solo la factura con RTN descuenta sus L 150.
    Livewire::test(ResumenComprasMes::class)
        ->assertSee('L. 1,480.00')
        ->assertSee('L. 150.00')
        ->assertSee('Su ISV no se puede descontar');

    Livewire::test(ManageCompras::class)
        ->assertCanSeeTableRecords(Compra::all())
        ->assertSee('Sin RTN: su ISV no se descuenta');
});
