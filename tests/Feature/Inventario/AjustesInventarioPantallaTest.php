<?php

declare(strict_types=1);

use App\Enums\EstadoDocumento;
use App\Enums\MotivoAjuste;
use App\Enums\TipoMovimientoInventario;
use App\Filament\Resources\AjustesInventario\Pages\ManageAjustesInventario;
use App\Models\AjusteInventario;
use App\Models\Producto;
use App\Models\User;
use App\Services\Inventario\InventarioService;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
| El ajuste se hace en un modal: el motivo dice si suma, resta o deja lo
| contado; "Aplicar ajuste" lo guarda y lo aplica de una vez.
*/

/**
 * @param list<string> $permisos
 */
function usuarioDeAjustes(array $permisos = ['ViewAny:AjusteInventario', 'Create:AjusteInventario', 'Update:AjusteInventario', 'Aplicar:AjusteInventario']): User
{
    $rol = Role::findOrCreate('ajustes-'.md5(implode(',', $permisos)), 'web');

    foreach ($permisos as $permiso) {
        $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
    }

    /** @var User $usuario */
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

function productoConExistencia(float $cantidad, float $costo): Producto
{
    /** @var Producto $producto */
    $producto = Producto::factory()->create();
    app(InventarioService::class)->registrarEntrada($producto, $cantidad, $costo, TipoMovimientoInventario::InventarioInicial);

    return $producto->refresh();
}

/**
 * Guarda cómo deshacer el Repeater::fake() de la prueba en curso (las
 * líneas quedan con llaves 0, 1… en vez de uuid) y lo entrega una vez.
 */
function deshacerRepeaterFalso(?Closure $deshacer = null): ?Closure
{
    static $guardado = null;

    if ($deshacer !== null) {
        $guardado = $deshacer;

        return null;
    }

    $actual = $guardado;
    $guardado = null;

    return $actual;
}

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    deshacerRepeaterFalso(Repeater::fake());
});

afterEach(function (): void {
    $deshacer = deshacerRepeaterFalso();

    if ($deshacer !== null) {
        $deshacer();
    }
});

it('aplica un conteo físico desde el modal: queda lo contado', function (): void {
    test()->actingAs(usuarioDeAjustes());
    $clavo = productoConExistencia(30, 10.00);

    Livewire::test(ManageAjustesInventario::class)
        ->callAction(CreateAction::class, data: [
            'motivo'   => MotivoAjuste::ConteoFisico->value,
            'detalles' => [
                ['producto_id' => $clavo->id, 'contado' => 25],
            ],
        ])
        ->assertHasNoFormErrors();

    $ajuste = AjusteInventario::query()->sole();

    expect($ajuste->estado)->toBe(EstadoDocumento::Confirmado)
        ->and($ajuste->fecha->isToday())->toBeTrue()
        ->and((float) $clavo->refresh()->stock)->toBe(25.0);
});

it('guarda como borrador sin mover el inventario', function (): void {
    test()->actingAs(usuarioDeAjustes());
    $clavo = productoConExistencia(30, 10.00);

    Livewire::test(ManageAjustesInventario::class)
        ->callAction(CreateAction::class, data: [
            'motivo'   => MotivoAjuste::DanoMerma->value,
            'detalles' => [
                ['producto_id' => $clavo->id, 'cantidad' => 4],
            ],
        ], arguments: ['borrador' => true])
        ->assertHasNoFormErrors();

    expect(AjusteInventario::query()->sole()->estado)->toBe(EstadoDocumento::Borrador)
        ->and((float) $clavo->refresh()->stock)->toBe(30.0);
});

it('no deja sacar más de lo que hay', function (): void {
    test()->actingAs(usuarioDeAjustes());
    $clavo = productoConExistencia(3, 10.00);

    Livewire::test(ManageAjustesInventario::class)
        ->callAction(CreateAction::class, data: [
            'motivo'   => MotivoAjuste::Robo->value,
            'detalles' => [
                ['producto_id' => $clavo->id, 'cantidad' => 5],
            ],
        ])
        ->assertHasFormErrors(['detalles.0.cantidad']);

    expect(AjusteInventario::query()->count())->toBe(0)
        ->and((float) $clavo->refresh()->stock)->toBe(3.0);
});

it('muestra cuánto falta en cada línea del conteo', function (): void {
    test()->actingAs(usuarioDeAjustes());
    $clavo = productoConExistencia(30, 10.00);

    Livewire::test(ManageAjustesInventario::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'motivo'   => MotivoAjuste::ConteoFisico->value,
            'detalles' => [
                ['producto_id' => $clavo->id, 'contado' => 25],
            ],
        ])
        ->assertMountedActionModalSee('Faltan 5')
        ->assertMountedActionModalSee('L. 50.00 al costo')
        ->assertMountedActionModalSee('Diferencia al costo');
});
