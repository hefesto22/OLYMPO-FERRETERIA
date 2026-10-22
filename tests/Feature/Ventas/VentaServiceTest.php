<?php

declare(strict_types=1);

use App\Domain\Exceptions\CaiException;
use App\Domain\Exceptions\DescuentoNoPermitidoException;
use App\Domain\Exceptions\PagoInsuficienteException;
use App\Domain\Exceptions\StockInsuficienteException;
use App\Domain\Exceptions\TurnoCajaException;
use App\Domain\Exceptions\VentaInvalidaException;
use App\Enums\EquivalenciaPresentacion;
use App\Enums\EstadoVenta;
use App\Enums\ListaPrecio;
use App\Enums\MetodoPago;
use App\Enums\RolFerreteria;
use App\Enums\TipoDescuento;
use App\Enums\TipoDocumentoVenta;
use App\Enums\TipoIsv;
use App\Enums\TipoMargen;
use App\Enums\TipoMovimientoInventario;
use App\Models\Caja;
use App\Models\ConfiguracionEmpresa;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\RangoCai;
use App\Models\TurnoCaja;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Venta;
use App\Services\Inventario\InventarioService;
use App\Services\Ventas\Datos\DatosVenta;
use App\Services\Ventas\Datos\LineaVenta;
use App\Services\Ventas\TurnoCajaService;
use App\Services\Ventas\VentaService;
use Spatie\Permission\Models\Role;

/**
 * @return array{0: User, 1: TurnoCaja, 2: Caja}
 */
function cajeroConTurnoAbierto(): array
{
    /** @var User $cajero */
    $cajero = User::factory()->create();

    /** @var Caja $caja */
    $caja = Caja::factory()->create(['punto_emision' => '001']);

    $turno = app(TurnoCajaService::class)->abrir($caja, $cajero, 0);

    return [$cajero, $turno, $caja];
}

/**
 * @param array<string, mixed> $atributos
 */
function productoEnExistencia(float $existencia, array $atributos = []): Producto
{
    /** @var Producto $producto */
    $producto = Producto::factory()->create($atributos);

    if ($existencia > 0) {
        app(InventarioService::class)->registrarEntrada($producto, $existencia, 50.00, TipoMovimientoInventario::InventarioInicial);
    }

    return $producto->refresh();
}

it('registra un recibo, descuenta el stock y separa el ISV incluido', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    $producto = productoEnExistencia(10, ['precio_venta' => 115]);

    $venta = app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($producto->id, 2)]), $cajero);

    expect($venta->numero)->toBe('REC-000001')
        ->and($venta->tipo_documento)->toBe(TipoDocumentoVenta::Recibo)
        ->and($venta->cliente_nombre)->toBe('Consumidor final')
        ->and((float) $venta->total)->toBe(230.0)
        ->and((float) $venta->importe_gravado_15)->toBe(200.0)
        ->and((float) $venta->isv_15)->toBe(30.0)
        ->and((float) $producto->refresh()->stock)->toBe(8.0);

    $detalle = $venta->detalles()->sole();
    $salida = MovimientoInventario::query()->where('tipo', TipoMovimientoInventario::SalidaVenta)->sole();

    expect((float) $detalle->costo_unitario_base)->toBe(50.0)
        ->and($salida->documento_type)->toBe('venta')
        ->and($salida->documento_id)->toBe($venta->id);
});

it('vende por presentación y descuenta en unidad base', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    $tornillo = productoEnExistencia(500, ['precio_venta' => 0.75]);
    $bolsa = $tornillo->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'nombre'           => 'Bolsa x100',
        'factor'           => 100,
        'precio_venta'     => 65,
    ]);

    $venta = app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($tornillo->id, 2, $bolsa->id)]), $cajero);

    expect((float) $venta->total)->toBe(130.0)
        ->and($venta->detalles()->sole()->unidad)->toBe('Bolsa x100')
        ->and((float) $tornillo->refresh()->stock)->toBe(300.0);
});

it('cobra precio de mayoreo cuando la venta es de mayoreo', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    $clavo = productoEnExistencia(100, ['precio_venta' => 28.00, 'precio_mayoreo' => 25.50]);

    $venta = app(VentaService::class)->registrar(
        new DatosVenta([new LineaVenta($clavo->id, 10)], listaPrecio: ListaPrecio::Mayoreo),
        $cajero,
    );

    expect((float) $venta->total)->toBe(255.0)
        ->and($venta->lista_precio)->toBe(ListaPrecio::Mayoreo);
});

