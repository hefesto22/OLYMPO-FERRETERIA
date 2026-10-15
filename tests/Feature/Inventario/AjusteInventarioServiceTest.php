<?php

declare(strict_types=1);

use App\Domain\Exceptions\CantidadInvalidaException;
use App\Domain\Exceptions\StockInsuficienteException;
use App\Enums\EstadoDocumento;
use App\Enums\TipoMovimientoInventario;
use App\Models\AjusteInventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Services\Inventario\AjusteInventarioService;
use App\Services\Inventario\InventarioService;

it('aplica un inventario inicial con su costo', function (): void {
    $producto = Producto::factory()->create();

    /** @var AjusteInventario $ajuste */
    $ajuste = AjusteInventario::factory()->inventarioInicial()->create();
    $ajuste->detalles()->create([
        'producto_id'    => $producto->id,
        'cantidad'       => 50,
        'costo_unitario' => 12.5,
    ]);

    app(AjusteInventarioService::class)->aplicar($ajuste);

    $producto->refresh();
    $ajuste->refresh();

    expect((float) $producto->stock)->toBe(50.0)
        ->and((float) $producto->costo_promedio)->toBe(12.5)
        ->and($ajuste->estado)->toBe(EstadoDocumento::Confirmado)
        ->and($ajuste->aplicado_at)->not->toBeNull()
        ->and(MovimientoInventario::query()->sole()->tipo)->toBe(TipoMovimientoInventario::InventarioInicial);
});

it('una salida mayor que la existencia lanza excepción y no mueve nada', function (): void {
    $producto = Producto::factory()->create();
    app(InventarioService::class)->registrarEntrada($producto, 5, 10.00, TipoMovimientoInventario::InventarioInicial);

    /** @var AjusteInventario $ajuste */
    $ajuste = AjusteInventario::factory()->salida()->create();
    $ajuste->detalles()->create([
        'producto_id' => $producto->id,
        'cantidad'    => 8,
    ]);

    expect(fn () => app(AjusteInventarioService::class)->aplicar($ajuste))
        ->toThrow(StockInsuficienteException::class);

    expect((float) $producto->refresh()->stock)->toBe(5.0)
        ->and($ajuste->refresh()->estado)->toBe(EstadoDocumento::Borrador)
        ->and(MovimientoInventario::query()->count())->toBe(1);
});

it('una salida conserva el costo promedio', function (): void {
    $producto = Producto::factory()->create();
    $inventario = app(InventarioService::class);

    $inventario->registrarEntrada($producto, 10, 4.00, TipoMovimientoInventario::InventarioInicial);
    $inventario->registrarSalida($producto, 3, TipoMovimientoInventario::AjusteSalida);

    $producto->refresh();

    expect((float) $producto->stock)->toBe(7.0)
        ->and((float) $producto->costo_promedio)->toBe(4.0);
});

it('maneja fracciones de la unidad base (metros, libras)', function (): void {
    $producto = Producto::factory()->create();
    $inventario = app(InventarioService::class);

    $inventario->registrarEntrada($producto, 100, 12.80, TipoMovimientoInventario::EntradaCompra);
    $inventario->registrarSalida($producto, 12.5, TipoMovimientoInventario::AjusteSalida);

    expect((float) $producto->refresh()->stock)->toBe(87.5);
});

it('los servicios no generan movimientos de kardex', function (): void {
    $servicio = Producto::factory()->servicio()->create();

    $movimiento = app(InventarioService::class)
        ->registrarEntrada($servicio, 1, 0, TipoMovimientoInventario::AjusteEntrada);

    expect($movimiento)->toBeNull()
        ->and((float) $servicio->refresh()->stock)->toBe(0.0)
        ->and(MovimientoInventario::query()->count())->toBe(0);
});

it('permite stock negativo solo si la configuración lo autoriza', function (): void {
    config(['ferreteria.inventario.permitir_stock_negativo' => true]);
    $producto = Producto::factory()->create();

    app(InventarioService::class)->registrarSalida($producto, 3, TipoMovimientoInventario::AjusteSalida);

    expect((float) $producto->refresh()->stock)->toBe(-3.0);
});

it('rechaza cantidades en cero o negativas', function (): void {
    $producto = Producto::factory()->create();

    expect(fn () => app(InventarioService::class)->registrarEntrada($producto, 0, 5.00, TipoMovimientoInventario::AjusteEntrada))
        ->toThrow(CantidadInvalidaException::class);
});

it('genera el código del producto cuando se deja vacío', function (): void {
    $producto = Producto::factory()->create(['codigo' => null]);

    expect($producto->refresh()->codigo)->toBe('P-'.str_pad((string) $producto->id, 6, '0', STR_PAD_LEFT));
});
