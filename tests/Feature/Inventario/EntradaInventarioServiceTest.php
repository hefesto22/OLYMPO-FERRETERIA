<?php

declare(strict_types=1);

use App\Domain\Exceptions\OperacionNoPermitidaException;
use App\Domain\Exceptions\StockInsuficienteException;
use App\Enums\EstadoDocumento;
use App\Enums\TipoMovimientoInventario;
use App\Models\EntradaInventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Inventario\EntradaInventarioService;
use App\Services\Inventario\InventarioService;

function entradaBorradorCon(Producto $producto, float $cantidad, float $costo, ?ProductoPresentacion $presentacion = null): EntradaInventario
{
    /** @var EntradaInventario $entrada */
    $entrada = EntradaInventario::factory()->create();

    $entrada->detalles()->create([
        'producto_id'              => $producto->id,
        'producto_presentacion_id' => $presentacion?->id,
        'cantidad'                 => $cantidad,
        'costo_unitario'           => $costo,
    ]);

    return $entrada;
}

it('confirma una entrada por cajas y suma el stock en unidad base', function (): void {
    $producto = Producto::factory()->create();
    $caja = $producto->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'nombre'           => 'Caja x100',
        'factor'           => 100,
    ]);

    $entrada = entradaBorradorCon($producto, 2, 350.00, $caja);

    app(EntradaInventarioService::class)->confirmar($entrada);

    $producto->refresh();
    $entrada->refresh();

    expect((float) $producto->stock)->toBe(200.0)
        ->and((float) $producto->costo_promedio)->toBe(3.5)
        ->and((float) $producto->ultimo_costo)->toBe(3.5)
        ->and($entrada->estado)->toBe(EstadoDocumento::Confirmado)
        ->and($entrada->confirmada_at)->not->toBeNull()
        ->and((float) $entrada->subtotal)->toBe(700.0)
        ->and((float) $entrada->isv)->toBe(105.0)
        ->and((float) $entrada->total)->toBe(805.0);

    $movimiento = MovimientoInventario::query()->sole();

    expect($movimiento->tipo)->toBe(TipoMovimientoInventario::Entrada)
        ->and((float) $movimiento->cantidad)->toBe(200.0)
        ->and((float) $movimiento->stock_anterior)->toBe(0.0)
        ->and((float) $movimiento->stock_nuevo)->toBe(200.0)
        ->and($movimiento->documento_type)->toBe('entrada_inventario')
        ->and($movimiento->documento_id)->toBe($entrada->id);
});

it('recalcula el costo promedio ponderado con cada entrada', function (): void {
    $producto = Producto::factory()->create();

    app(InventarioService::class)->registrarEntrada($producto, 10, 5.00, TipoMovimientoInventario::InventarioInicial);
    app(EntradaInventarioService::class)->confirmar(entradaBorradorCon($producto, 30, 7.00));

    $producto->refresh();

    // (10 × 5 + 30 × 7) / 40 = 6.50
    expect((float) $producto->stock)->toBe(40.0)
        ->and((float) $producto->costo_promedio)->toBe(6.5)
        ->and((float) $producto->ultimo_costo)->toBe(7.0);
});

it('no calcula ISV en productos exentos', function (): void {
    $producto = Producto::factory()->exento()->create();
    $entrada = entradaBorradorCon($producto, 4, 25.00);

    app(EntradaInventarioService::class)->confirmar($entrada);
    $entrada->refresh();

    expect((float) $entrada->subtotal)->toBe(100.0)
        ->and((float) $entrada->isv)->toBe(0.0)
        ->and((float) $entrada->total)->toBe(100.0);
});

it('no permite confirmar dos veces la misma entrada', function (): void {
    $producto = Producto::factory()->create();
    $entrada = entradaBorradorCon($producto, 5, 10.00);

    app(EntradaInventarioService::class)->confirmar($entrada);

    expect(fn () => app(EntradaInventarioService::class)->confirmar($entrada))
        ->toThrow(OperacionNoPermitidaException::class);

    expect((float) $producto->refresh()->stock)->toBe(5.0);
});

it('no confirma una entrada sin productos', function (): void {
    /** @var EntradaInventario $entrada */
    $entrada = EntradaInventario::factory()->create();

    expect(fn () => app(EntradaInventarioService::class)->confirmar($entrada))
        ->toThrow(OperacionNoPermitidaException::class);
});

it('anular una entrada revierte el stock y el costo promedio', function (): void {
    $producto = Producto::factory()->create();
    app(InventarioService::class)->registrarEntrada($producto, 10, 5.00, TipoMovimientoInventario::InventarioInicial);

    $entrada = entradaBorradorCon($producto, 30, 7.00);
    app(EntradaInventarioService::class)->confirmar($entrada);
    app(EntradaInventarioService::class)->anular($entrada, 'Factura duplicada');

    $producto->refresh();
    $entrada->refresh();

    expect((float) $producto->stock)->toBe(10.0)
        ->and((float) $producto->costo_promedio)->toBe(5.0)
        ->and($entrada->estado)->toBe(EstadoDocumento::Anulado)
        ->and($entrada->motivo_anulacion)->toBe('Factura duplicada')
        ->and(MovimientoInventario::query()->where('tipo', TipoMovimientoInventario::AnulacionEntrada)->count())->toBe(1);
});

it('no anula una entrada cuya mercadería ya salió, y no cambia nada', function (): void {
    $producto = Producto::factory()->create();
    $entrada = entradaBorradorCon($producto, 10, 5.00);
    app(EntradaInventarioService::class)->confirmar($entrada);

    app(InventarioService::class)->registrarSalida($producto, 8, TipoMovimientoInventario::AjusteSalida);

    expect(fn () => app(EntradaInventarioService::class)->anular($entrada, 'Error'))
        ->toThrow(StockInsuficienteException::class);

    expect((float) $producto->refresh()->stock)->toBe(2.0)
        ->and($entrada->refresh()->estado)->toBe(EstadoDocumento::Confirmado);
});

it('numera con E, toma la fecha del día y guarda quién la registró', function (): void {
    /** @var User $usuario */
    $usuario = User::factory()->create();
    test()->actingAs($usuario);
    $producto = Producto::factory()->create();

    // Sin proveedor, sin referencia y sin fecha: lo demás lo pone el sistema.
    $entrada = EntradaInventario::query()->create([]);
    $entrada->detalles()->create(['producto_id' => $producto->id, 'cantidad' => 3, 'costo_unitario' => 10]);

    app(EntradaInventarioService::class)->confirmar($entrada);
    $entrada->refresh();

    expect($entrada->numero)->toStartWith('E-')
        ->and($entrada->fecha->isToday())->toBeTrue()
        ->and($entrada->created_by)->toBe($usuario->id)
        ->and((float) $producto->refresh()->stock)->toBe(3.0)
        ->and(MovimientoInventario::query()->sole()->descripcion)->toBe("Entrada {$entrada->numero}");
});