it('emite factura con el correlativo del CAI y avanza el rango', function (): void {
    [$cajero, , $caja] = cajeroConTurnoAbierto();

    /** @var RangoCai $rango */
    $rango = RangoCai::factory()->create(['caja_id' => $caja->id, 'punto_emision' => '001']);
    $producto = productoEnExistencia(5, ['precio_venta' => 100]);

    $venta = app(VentaService::class)->registrar(new DatosVenta(
        [new LineaVenta($producto->id, 1)],
        TipoDocumentoVenta::Factura,
        clienteNombre: 'Constructora El Progreso',
        clienteRtn: '05011999000123',
    ), $cajero);

    expect($venta->numero)->toBe('000-001-01-00000001')
        ->and($venta->cai)->toBe($rango->cai)
        ->and($venta->cai_rango_desde)->toBe('000-001-01-00000001')
        ->and($venta->cai_rango_hasta)->toBe('000-001-01-00000100')
        ->and($venta->cliente_rtn)->toBe('05011999000123')
        ->and($venta->datos_emisor)->toBeArray()
        ->and($rango->refresh()->siguiente)->toBe(2);
});

it('no factura sin rango CAI, ni con CAI vencido o agotado, y no toca el stock', function (string $estado): void {
    [$cajero, , $caja] = cajeroConTurnoAbierto();
    $producto = productoEnExistencia(5, ['precio_venta' => 100]);

    if ($estado === 'vencido') {
        RangoCai::factory()->vencido()->create(['caja_id' => $caja->id]);
    }

    if ($estado === 'agotado') {
        RangoCai::factory()->agotado()->create(['caja_id' => $caja->id]);
    }

    expect(fn () => app(VentaService::class)->registrar(
        new DatosVenta([new LineaVenta($producto->id, 1)], TipoDocumentoVenta::Factura),
        $cajero,
    ))->toThrow(CaiException::class);

    expect((float) $producto->refresh()->stock)->toBe(5.0)
        ->and(Venta::query()->count())->toBe(0);
})->with(['sin rango', 'vencido', 'agotado']);

it('respeta el tope de descuento del cajero', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    Role::findOrCreate('cajero', 'web');
    $cajero->assignRole('cajero');
    ConfiguracionEmpresa::actual()->update(['descuento_max_cajero' => 5]);
    $producto = productoEnExistencia(10, ['precio_venta' => 115]);

    expect(fn () => app(VentaService::class)->registrar(
        new DatosVenta([new LineaVenta($producto->id, 1, descuento: 10)]),
        $cajero,
    ))->toThrow(DescuentoNoPermitidoException::class);

    $venta = app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($producto->id, 1, descuento: 5)]), $cajero);

    // 115 − 5 % = 109.25 → L 110 (sin monedas): el descuento efectivo es L 5.
    expect((float) $venta->descuento)->toBe(5.0)
        ->and((float) $venta->total)->toBe(110.0);
});

it('un usuario sin rol de caja no puede dar descuentos', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    $producto = productoEnExistencia(10, ['precio_venta' => 100]);

    expect(fn () => app(VentaService::class)->registrar(
        new DatosVenta([new LineaVenta($producto->id, 1, descuento: 1)]),
        $cajero,
    ))->toThrow(DescuentoNoPermitidoException::class);
});

it('acepta el descuento en lempiras y lo compara con el tope en porcentaje', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    Role::findOrCreate('cajero', 'web');
    $cajero->assignRole('cajero');
    ConfiguracionEmpresa::actual()->update(['descuento_max_cajero' => 5]);
    $producto = productoEnExistencia(10, ['precio_venta' => 115]);

    // L 10 de L 115 es 8.7 %: pasa el tope de 5 %.
    expect(fn () => app(VentaService::class)->registrar(
        new DatosVenta([new LineaVenta($producto->id, 1, descuento: 10, tipoDescuento: TipoDescuento::Monto)]),
        $cajero,
    ))->toThrow(DescuentoNoPermitidoException::class);

    $venta = app(VentaService::class)->registrar(
        new DatosVenta([new LineaVenta($producto->id, 1, descuento: 5, tipoDescuento: TipoDescuento::Monto)]),
        $cajero,
    );
    $detalle = $venta->detalles()->sole();

    expect((float) $venta->total)->toBe(110.0)
        ->and((float) $venta->descuento)->toBe(5.0)
        ->and($detalle->descuento_tipo)->toBe(TipoDescuento::Monto)
        ->and((float) $detalle->descuento_valor)->toBe(5.0)
        ->and((float) $detalle->descuento_pct)->toBe(4.35);
});

