<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Domain\Exceptions\DescuentoNoPermitidoException;
use App\Domain\Exceptions\PagoInsuficienteException;
use App\Domain\Exceptions\TurnoCajaException;
use App\Domain\Exceptions\VentaInvalidaException;
use App\Domain\ValueObjects\RTN;
use App\Enums\EstadoTurno;
use App\Enums\EstadoVenta;
use App\Enums\MetodoPago;
use App\Enums\TipoDescuento;
use App\Enums\TipoDocumentoVenta;
use App\Enums\TipoIsv;
use App\Enums\TipoMovimientoInventario;
use App\Models\Cliente;
use App\Models\ConfiguracionEmpresa;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\RangoCai;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Services\Inventario\InventarioService;
use App\Services\Precios\PrecioService;
use App\Services\Ventas\Datos\DatosVenta;
use App\Services\Ventas\Datos\LineaVenta;
use App\Support\Ventas\CalculadoraVenta;
use App\Support\Ventas\DescuentoPermitido;
use App\Support\Ventas\PrecioProducto;
use Illuminate\Support\Facades\DB;

/**
 * Registra y anula ventas.
 *
 * Todo en una transacción: valida turno, precios (los decide el
 * servidor según la lista), descuentos contra el tope del rol, pagos y
 * existencias; toma el número (CAI o recibo) y descuenta el inventario.
 * Si cualquier paso falla, no queda venta, ni número consumido, ni
 * movimiento de stock.
 */
final class VentaService
{
    private const EPSILON = 0.004;

    public function __construct(
        private readonly InventarioService $inventario,
        private readonly NumeracionService $numeracion,
        private readonly PrecioService $precios,
    ) {}

    public function registrar(DatosVenta $datos, User $cajero): Venta
    {
        if ($datos->lineas === []) {
            throw VentaInvalidaException::sinLineas();
        }

        return DB::transaction(function () use ($datos, $cajero): Venta {
            $turno = TurnoCaja::query()
                ->with('caja')
                ->where('user_id', $cajero->id)
                ->where('estado', EstadoTurno::Abierto)
                ->lockForUpdate()
                ->first();

            if ($turno === null) {
                throw TurnoCajaException::sinTurno();
            }

            $cliente = $this->cliente($datos->clienteId);
            $lineas = $this->prepararLineas($datos, $cajero);
            $totales = CalculadoraVenta::totales($lineas);
            $pagos = $this->prepararPagos($datos, $totales['total']);
            [$nombre, $rtn] = $this->datosCliente($datos, $cliente);

            $rango = null;

            if ($datos->tipoDocumento === TipoDocumentoVenta::Factura) {
                $factura = $this->numeracion->siguienteFactura($turno->caja);
                $numero = $factura['numero'];
                $rango = $factura['rango'];
            } else {
                $numero = $this->numeracion->siguienteRecibo();
            }

            $descuentoGlobal = round(array_sum(array_column($lineas, 'descuento_global')), 2);
            $venta = $this->crearVenta($datos, $turno, $cajero, $cliente, $nombre, $rtn, $numero, $rango, $totales, $pagos, $descuentoGlobal);
            $this->guardarLineasYDescontarStock($venta, $lineas);

            foreach ($pagos['lineas'] as $pago) {
                VentaPago::query()->create([
                    'venta_id'   => $venta->id,
                    'metodo'     => $pago['metodo'],
                    'monto'      => $pago['monto'],
                    'referencia' => $pago['referencia'],
                ]);
            }

            return $venta;
        });
    }

