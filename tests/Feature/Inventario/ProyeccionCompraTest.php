<?php

declare(strict_types=1);

use App\Enums\TipoMargen;
use App\Filament\Resources\Compras\Pages\CreateCompra;
use App\Filament\Resources\Productos\Pages\EditProducto;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Inventario\CompraService;
use App\Support\Inventario\ProyeccionCompra;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
| Al capturar una compra, cada línea muestra cómo queda el producto si se
| confirma: lo que hay en existencia + lo que entra = el costo ponderado
| nuevo, y el precio de venta que resulta con la regla de siempre (si el
| costo sube, el precio sube solo; si baja, se queda y decide el dueño).
*/

/**
 * Producto con su primera compra confirmada (fija el costo y el precio).
 *
 * @param array<string, mixed> $atributos
 */
function productoConPrimeraCompra(float $cantidad, float $costo, array $atributos = []): Producto
{
    /** @var Producto $producto */
    $producto = Producto::factory()->create([
        'margen_tipo'    => TipoMargen::Porcentaje,
        'margen_publico' => 30,
        'margen_mayoreo' => null,
        ...$atributos,
    ]);

    /** @var Compra $compra */
    $compra = Compra::factory()->create();
    $compra->detalles()->create(['producto_id' => $producto->id, 'cantidad' => $cantidad, 'costo_unitario' => $costo]);
    app(CompraService::class)->confirmar($compra);

    return $producto->refresh();
}

/**
 * @param list<string> $permisos
 */
function usuarioConPermisosDeInventario(array $permisos): User
{
    $rol = Role::findOrCreate('inventario-'.md5(implode(',', $permisos)), 'web');

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

it('pondera el costo con lo que hay en existencia y sube el precio', function (): void {
    $clavo = productoConPrimeraCompra(10, 18.80);

    expect((float) $clavo->precio_venta)->toBe(29.0);

    $linea = ProyeccionCompra::desdeFormulario([
        'a' => ['producto_id' => $clavo->id, 'producto_presentacion_id' => null, 'cantidad' => 10, 'costo_unitario' => 25],
    ])['a'];

    // (10 × 18.80 + 10 × 25) / 20 = 21.90 → 21.90 × 1.30 × 1.15 = 32.74 → L 33
    expect($linea['existencia'])->toBe(10.0)
        ->and($linea['costo_antes'])->toBe(18.8)
        ->and($linea['entra'])->toBe(10.0)
        ->and($linea['costo_nuevo'])->toBe(21.9)
        ->and($linea['precio_antes'])->toBe(29.0)
        ->and($linea['precio_nuevo'])->toBe(33.0)
        ->and($linea['estado'])->toBe(ProyeccionCompra::SUBE);
});

it('si el costo baja, el precio se queda y lo decide el dueño', function (): void {
    $clavo = productoConPrimeraCompra(10, 18.80);

    $linea = ProyeccionCompra::desdeFormulario([
        'a' => ['producto_id' => $clavo->id, 'cantidad' => 10, 'costo_unitario' => 15],
    ])['a'];

    // (188 + 150) / 20 = 16.90 → bajaría a L 26, pero se queda en L 29.
    expect($linea['costo_nuevo'])->toBe(16.9)
        ->and($linea['precio_nuevo'])->toBe(29.0)
        ->and($linea['precio_propuesto'])->toBe(26.0)
        ->and($linea['estado'])->toBe(ProyeccionCompra::BAJA);
});

it('acumula las líneas del mismo producto y pasa las presentaciones a la unidad base', function (): void {
    $clavo = productoConPrimeraCompra(10, 18.80);
    $caja = $clavo->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'nombre'           => 'Caja 50 lb',
        'factor'           => 50,
    ]);

    $lineas = ProyeccionCompra::desdeFormulario([
        'caja'  => ['producto_id' => $clavo->id, 'producto_presentacion_id' => $caja->id, 'cantidad' => 1, 'costo_unitario' => 1000],
        'vacia' => ['producto_id' => null, 'cantidad' => 1, 'costo_unitario' => 5],
        'libra' => ['producto_id' => (string) $clavo->id, 'producto_presentacion_id' => '', 'cantidad' => '50', 'costo_unitario' => '22'],
    ]);

    // Caja: 10 LB a 18.80 + 50 LB a 20.00 = 19.80. Libras: 60 LB a 19.80 + 50 LB a 22.00 = 20.80.
    expect($lineas)->not->toHaveKey('vacia');

    expect($lineas)->toHaveKeys(['caja', 'libra'])
        ->and($lineas['caja']['entra'])->toBe(50.0)
        ->and($lineas['caja']['costo_entrada'])->toBe(20.0)
        ->and($lineas['caja']['costo_nuevo'])->toBe(19.8)
        ->and($lineas['libra']['existencia'])->toBe(60.0)
        ->and($lineas['libra']['costo_antes'])->toBe(19.8)
        ->and($lineas['libra']['costo_nuevo'])->toBe(20.8);
});

it('no mueve el precio a mano', function (): void {
    $alambre = productoConPrimeraCompra(10, 20, ['margen_tipo' => TipoMargen::Manual, 'margen_publico' => null, 'precio_venta' => 50]);

    $linea = ProyeccionCompra::desdeFormulario([
        'a' => ['producto_id' => $alambre->id, 'cantidad' => 10, 'costo_unitario' => 30],
    ])['a'];

    expect($linea['costo_nuevo'])->toBe(25.0)
        ->and($linea['precio_nuevo'])->toBe(50.0)
        ->and($linea['estado'])->toBe(ProyeccionCompra::MANUAL);
});

it('muestra los costos con 4 decimales solo cuando los necesitan', function (): void {
    expect(costoLempiras(22.5))->toBe('L. 22.50')
        ->and(costoLempiras(25.2985))->toBe('L. 25.2985')
        ->and(costoLempiras(0.375))->toBe('L. 0.3750');
});

it('dibuja la compra con lo que va a quedar en cada línea', function (): void {
    $clavo = productoConPrimeraCompra(10, 18.80);
    test()->actingAs(usuarioConPermisosDeInventario(['ViewAny:Compra', 'Create:Compra']));

    Livewire::test(CreateCompra::class)
        ->fillForm([
            'detalles' => [
                'linea' => ['producto_id' => $clavo->id, 'producto_presentacion_id' => null, 'cantidad' => 10, 'costo_unitario' => 25],
            ],
        ])
        ->assertSee('Al confirmar')
        ->assertSee('Costo')
        ->assertSee('L. 21.90')
        ->assertSee('Sube de L. 29.00');
});

it('dibuja la tarjeta del precio y una línea por presentación', function (): void {
    $clavo = productoConPrimeraCompra(10, 18.80);
    $clavo->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'nombre'           => 'Caja 50 lb',
        'factor'           => 50,
        'margen'           => 15,
    ]);
    test()->actingAs(usuarioConPermisosDeInventario(['ViewAny:Producto', 'View:Producto', 'Update:Producto']));

    Livewire::test(EditProducto::class, ['record' => $clavo->getRouteKey()])
        ->assertSee('Costo ponderado')
        ->assertSee('L. 18.80')
        ->assertSee('Al lempira hacia arriba')
        ->assertSee('L. 29.00')
        ->assertSee('Se ve como «Caja 50 lb»')
        ->assertSee('Margen propio: 15 %');
});