it('reparte el descuento a toda la venta entre las líneas y separa el ISV por tasa', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    Role::findOrCreate(RolFerreteria::Administrador->value, 'web');
    $cajero->assignRole(RolFerreteria::Administrador->value);
    $martillo = productoEnExistencia(10, ['precio_venta' => 115]);
    $semilla = productoEnExistencia(10, ['precio_venta' => 100, 'tipo_isv' => TipoIsv::Exento]);

    // 230 + 100 = 330; − 10 % = 297. Los L 33 se reparten 23 y 10.
    $venta = app(VentaService::class)->registrar(
        new DatosVenta(
            [new LineaVenta($martillo->id, 2), new LineaVenta($semilla->id, 1)],
            descuentoGlobal: 10,
            tipoDescuentoGlobal: TipoDescuento::Porcentaje,
        ),
        $cajero,
    );

    expect((float) $venta->total)->toBe(297.0)
        ->and((float) $venta->descuento)->toBe(33.0)
        ->and((float) $venta->descuento_global)->toBe(33.0)
        ->and($venta->descuento_global_tipo)->toBe(TipoDescuento::Porcentaje)
        ->and((float) $venta->descuento_global_valor)->toBe(10.0)
        ->and((float) $venta->importe_gravado_15)->toBe(180.0)
        ->and((float) $venta->isv_15)->toBe(27.0)
        ->and((float) $venta->importe_exento)->toBe(90.0);

    $detalles = $venta->detalles()->orderBy('id')->get();

    expect((float) $detalles[0]->descuento_global)->toBe(23.0)
        ->and((float) $detalles[0]->total)->toBe(207.0)
        ->and($detalles[0]->descuentoDeLinea())->toBe(0.0)
        ->and((float) $detalles[1]->descuento_global)->toBe(10.0)
        ->and((float) $detalles[1]->total)->toBe(90.0);
});

it('no deja juntar descuentos de línea y global por encima del tope', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    Role::findOrCreate('cajero', 'web');
    $cajero->assignRole('cajero');
    ConfiguracionEmpresa::actual()->update(['descuento_max_cajero' => 5]);
    $producto = productoEnExistencia(10, ['precio_venta' => 100]);

    // 5 % en la línea (L 95) y otro 5 % a toda la venta (L 91): son 9 %.
    expect(fn () => app(VentaService::class)->registrar(
        new DatosVenta([new LineaVenta($producto->id, 1, descuento: 5)], descuentoGlobal: 5),
        $cajero,
    ))->toThrow(DescuentoNoPermitidoException::class);

    // Solo el 5 % a toda la venta sí cabe.
    $venta = app(VentaService::class)->registrar(
        new DatosVenta([new LineaVenta($producto->id, 1)], descuentoGlobal: 5),
        $cajero,
    );

    expect((float) $venta->total)->toBe(95.0)
        ->and((float) $venta->descuento_global)->toBe(5.0);
});

it('exige efectivo suficiente', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    $producto = productoEnExistencia(10, ['precio_venta' => 115]);

    expect(fn () => app(VentaService::class)->registrar(
        new DatosVenta([new LineaVenta($producto->id, 2)], efectivoRecibido: 100),
        $cajero,
    ))->toThrow(PagoInsuficienteException::class);
});

it('reparte el pago entre tarjeta y efectivo y calcula el cambio', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    $producto = productoEnExistencia(10, ['precio_venta' => 115]);

    $venta = app(VentaService::class)->registrar(new DatosVenta(
        [new LineaVenta($producto->id, 2)],
        efectivoRecibido: 200,
        montoTarjeta: 100,
        referenciaTarjeta: 'V-123',
    ), $cajero);

    $pagos = $venta->pagos()->get()->keyBy(fn ($pago): string => $pago->metodo->value);

    expect((float) $venta->cambio)->toBe(70.0)
        ->and((float) $venta->efectivo_recibido)->toBe(200.0)
        ->and((float) $pagos[MetodoPago::Efectivo->value]->monto)->toBe(130.0)
        ->and((float) $pagos[MetodoPago::Tarjeta->value]->monto)->toBe(100.0)
        ->and($pagos[MetodoPago::Tarjeta->value]->referencia)->toBe('V-123');
});