    /**
     * Anula la venta y devuelve al inventario lo vendido, al costo con que
     * salió. El número de factura queda usado (las facturas anuladas se
     * conservan para el SAR).
     */
    public function anular(Venta $venta, string $motivo, User $usuario): Venta
    {
        return DB::transaction(function () use ($venta, $motivo, $usuario): Venta {
            $venta = Venta::query()->whereKey($venta->getKey())->lockForUpdate()->firstOrFail();

            if ($venta->estaAnulada()) {
                throw VentaInvalidaException::yaAnulada($venta->numero);
            }

            $venta->load('detalles.producto');

            foreach ($venta->detalles->sortBy('producto_id') as $detalle) {
                $this->inventario->registrarEntrada(
                    $detalle->producto,
                    (float) $detalle->cantidad_base,
                    (float) $detalle->costo_unitario_base,
                    TipoMovimientoInventario::AnulacionVenta,
                    $venta,
                    "Anulación de la venta {$venta->numero}",
                );
            }

            // Lo devuelto entra a su costo de salida y puede mover el promedio.
            $this->precios->revisar(
                $venta->detalles->map(fn (VentaDetalle $detalle): int => $detalle->producto_id)->all(),
                "Anulación de la venta {$venta->numero}",
            );

            $venta->forceFill([
                'estado'           => EstadoVenta::Anulada,
                'anulada_at'       => now(),
                'anulada_por'      => $usuario->id,
                'motivo_anulacion' => $motivo,
            ])->save();

            return $venta;
        });
    }

