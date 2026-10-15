<?php

declare(strict_types=1);

use App\Domain\Exceptions\OperacionNoPermitidaException;
use App\Domain\Exceptions\StockInsuficienteException;
use App\Enums\EstadoDocumento;
use App\Enums\TipoMovimientoInventario;
use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use App\Services\Inventario\CompraService;
use App\Services\Inventario\InventarioService;

function compraBorradorCon(Producto $producto, float $cantidad, float $costo, ?ProductoPresentacion $presentacion = null): Compra
{
    /** @var Compra $compra */
    $compra = Compra::factory()->create();

    $compra->detalles()->create([
        'producto_id'              => $producto->id,
        'producto_presentacion_id' => $presentacion?->id,
        'cantidad'                 => $cantidad,
        'costo_unitario'           => $costo,
    ]);

    return $compra;
}

it('confirma una compra por cajas y suma el stock en unidad base', function (): void {
    $producto = Producto::factory()->create();
    $caja = $producto->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'nombre'           => 'Caja x100',
        'factor'           => 100,
    ]);

    $compra = compraBorradorCon($producto, 2, 350.00, $caja);

    app(CompraService::class)->confirmar($compra);

    $producto->refresh();
    $compra->refresh();

    expect((float) $producto->stock)->toBe(200.0)
        ->and((float) $producto->costo_promedio)->toBe(3.5)
        ->and((float) $producto->ultimo_costo)->toBe(3.5)
        ->and($compra->estado)->toBe(EstadoDocumento::Confirmado)
        ->and($compra->confirmada_at)->not->toBeNull()
        ->and((float) $compra->subtotal)->toBe(700.0)
        ->and((float) $compra->isv)->toBe(105.0)
        ->and((float) $compra->total)->toBe(805.0);

    $movimiento = MovimientoInventario::query()->sole();

    expect($movimiento->tipo)->toBe(TipoMovimientoInventario::EntradaCompra)
        ->and((float) $movimiento->cantidad)->toBe(200.0)
        ->and((float) $movimiento->stock_anterior)->toBe(0.0)
        ->and((float) $movimiento->stock_nuevo)->toBe(200.0)
        ->and($movimiento->documento_type)->toBe('compra')
        ->and($movimiento->documento_id)->toBe($compra->id);
});

it('recalcula el costo promedio ponderado con cada compra', function (): void {
    $producto = Producto::factory()->create();

    app(InventarioService::class)->registrarEntrada($producto, 10, 5.00, TipoMovimientoInventario::InventarioInicial);
    app(CompraService::class)->confirmar(compraBorradorCon($producto, 30, 7.00));

    $producto->refresh();

    // (10 × 5 + 30 × 7) / 40 = 6.50
    expect((float) $producto->stock)->toBe(40.0)
        ->and((float) $producto->costo_promedio)->toBe(6.5)
        ->and((float) $producto->ultimo_costo)->toBe(7.0);
});

it('no calcula ISV en productos exentos', function (): void {
    $producto = Producto::factory()->exento()->create();
    $compra = compraBorradorCon($producto, 4, 25.00);

    app(CompraService::class)->confirmar($compra);
    $compra->refresh();

    expect((float) $compra->subtotal)->toBe(100.0)
        ->and((float) $compra->isv)->toBe(0.0)
        ->and((float) $compra->total)->toBe(100.0);
});

it('no permite confirmar dos veces la misma compra', function (): void {
    $producto = Producto::factory()->create();
    $compra = compraBorradorCon($producto, 5, 10.00);

    app(CompraService::class)->confirmar($compra);

    expect(fn () => app(CompraService::class)->confirmar($compra))
        ->toThrow(OperacionNoPermitidaException::class);

    expect((float) $producto->refresh()->stock)->toBe(5.0);
});

it('no confirma una compra sin productos', function (): void {
    /** @var Compra $compra */
    $compra = Compra::factory()->create();

    expect(fn () => app(CompraService::class)->confirmar($compra))
        ->toThrow(OperacionNoPermitidaException::class);
});

it('anular una compra revierte el stock y el costo promedio', function (): void {
    $producto = Producto::factory()->create();
    app(InventarioService::class)->registrarEntrada($producto, 10, 5.00, TipoMovimientoInventario::InventarioInicial);

    $compra = compraBorradorCon($producto, 30, 7.00);
    app(CompraService::class)->confirmar($compra);
    app(CompraService::class)->anular($compra, 'Factura duplicada');

    $producto->refresh();
    $compra->refresh();

    expect((float) $producto->stock)->toBe(10.0)
        ->and((float) $producto->costo_promedio)->toBe(5.0)
        ->and($compra->estado)->toBe(EstadoDocumento::Anulado)
        ->and($compra->motivo_anulacion)->toBe('Factura duplicada')
        ->and(MovimientoInventario::query()->where('tipo', TipoMovimientoInventario::AnulacionCompra)->count())->toBe(1);
});

it('no anula una compra cuya mercadería ya salió, y no cambia nada', function (): void {
    $producto = Producto::factory()->create();
    $compra = compraBorradorCon($producto, 10, 5.00);
    app(CompraService::class)->confirmar($compra);

    app(InventarioService::class)->registrarSalida($producto, 8, TipoMovimientoInventario::AjusteSalida);

    expect(fn () => app(CompraService::class)->anular($compra, 'Error'))
        ->toThrow(StockInsuficienteException::class);

    expect((float) $producto->refresh()->stock)->toBe(2.0)
        ->and($compra->refresh()->estado)->toBe(EstadoDocumento::Confirmado);
});
