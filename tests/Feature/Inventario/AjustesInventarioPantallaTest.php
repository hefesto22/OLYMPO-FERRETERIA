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
use App\Support\Inventario\OpcionesProducto;
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

it('muestra cuánto falta en cada línea del conteo, con ISV', function (): void {
    test()->actingAs(usuarioDeAjustes());
    $clavo = productoConExistencia(30, 10.00);

    // Faltan 5 a L 10 sin ISV: L 50 + 15 % = L 57.50.
    Livewire::test(ManageAjustesInventario::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'motivo'   => MotivoAjuste::ConteoFisico->value,
            'detalles' => [
                ['producto_id' => $clavo->id, 'contado' => 25],
            ],
        ])
        ->assertMountedActionModalSee('Faltan 5')
        ->assertMountedActionModalSee('Pérdida de L. 57.50 con ISV')
        ->assertMountedActionModalSee('Diferencia con ISV')
        ->assertMountedActionModalSee('−L. 57.50');
});

it('una merma muestra la pérdida con el ISV que se pagó', function (): void {
    test()->actingAs(usuarioDeAjustes());
    $clavo = productoConExistencia(30, 10.00);

    // Salen 4 a L 10 sin ISV: L 40 + ISV L 6 = L 46.
    Livewire::test(ManageAjustesInventario::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'motivo'   => MotivoAjuste::DanoMerma->value,
            'detalles' => [
                ['producto_id' => $clavo->id, 'cantidad' => 4],
            ],
        ])
        ->assertMountedActionModalSee('Pérdida de L. 46.00 con ISV')
        ->assertMountedActionModalSee('Costo sin ISV')
        ->assertMountedActionModalSee('L. 6.00')
        ->assertMountedActionModalSee('Pérdida con ISV')
        ->assertMountedActionModalSee('−L. 46.00');
});

it('al cambiar qué pasó, el número escrito se queda en la casilla', function (): void {
    test()->actingAs(usuarioDeAjustes());
    $clavo = productoConExistencia(30, 10.00);

    Livewire::test(ManageAjustesInventario::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'motivo'   => MotivoAjuste::DanoMerma->value,
            'tipo'     => 'salida',
            'detalles' => [
                ['producto_id' => $clavo->id, 'cantidad' => 4],
            ],
        ])
        // De una salida a un conteo: los 4 pasan a lo contado.
        ->set('mountedActions.0.data.motivo', MotivoAjuste::ConteoFisico->value)
        ->assertSet('mountedActions.0.data.tipo', 'conteo')
        ->assertSet('mountedActions.0.data.detalles.0.contado', 4)
        // Y de vuelta: lo contado pasa a la cantidad.
        ->set('mountedActions.0.data.detalles.0.contado', 6)
        ->set('mountedActions.0.data.motivo', MotivoAjuste::Robo->value)
        ->assertSet('mountedActions.0.data.detalles.0.cantidad', 6)
        // "Otro" después de un conteo pregunta y arranca en "suma".
        ->set('mountedActions.0.data.motivo', MotivoAjuste::ConteoFisico->value)
        ->set('mountedActions.0.data.motivo', MotivoAjuste::Otro->value)
        ->assertSet('mountedActions.0.data.tipo', 'entrada');
});

it('el buscador de productos muestra código y existencia, y escapa el nombre', function (): void {
    /** @var Producto $producto */
    $producto = Producto::factory()->create(['nombre' => 'Cemento <b>gris</b>']);
    app(InventarioService::class)->registrarEntrada($producto, 168, 22.50, TipoMovimientoInventario::InventarioInicial);
    $producto->refresh();

    $opcion = OpcionesProducto::etiquetaHtml($producto);

    expect($opcion)->toContain('Cemento &lt;b&gt;gris&lt;/b&gt;')
        ->toContain((string) $producto->codigo)
        ->toContain('168 '.$producto->unidadMedida->abreviatura)
        ->toContain('en existencia');

    // El nombre va escapado: no se cuela HTML en el buscador.
    expect($opcion)->not->toContain('<b>gris</b>');
});