    /**
     * Calcula cada línea con el precio de la lista, valida cantidad,
     * presentación y descuentos, y reparte el descuento global.
     *
     * El tope del rol (en %) se revisa en cada línea y en la venta entera:
     * lo rebajado por las líneas más el global no puede pasar del tope
     * sobre el importe sin descuentos (5 % en una línea y otro 5 % global
     * ya no caben en un tope de 5 %).
     *
     * @return list<array{producto: Producto, presentacion: ProductoPresentacion|null, unidad: string, cantidad: float, factor: float, cantidad_base: float, precio: float, descuento_tipo: TipoDescuento, descuento_valor: float, descuento_pct: float, descuento_global: float, tipo_isv: TipoIsv, bruto: float, descuento: float, total: float, base: float, isv: float}>
     */
    private function prepararLineas(DatosVenta $datos, User $cajero): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (LineaVenta $linea): int => $linea->productoId,
            $datos->lineas,
        )));

        $productos = Producto::query()
            ->with(['unidadMedida', 'presentaciones.unidadMedida'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $descuentoMaximo = DescuentoPermitido::para($cajero);

        if ($datos->descuentoGlobal < 0) {
            throw DescuentoNoPermitidoException::negativo();
        }

        $previas = [];

        foreach ($datos->lineas as $linea) {
            $producto = $productos->get($linea->productoId);

            if (! $producto instanceof Producto) {
                throw VentaInvalidaException::productoNoDisponible("#{$linea->productoId}");
            }

            if (! $producto->activo) {
                throw VentaInvalidaException::productoNoDisponible($producto->nombre);
            }

            if ($linea->cantidad <= 0) {
                throw VentaInvalidaException::cantidadInvalida($producto->nombre);
            }

            $presentacion = $this->presentacion($producto, $linea->presentacionId);
            $unidad = $presentacion !== null ? $presentacion->unidadMedida : $producto->unidadMedida;

            if (! $unidad->permite_decimales && fmod($linea->cantidad, 1.0) > 0.0005) {
                throw VentaInvalidaException::cantidadNoFraccionable($producto->nombre, mb_strtolower($unidad->nombre));
            }

            if ($linea->descuento < 0) {
                throw DescuentoNoPermitidoException::negativo();
            }

            $factor = $presentacion !== null ? (float) $presentacion->factor : 1.0;
            $precio = PrecioProducto::unitario($producto, $presentacion, $datos->listaPrecio);

            // Un producto con margen que aún no tiene costo no tiene precio.
            if ($precio <= 0) {
                throw VentaInvalidaException::sinPrecio($producto->nombre);
            }

            $calculo = CalculadoraVenta::linea($precio, $linea->cantidad, $linea->descuento, $producto->tipo_isv->tasa(), $linea->tipoDescuento);
            $porcentaje = CalculadoraVenta::porcentaje($calculo['bruto'], $linea->descuento, $linea->tipoDescuento);

            if (($porcentaje - $descuentoMaximo) > 0.001) {
                throw DescuentoNoPermitidoException::excede($producto->nombre, $porcentaje, $descuentoMaximo);
            }

            $previas[] = [
                'producto'        => $producto,
                'presentacion'    => $presentacion,
                'unidad'          => $presentacion !== null ? $presentacion->etiqueta() : $producto->unidadMedida->nombre,
                'cantidad'        => $linea->cantidad,
                'factor'          => $factor,
                'cantidad_base'   => round($linea->cantidad * $factor, 6),
                'precio'          => $precio,
                'descuento_tipo'  => $linea->tipoDescuento,
                'descuento_valor' => round($linea->descuento, 2),
                'descuento_pct'   => round($porcentaje, 2),
                'tipo_isv'        => $producto->tipo_isv,
                'bruto'           => $calculo['bruto'],
                'total'           => $calculo['total'],
            ];
        }

        $partes = CalculadoraVenta::repartirGlobal(array_column($previas, 'total'), $datos->descuentoGlobal, $datos->tipoDescuentoGlobal);
        $resultado = [];
        $bruto = 0.0;
        $rebajado = 0.0;

        foreach ($previas as $indice => $previa) {
            $parte = $partes[$indice] ?? 0.0;
            $calculo = CalculadoraVenta::conParteGlobal($previa['bruto'], $previa['total'], $parte, $previa['tipo_isv']->tasa());
            $bruto += $calculo['bruto'];
            $rebajado += $calculo['descuento'];

            $resultado[] = [
                'producto'         => $previa['producto'],
                'presentacion'     => $previa['presentacion'],
                'unidad'           => $previa['unidad'],
                'cantidad'         => $previa['cantidad'],
                'factor'           => $previa['factor'],
                'cantidad_base'    => $previa['cantidad_base'],
                'precio'           => $previa['precio'],
                'descuento_tipo'   => $previa['descuento_tipo'],
                'descuento_valor'  => $previa['descuento_valor'],
                'descuento_pct'    => $previa['descuento_pct'],
                'descuento_global' => $parte,
                'tipo_isv'         => $previa['tipo_isv'],
                'bruto'            => $calculo['bruto'],
                'descuento'        => $calculo['descuento'],
                'total'            => $calculo['total'],
                'base'             => $calculo['base'],
                'isv'              => $calculo['isv'],
            ];
        }

        $porcentajeVenta = $bruto > 0 ? $rebajado / $bruto * 100 : 0.0;

        if (($porcentajeVenta - $descuentoMaximo) > 0.001) {
            throw DescuentoNoPermitidoException::excedeVenta($porcentajeVenta, $descuentoMaximo);
        }

        return $resultado;
    }

    private function presentacion(Producto $producto, ?int $presentacionId): ?ProductoPresentacion
    {
        if ($presentacionId === null) {
            return null;
        }

        $presentacion = $producto->presentaciones->firstWhere('id', $presentacionId);

        if (! $presentacion instanceof ProductoPresentacion || ! $presentacion->activo) {
            throw VentaInvalidaException::presentacionInvalida($producto->nombre);
        }

        // Evita volver a consultar el producto al armar la etiqueta.
        $presentacion->setRelation('producto', $producto);

        return $presentacion;
    }

    /**
     * Reparte el cobro: tarjeta y transferencia por su monto, el resto en
     * efectivo. Si no se indica efectivo recibido, se asume el exacto.
     *
     * @return array{efectivo_recibido: float, cambio: float, lineas: list<array{metodo: MetodoPago, monto: float, referencia: string|null}>}
     */
    private function prepararPagos(DatosVenta $datos, float $total): array
    {
        $tarjeta = round($datos->montoTarjeta, 2);
        $transferencia = round($datos->montoTransferencia, 2);

        if ($tarjeta < 0 || $transferencia < 0 || ($datos->efectivoRecibido !== null && $datos->efectivoRecibido < 0)) {
            throw PagoInsuficienteException::montoNegativo();
        }

        if (($tarjeta + $transferencia - $total) > self::EPSILON) {
            throw PagoInsuficienteException::excedeTotal();
        }

        $efectivoAplicado = round($total - $tarjeta - $transferencia, 2);
        $recibido = $datos->efectivoRecibido !== null ? round($datos->efectivoRecibido, 2) : $efectivoAplicado;

        if (($efectivoAplicado - $recibido) > self::EPSILON) {
            throw PagoInsuficienteException::efectivo(round($efectivoAplicado - $recibido, 2));
        }

        $lineas = [];

        if ($efectivoAplicado > 0) {
            $lineas[] = ['metodo' => MetodoPago::Efectivo, 'monto' => $efectivoAplicado, 'referencia' => null];
        }

        if ($tarjeta > 0) {
            $lineas[] = ['metodo' => MetodoPago::Tarjeta, 'monto' => $tarjeta, 'referencia' => $datos->referenciaTarjeta];
        }

        if ($transferencia > 0) {
            $lineas[] = ['metodo' => MetodoPago::Transferencia, 'monto' => $transferencia, 'referencia' => $datos->referenciaTransferencia];
        }

        return [
            'efectivo_recibido' => $efectivoAplicado > 0 ? $recibido : 0.0,
            'cambio'            => $efectivoAplicado > 0 ? round($recibido - $efectivoAplicado, 2) : 0.0,
            'lineas'            => $lineas,
        ];
    }

    private function cliente(?int $clienteId): ?Cliente
    {
        if ($clienteId === null) {
            return null;
        }

        $cliente = Cliente::query()->find($clienteId);

        if ($cliente === null || ! $cliente->activo) {
            throw VentaInvalidaException::clienteNoDisponible();
        }

        return $cliente;
    }

    /**
     * Nombre y RTN que van en el documento. Lo tecleado en caja manda
     * sobre lo guardado en el cliente. El RTN se valida (14 dígitos).
     *
     * @return array{0: string, 1: string|null}
     */
    private function datosCliente(DatosVenta $datos, ?Cliente $cliente): array
    {
        $nombre = trim((string) $datos->clienteNombre);

        if ($nombre === '') {
            $nombre = $cliente !== null ? $cliente->nombre : 'Consumidor final';
        }

        $rtn = trim((string) $datos->clienteRtn);

        if ($rtn === '') {
            $rtn = (string) $cliente?->rtn;
        }

        if ($rtn === '') {
            return [$nombre, null];
        }

        return [$nombre, (new RTN($rtn))->valor];
    }

    /**
     * @param array{descuento: float, importe_exento: float, importe_gravado_15: float, importe_gravado_18: float, isv_15: float, isv_18: float, subtotal: float, total: float} $totales
     * @param array{efectivo_recibido: float, cambio: float, lineas: list<array{metodo: MetodoPago, monto: float, referencia: string|null}>} $pagos
     * @param float $descuentoGlobal lo rebajado por el descuento a toda la venta (ya repartido en las líneas)
     */
    private function crearVenta(
        DatosVenta $datos,
        TurnoCaja $turno,
        User $cajero,
        ?Cliente $cliente,
        string $nombre,
        ?string $rtn,
        string $numero,
        ?RangoCai $rango,
        array $totales,
        array $pagos,
        float $descuentoGlobal,
    ): Venta {
        $venta = new Venta;
        $venta->forceFill([
            'numero'                 => $numero,
            'tipo_documento'         => $datos->tipoDocumento,
            'caja_id'                => $turno->caja_id,
            'turno_caja_id'          => $turno->id,
            'user_id'                => $cajero->id,
            'cliente_id'             => $cliente?->id,
            'cliente_nombre'         => $nombre,
            'cliente_rtn'            => $rtn,
            'lista_precio'           => $datos->listaPrecio,
            'estado'                 => EstadoVenta::Completada,
            'rango_cai_id'           => $rango?->id,
            'cai'                    => $rango?->cai,
            'cai_rango_desde'        => $rango !== null ? $rango->formatearNumero($rango->rango_inicial) : null,
            'cai_rango_hasta'        => $rango !== null ? $rango->formatearNumero($rango->rango_final) : null,
            'cai_fecha_limite'       => $rango?->fecha_limite_emision,
            'datos_emisor'           => ConfiguracionEmpresa::actual()->datosEmisor(),
            'descuento'              => $totales['descuento'],
            'descuento_global_tipo'  => $descuentoGlobal > 0 ? $datos->tipoDescuentoGlobal : null,
            'descuento_global_valor' => $descuentoGlobal > 0 ? round($datos->descuentoGlobal, 2) : 0.0,
            'descuento_global'       => $descuentoGlobal,
            'importe_exento'         => $totales['importe_exento'],
            'importe_gravado_15'     => $totales['importe_gravado_15'],
            'importe_gravado_18'     => $totales['importe_gravado_18'],
            'isv_15'                 => $totales['isv_15'],
            'isv_18'                 => $totales['isv_18'],
            'subtotal'               => $totales['subtotal'],
            'total'                  => $totales['total'],
            'efectivo_recibido'      => $pagos['efectivo_recibido'],
            'cambio'                 => $pagos['cambio'],
            'notas'                  => $datos->notas,
        ])->save();

        return $venta;
    }

    /**
     * Guarda las líneas y descuenta el stock en orden de producto (siempre
     * el mismo orden de bloqueo: dos ventas simultáneas no se traban).
     *
     * @param list<array{producto: Producto, presentacion: ProductoPresentacion|null, unidad: string, cantidad: float, factor: float, cantidad_base: float, precio: float, descuento_tipo: TipoDescuento, descuento_valor: float, descuento_pct: float, descuento_global: float, tipo_isv: TipoIsv, bruto: float, descuento: float, total: float, base: float, isv: float}> $lineas
     */
    private function guardarLineasYDescontarStock(Venta $venta, array $lineas): void
    {
        $guardadas = [];

        foreach ($lineas as $linea) {
            $detalle = new VentaDetalle;
            $detalle->forceFill([
                'venta_id'                 => $venta->id,
                'producto_id'              => $linea['producto']->id,
                'producto_presentacion_id' => $linea['presentacion']?->id,
                'descripcion'              => $linea['producto']->nombre,
                'unidad'                   => $linea['unidad'],
                'cantidad'                 => $linea['cantidad'],
                'factor'                   => $linea['factor'],
                'cantidad_base'            => $linea['cantidad_base'],
                'precio_unitario'          => $linea['precio'],
                'descuento_tipo'           => $linea['descuento_tipo'],
                'descuento_valor'          => $linea['descuento_valor'],
                'descuento_pct'            => $linea['descuento_pct'],
                'descuento_global'         => $linea['descuento_global'],
                'descuento'                => $linea['descuento'],
                'tipo_isv'                 => $linea['tipo_isv'],
                'base_imponible'           => $linea['base'],
                'isv'                      => $linea['isv'],
                'total'                    => $linea['total'],
            ])->save();

            $guardadas[] = ['detalle' => $detalle, 'producto' => $linea['producto'], 'cantidad_base' => $linea['cantidad_base']];
        }

        usort($guardadas, static fn (array $a, array $b): int => $a['producto']->id <=> $b['producto']->id);

        foreach ($guardadas as $guardada) {
            $movimiento = $this->inventario->registrarSalida(
                $guardada['producto'],
                $guardada['cantidad_base'],
                TipoMovimientoInventario::SalidaVenta,
                $venta,
                "Venta {$venta->numero}",
            );

            if ($movimiento !== null) {
                $guardada['detalle']->forceFill(['costo_unitario_base' => $movimiento->costo_unitario])->save();
            }
        }
    }
}
