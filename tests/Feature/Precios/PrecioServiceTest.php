<?php

declare(strict_types=1);

use App\Enums\EstadoCambioPrecio;
use App\Enums\TipoMargen;
use App\Models\CambioPrecio;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\User;
use App\Services\Inventario\CompraService;
use App\Services\Precios\PrecioService;
use BezhanSalleh\FilamentShield\Support\Utils as ShieldUtils;
use Spatie\Permission\Models\Role;

function comprarAlCosto(Producto $producto, float $cantidad, float $costo): void
{
    /** @var Compra $compra */
    $compra = Compra::factory()->create();

    $compra->detalles()->create([
        'producto_id'    => $producto->id,
        'cantidad'       => $cantidad,
        'costo_unitario' => $costo,
    ]);

    app(CompraService::class)->confirmar($compra);
}

/**
 * Producto con margen y su primera compra de 10 unidades al costo dado.
 */
function productoConMargen(float $costo, float $margenPublico = 30, ?float $margenMayoreo = 20, TipoMargen $tipo = TipoMargen::Porcentaje): Producto
{
    /** @var Producto $producto */
    $producto = Producto::factory()->create([
        'margen_tipo'    => $tipo,
        'margen_publico' => $margenPublico,
        'margen_mayoreo' => $margenMayoreo,
    ]);

    comprarAlCosto($producto, 10, $costo);

    return $producto->refresh();
}

function duenoDeLaFerreteria(): User
{
    /** @var User $dueno */
    $dueno = User::factory()->create(['is_active' => true]);
    $dueno->assignRole(Role::findOrCreate(ShieldUtils::getSuperAdminName(), 'web'));

    return $dueno;
}

it('fija el precio con el margen al registrar el primer costo', function (): void {
    $producto = productoConMargen(18.80);

    expect((float) $producto->precio_venta)->toBe(29.0)
        ->and((float) $producto->precio_mayoreo)->toBe(26.0)
        ->and((float) $producto->costo_precio)->toBe(18.8)
        ->and(CambioPrecio::query()->count())->toBe(0);
});

it('suma un margen en lempiras por unidad base', function (): void {
    $producto = productoConMargen(18.80, 5, 3, TipoMargen::Monto);

    // (18.80 + 5) × 1.15 = 27.37 → L 28; (18.80 + 3) × 1.15 = 25.07 → L 26.
    expect((float) $producto->precio_venta)->toBe(28.0)
        ->and((float) $producto->precio_mayoreo)->toBe(26.0);
});

it('sube el precio solo cuando sube el costo', function (): void {
    $dueno = duenoDeLaFerreteria();
    $producto = productoConMargen(18.80);

    // Promedio: (10 × 18.80 + 10 × 22.00) / 20 = 20.40.
    comprarAlCosto($producto, 10, 22.00);
    $producto->refresh();

    $cambio = CambioPrecio::query()->sole();

    expect((float) $producto->precio_venta)->toBe(31.0)
        ->and((float) $producto->precio_mayoreo)->toBe(29.0)
        ->and($cambio->estado)->toBe(EstadoCambioPrecio::Automatico)
        ->and((float) $cambio->precio_anterior)->toBe(29.0)
        ->and((float) $cambio->precio_nuevo)->toBe(31.0)
        ->and($cambio->origen)->toStartWith('Compra')
        ->and($dueno->notifications()->count())->toBe(0);
});

it('si baja el costo deja el precio igual y le avisa al dueño', function (): void {
    $dueno = duenoDeLaFerreteria();
    $producto = productoConMargen(18.80);

    // Promedio: (10 × 18.80 + 10 × 15.00) / 20 = 16.90.
    comprarAlCosto($producto, 10, 15.00);
    $producto->refresh();

    $cambio = CambioPrecio::query()->sole();

    expect((float) $producto->precio_venta)->toBe(29.0)
        ->and($cambio->estado)->toBe(EstadoCambioPrecio::Pendiente)
        ->and((float) $cambio->costo_nuevo)->toBe(16.9)
        ->and((float) $cambio->precio_nuevo)->toBe(26.0)
        ->and((float) $cambio->mayoreo_nuevo)->toBe(24.0)
        ->and($dueno->notifications()->count())->toBe(1);
});

it('baja el precio cuando el dueño lo aprueba', function (): void {
    $dueno = duenoDeLaFerreteria();
    $producto = productoConMargen(18.80);
    comprarAlCosto($producto, 10, 15.00);

    $cambio = app(PrecioService::class)->aprobar(CambioPrecio::query()->sole(), $dueno);
    $producto->refresh();

    expect((float) $producto->precio_venta)->toBe(26.0)
        ->and((float) $producto->precio_mayoreo)->toBe(24.0)
        ->and((float) $producto->costo_precio)->toBe(16.9)
        ->and($cambio->estado)->toBe(EstadoCambioPrecio::Aplicado)
        ->and($cambio->revisado_por)->toBe($dueno->id);
});

