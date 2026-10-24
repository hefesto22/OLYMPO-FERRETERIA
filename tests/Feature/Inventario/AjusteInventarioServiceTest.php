<?php

declare(strict_types=1);

use App\Domain\Exceptions\CantidadInvalidaException;
use App\Domain\Exceptions\StockInsuficienteException;
use App\Enums\EstadoDocumento;
use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
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

it('un conteo físico deja cada producto en lo contado', function (): void {
    $inventario = app(InventarioService::class);
    $clavo = Producto::factory()->create();
    $tubo = Producto::factory()->create();
    $cinta = Producto::factory()->create();
    $inventario->registrarEntrada($clavo, 30, 10.00, TipoMovimientoInventario::InventarioInicial);
    $inventario->registrarEntrada($tubo, 10, 40.00, TipoMovimientoInventario::InventarioInicial);
    $inventario->registrarEntrada($cinta, 7, 25.00, TipoMovimientoInventario::InventarioInicial);

    /** @var AjusteInventario $ajuste */
    $ajuste = AjusteInventario::factory()->conteo()->create();
    $ajuste->detalles()->create(['producto_id' => $clavo->id, 'contado' => 25]);
    $ajuste->detalles()->create(['producto_id' => $tubo->id, 'contado' => 14]);
    $ajuste->detalles()->create(['producto_id' => $cinta->id, 'contado' => 7]);

    app(AjusteInventarioService::class)->aplicar($ajuste);

    // Faltan 5 clavos, sobran 4 tubos (al costo promedio) y la cinta cuadra.
    expect((float) $clavo->refresh()->stock)->toBe(25.0)
        ->and((float) $tubo->refresh()->stock)->toBe(14.0)
        ->and((float) $tubo->costo_promedio)->toBe(40.0)
        ->and((float) $cinta->refresh()->stock)->toBe(7.0)
        ->and(MovimientoInventario::query()->where('tipo', TipoMovimientoInventario::AjusteSalida)->count())->toBe(1)
        ->and(MovimientoInventario::query()->where('tipo', TipoMovimientoInventario::AjusteEntrada)->count())->toBe(1);

    $lineas = $ajuste->refresh()->detalles->keyBy('producto_id');

    expect($lineas[$clavo->id]->tipo)->toBe(TipoAjuste::Salida)
        ->and((float) $lineas[$clavo->id]->cantidad)->toBe(5.0)
        ->and((float) $lineas[$clavo->id]->existencia_antes)->toBe(30.0)
        ->and($lineas[$tubo->id]->tipo)->toBe(TipoAjuste::Entrada)
        ->and($lineas[$cinta->id]->tipo)->toBeNull()
        ->and((float) $lineas[$cinta->id]->cantidad)->toBe(0.0);
});

it('el conteo se vuelve a sacar al aplicar, con la existencia de ese momento', function (): void {
    $inventario = app(InventarioService::class);
    $producto = Producto::factory()->create();
    $inventario->registrarEntrada($producto, 30, 10.00, TipoMovimientoInventario::InventarioInicial);

    /** @var AjusteInventario $ajuste */
    $ajuste = AjusteInventario::factory()->conteo()->create();
    $ajuste->detalles()->create(['producto_id' => $producto->id, 'contado' => 25]);

    // Mientras el conteo estaba en borrador se vendieron 2.
    $inventario->registrarSalida($producto, 2, TipoMovimientoInventario::SalidaVenta);

    app(AjusteInventarioService::class)->aplicar($ajuste);

    expect((float) $producto->refresh()->stock)->toBe(25.0)
        ->and((float) $ajuste->detalles()->sole()->cantidad)->toBe(3.0);
});

it('el motivo decide si suma o resta, y la fecha es la del día', function (): void {
    $ajuste = AjusteInventario::query()->create([
        'motivo' => MotivoAjuste::DanoMerma,
        'tipo'   => TipoAjuste::Entrada,
    ]);

    expect($ajuste->refresh()->tipo)->toBe(TipoAjuste::Salida)
        ->and($ajuste->fecha->isToday())->toBeTrue()
        ->and($ajuste->numero)->toStartWith('AJ-');
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

it('al aplicar una merma queda el costo con que salió y cuánto se perdió con ISV', function (): void {
    $producto = Producto::factory()->create();
    app(InventarioService::class)->registrarEntrada($producto, 10, 20.00, TipoMovimientoInventario::InventarioInicial);

    /** @var AjusteInventario $ajuste */
    $ajuste = AjusteInventario::factory()->salida()->create();
    $ajuste->detalles()->create(['producto_id' => $producto->id, 'cantidad' => 3]);

    // En borrador se valora al promedio de hoy: 3 × L 20 = L 60 + ISV L 9.
    expect($ajuste->detalles()->sole()->valorConIsv())->toBe(69.0);

    app(AjusteInventarioService::class)->aplicar($ajuste);

    // El promedio cambia después; la pérdida del ajuste no.
    app(InventarioService::class)->registrarEntrada($producto, 7, 40.00, TipoMovimientoInventario::Entrada);
    $linea = $ajuste->refresh()->detalles()->sole();

    expect((float) $linea->costo_unitario)->toBe(20.0)
        ->and($linea->valorConIsv())->toBe(69.0);
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

    $inventario->registrarEntrada($producto, 100, 12.80, TipoMovimientoInventario::Entrada);
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