it('no vende sin turno abierto', function (): void {
    /** @var User $usuario */
    $usuario = User::factory()->create();
    $producto = productoEnExistencia(10, ['precio_venta' => 115]);

    expect(fn () => app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($producto->id, 1)]), $usuario))
        ->toThrow(TurnoCajaException::class);
});

it('no vende más de lo que hay y no consume el número del recibo', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    $producto = productoEnExistencia(3, ['precio_venta' => 50]);

    expect(fn () => app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($producto->id, 5)]), $cajero))
        ->toThrow(StockInsuficienteException::class);

    $venta = app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($producto->id, 1)]), $cajero);

    expect($venta->numero)->toBe('REC-000001')
        ->and(Venta::query()->count())->toBe(1);
});

it('no permite fracciones en productos que se venden por unidad', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    $producto = productoEnExistencia(10, ['precio_venta' => 50]);

    expect(fn () => app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($producto->id, 1.5)]), $cajero))
        ->toThrow(VentaInvalidaException::class);
});

it('anular una venta devuelve el stock al inventario', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    $producto = productoEnExistencia(10, ['precio_venta' => 115]);
    $venta = app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($producto->id, 2)]), $cajero);

    app(VentaService::class)->anular($venta, 'Cliente devolvió todo', $cajero);

    expect((float) $producto->refresh()->stock)->toBe(10.0)
        ->and($venta->refresh()->estado)->toBe(EstadoVenta::Anulada)
        ->and($venta->motivo_anulacion)->toBe('Cliente devolvió todo')
        ->and(MovimientoInventario::query()->where('tipo', TipoMovimientoInventario::AnulacionVenta)->count())->toBe(1);

    expect(fn () => app(VentaService::class)->anular($venta, 'Otra vez', $cajero))
        ->toThrow(VentaInvalidaException::class);
});

it('vende clavos sueltos de un producto que se lleva en libras', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    $producto = productoEnExistencia(10, ['precio_venta' => 28]);

    $suelto = $producto->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'nombre'           => 'Clavo suelto',
        'factor_cantidad'  => 100,
        'factor_modo'      => EquivalenciaPresentacion::PorUnidadBase,
    ]);

    $venta = app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($producto->id, 20, $suelto->id)]), $cajero);
    $detalle = $venta->detalles()->sole();

    // L 28 × 0.01 = L 0.28, pero un clavo nunca cuesta menos de L 1.
    expect((float) $detalle->precio_unitario)->toBe(1.0)
        ->and((float) $venta->total)->toBe(20.0)
        ->and((float) $detalle->cantidad_base)->toBe(0.2)
        ->and((float) $producto->refresh()->stock)->toBe(9.8);
});

it('descuenta exacto un clavo suelto aunque el factor no sea redondo', function (): void {
    [$cajero] = cajeroConTurnoAbierto();
    $producto = productoEnExistencia(10, ['precio_venta' => 28]);

    $suelto = $producto->presentaciones()->create([
        'unidad_medida_id' => UnidadMedida::factory()->create()->id,
        'factor_cantidad'  => 80,
        'factor_modo'      => EquivalenciaPresentacion::PorUnidadBase,
    ]);

    app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($producto->id, 1, $suelto->id)]), $cajero);

    // 1/80 de libra = 0.0125 lb (con 3 decimales se habrían descontado 0.013).
    expect((float) $producto->refresh()->stock)->toBe(9.9875)
        ->and((float) MovimientoInventario::query()->where('tipo', TipoMovimientoInventario::SalidaVenta)->sole()->cantidad)->toBe(0.0125);
});

it('no vende un producto con margen que todavía no tiene precio', function (): void {
    [$cajero] = cajeroConTurnoAbierto();

    // Entró al inventario sin pasar por la revisión de precios: sigue sin precio.
    $producto = productoEnExistencia(10, ['margen_tipo' => TipoMargen::Porcentaje, 'margen_publico' => 30]);

    expect(fn () => app(VentaService::class)->registrar(new DatosVenta([new LineaVenta($producto->id, 1)]), $cajero))
        ->toThrow(VentaInvalidaException::class, 'todavía no tiene precio');
});