it('mantiene el precio si el dueño lo rechaza y no vuelve a preguntar por el mismo costo', function (): void {
    $dueno = duenoDeLaFerreteria();
    $producto = productoConMargen(18.80);
    comprarAlCosto($producto, 10, 15.00);

    app(PrecioService::class)->rechazar(CambioPrecio::query()->sole(), $dueno);
    app(PrecioService::class)->revisar([$producto->id], 'Otra revisión');

    expect((float) $producto->refresh()->precio_venta)->toBe(29.0)
        ->and(CambioPrecio::query()->pendientes()->count())->toBe(0)
        ->and($dueno->notifications()->count())->toBe(1);
});

it('vuelve a preguntar si después de rechazar el costo baja todavía más', function (): void {
    $dueno = duenoDeLaFerreteria();
    $producto = productoConMargen(18.80);
    comprarAlCosto($producto, 10, 15.00);
    app(PrecioService::class)->rechazar(CambioPrecio::query()->sole(), $dueno);

    // Promedio: (20 × 16.90 + 20 × 10.00) / 40 = 13.45 → 13.45 × 1.30 × 1.15 = 20.11 → L 21.
    comprarAlCosto($producto, 20, 10.00);

    $pendiente = CambioPrecio::query()->pendientes()->sole();

    expect((float) $pendiente->precio_nuevo)->toBe(21.0)
        ->and((float) $producto->refresh()->precio_venta)->toBe(29.0)
        ->and($dueno->notifications()->count())->toBe(2);
});

it('si el costo vuelve a subir, la baja que esperaba revisión queda sin efecto', function (): void {
    duenoDeLaFerreteria();
    $producto = productoConMargen(18.80);
    comprarAlCosto($producto, 10, 15.00);

    // Promedio: (20 × 16.90 + 40 × 25.00) / 60 = 22.30 → 22.30 × 1.30 × 1.15 = 33.34 → L 34.
    comprarAlCosto($producto, 40, 25.00);

    expect((float) $producto->refresh()->precio_venta)->toBe(34.0)
        ->and(CambioPrecio::query()->where('estado', EstadoCambioPrecio::Descartado)->count())->toBe(1)
        ->and(CambioPrecio::query()->where('estado', EstadoCambioPrecio::Automatico)->count())->toBe(1)
        ->and(CambioPrecio::query()->pendientes()->count())->toBe(0);
});

it('si se anula la compra que bajó el costo, la baja que esperaba revisión queda sin efecto', function (): void {
    duenoDeLaFerreteria();
    $producto = productoConMargen(18.80);

    /** @var Compra $compra */
    $compra = Compra::factory()->create();
    $compra->detalles()->create(['producto_id' => $producto->id, 'cantidad' => 10, 'costo_unitario' => 15.00]);
    app(CompraService::class)->confirmar($compra);

    expect(CambioPrecio::query()->pendientes()->count())->toBe(1);

    // Al anular, el promedio regresa a 18.80: ya no hay nada que bajar.
    app(CompraService::class)->anular($compra->refresh(), 'Error de captura');

    expect(CambioPrecio::query()->pendientes()->count())->toBe(0)
        ->and(CambioPrecio::query()->sole()->estado)->toBe(EstadoCambioPrecio::Descartado)
        ->and((float) $producto->refresh()->precio_venta)->toBe(29.0);
});

it('al cambiar el margen recalcula sobre el costo promedio de hoy y descarta lo pendiente', function (): void {
    duenoDeLaFerreteria();
    $producto = productoConMargen(18.80);
    comprarAlCosto($producto, 10, 15.00);

    $producto->refresh()->update(['margen_publico' => 25]);

    // 16.90 × 1.25 × 1.15 = 24.29 → L 25.
    expect((float) $producto->refresh()->precio_venta)->toBe(25.0)
        ->and((float) $producto->costo_precio)->toBe(16.9)
        ->and(CambioPrecio::query()->sole()->estado)->toBe(EstadoCambioPrecio::Descartado);
});

it('no toca los precios puestos a mano', function (): void {
    duenoDeLaFerreteria();

    /** @var Producto $producto */
    $producto = Producto::factory()->create(['precio_venta' => 50]);
    comprarAlCosto($producto, 10, 18.80);
    comprarAlCosto($producto, 10, 10.00);

    expect((float) $producto->refresh()->precio_venta)->toBe(50.0)
        ->and(CambioPrecio::query()->count())->toBe(0);
});
