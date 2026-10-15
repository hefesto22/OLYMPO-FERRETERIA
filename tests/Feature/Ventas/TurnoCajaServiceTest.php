<?php

declare(strict_types=1);

use App\Domain\Exceptions\TurnoCajaException;
use App\Enums\EstadoTurno;
use App\Enums\TipoMovimientoCaja;
use App\Enums\TipoMovimientoInventario;
use App\Models\Caja;
use App\Models\Producto;
use App\Models\User;
use App\Services\Inventario\InventarioService;
use App\Services\Ventas\Datos\DatosVenta;
use App\Services\Ventas\Datos\LineaVenta;
use App\Services\Ventas\TurnoCajaService;
use App\Services\Ventas\VentaService;

function productoParaTurno(float $precio): Producto
{
    /** @var Producto $producto */
    $producto = Producto::factory()->create(['precio_venta' => $precio]);
    app(InventarioService::class)->registrarEntrada($producto, 50, 10.00, TipoMovimientoInventario::InventarioInicial);

    return $producto;
}

it('abre un turno y no permite dos en la misma caja ni dos al mismo cajero', function (): void {
    /** @var User $ana */
    $ana = User::factory()->create();
    /** @var User $luis */
    $luis = User::factory()->create();
    /** @var Caja $caja1 */
    $caja1 = Caja::factory()->create();
    /** @var Caja $caja2 */
    $caja2 = Caja::factory()->create();

    $turno = app(TurnoCajaService::class)->abrir($caja1, $ana, 500);

    expect($turno->estado)->toBe(EstadoTurno::Abierto)
        ->and((float) $turno->fondo_inicial)->toBe(500.0);

    expect(fn () => app(TurnoCajaService::class)->abrir($caja1, $luis, 0))->toThrow(TurnoCajaException::class);
    expect(fn () => app(TurnoCajaService::class)->abrir($caja2, $ana, 0))->toThrow(TurnoCajaException::class);
});

it('el cierre calcula el efectivo esperado y la diferencia', function (): void {
    /** @var User $cajero */
    $cajero = User::factory()->create();
    /** @var Caja $caja */
    $caja = Caja::factory()->create();
    $servicio = app(TurnoCajaService::class);
    $turno = $servicio->abrir($caja, $cajero, 500);

    $martillo = productoParaTurno(115);
    $cinta = productoParaTurno(100);

    app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($martillo->id, 2)]), $cajero);
    app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($cinta->id, 1)], montoTarjeta: 100), $cajero);
    $servicio->registrarMovimiento($turno, TipoMovimientoCaja::Egreso, 50, 'Pago de flete', $cajero);

    // Esperado: 500 + 230 − 50 = 680. Contado: 670 → faltan 10.
    $turno = $servicio->cerrar($turno, 670, 'Faltó cambio', $cajero);

    expect($turno->estado)->toBe(EstadoTurno::Cerrado)
        ->and($turno->cantidad_ventas)->toBe(2)
        ->and((float) $turno->total_ventas)->toBe(330.0)
        ->and((float) $turno->total_efectivo)->toBe(230.0)
        ->and((float) $turno->total_tarjeta)->toBe(100.0)
        ->and((float) $turno->total_egresos)->toBe(50.0)
        ->and((float) $turno->efectivo_esperado)->toBe(680.0)
        ->and((float) $turno->diferencia)->toBe(-10.0);
});

it('las ventas anuladas no cuentan en el cierre', function (): void {
    /** @var User $cajero */
    $cajero = User::factory()->create();
    /** @var Caja $caja */
    $caja = Caja::factory()->create();
    $servicio = app(TurnoCajaService::class);
    $turno = $servicio->abrir($caja, $cajero, 300);

    $venta = app(VentaService::class)->registrar(new DatosVenta([new LineaVenta(productoParaTurno(115)->id, 1)]), $cajero);
    app(VentaService::class)->anular($venta, 'Error de cobro', $cajero);

    $resumen = $servicio->resumen($turno);

    expect($resumen['cantidad_ventas'])->toBe(0)
        ->and($resumen['anuladas'])->toBe(1)
        ->and($resumen['efectivo_esperado'])->toBe(300.0);
});

it('no se cierra dos veces ni acepta movimientos en un turno cerrado', function (): void {
    /** @var User $cajero */
    $cajero = User::factory()->create();
    /** @var Caja $caja */
    $caja = Caja::factory()->create();
    $servicio = app(TurnoCajaService::class);
    $turno = $servicio->cerrar($servicio->abrir($caja, $cajero, 0), 0, null, $cajero);

    expect(fn () => $servicio->cerrar($turno, 0, null, $cajero))->toThrow(TurnoCajaException::class);
    expect(fn () => $servicio->registrarMovimiento($turno, TipoMovimientoCaja::Ingreso, 10, 'Cambio', $cajero))
        ->toThrow(TurnoCajaException::class);
});
