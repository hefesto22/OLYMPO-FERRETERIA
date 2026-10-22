@extends('tickets.layout')

@section('titulo', $venta->numero)

@section('contenido')
    @php
        /** Las facturas usan los datos del emisor guardados al emitirlas. */
        $emisor = $venta->esFactura() && is_array($venta->datos_emisor) ? $venta->datos_emisor : $empresa->datosEmisor();
        $nombre = filled($emisor['nombre_comercial'] ?? null) ? $emisor['nombre_comercial'] : ($emisor['razon_social'] ?? '');
    @endphp

    <div class="centro">
        @if ($logo)
            <img src="{{ $logo }}" alt="" class="logo">
        @endif
        <div class="grande negrita">{{ $nombre }}</div>
        @if (filled($emisor['razon_social'] ?? null) && $emisor['razon_social'] !== $nombre)
            <div>{{ $emisor['razon_social'] }}</div>
        @endif
        @if (filled($emisor['rtn'] ?? null))
            <div>RTN: {{ $emisor['rtn'] }}</div>
        @endif
        @if (filled($emisor['direccion'] ?? null))
            <div>{{ $emisor['direccion'] }}</div>
        @endif
        @if (filled($emisor['telefono'] ?? null))
            <div>Tel.: {{ $emisor['telefono'] }}</div>
        @endif
        @if (filled($emisor['correo'] ?? null))
            <div>{{ $emisor['correo'] }}</div>
        @endif
    </div>

    <div class="linea"></div>

    <div class="centro grande negrita">{{ $venta->esFactura() ? 'FACTURA' : 'RECIBO' }}</div>
    <div class="centro negrita">N.º {{ $venta->numero }}</div>
    @if ($venta->estaAnulada())
        <div class="centro anulada">*** ANULADA ***</div>
    @endif

    <table>
        <tr><td>Fecha:</td><td class="der">{{ $venta->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Caja:</td><td class="der">{{ $venta->caja->nombre }}</td></tr>
        <tr><td>Cajero:</td><td class="der">{{ $venta->cajero->name }}</td></tr>
    </table>

    <div class="linea"></div>

    <div>Cliente: {{ $venta->cliente_nombre }}</div>
    <div>RTN: {{ $venta->cliente_rtn ?? '—' }}</div>

    @if ($venta->esFactura())
        <div class="linea"></div>
        <div>CAI: {{ $venta->cai }}</div>
        <div>Rango autorizado:</div>
        <div>{{ $venta->cai_rango_desde }} al</div>
        <div>{{ $venta->cai_rango_hasta }}</div>
        <div>Fecha límite de emisión: {{ $venta->cai_fecha_limite?->format('d/m/Y') }}</div>
    @endif

    <div class="linea"></div>

    <table>
        @foreach ($venta->detalles as $detalle)
            <tr><td colspan="2">{{ $detalle->descripcion }}</td></tr>
            <tr>
                <td class="sangria">
                    {{ cantidadFormato((float) $detalle->cantidad) }} {{ $detalle->unidad }} × {{ lempiras((float) $detalle->precio_unitario) }}{{ $detalle->tipo_isv === \App\Enums\TipoIsv::Exento ? ' E' : '' }}
                </td>
                <td class="der">{{ lempiras((float) $detalle->total + (float) $detalle->descuento) }}</td>
            </tr>
            {{-- El descuento de la línea; su parte del descuento a toda la venta va abajo, junta. --}}
            @if ($detalle->descuentoDeLinea() > 0)
                <tr>
                    <td class="sangria">{{ $detalle->etiquetaDescuento() }}</td>
                    <td class="der">− {{ lempiras($detalle->descuentoDeLinea()) }}</td>
                </tr>
            @endif
        @endforeach
        @if ((float) $venta->descuento_global > 0)
            <tr>
                <td>{{ $venta->etiquetaDescuentoGlobal() }}</td>
                <td class="der">− {{ lempiras((float) $venta->descuento_global) }}</td>
            </tr>
        @endif
    </table>

    <div class="linea"></div>

    <table>
        @if ((float) $venta->descuento > 0)
            <tr><td>Descuentos y rebajas</td><td class="der">{{ lempiras((float) $venta->descuento) }}</td></tr>
        @endif
        @if ($venta->esFactura())
            <tr><td>Importe exonerado</td><td class="der">{{ lempiras(0) }}</td></tr>
        @endif
        <tr><td>Importe exento</td><td class="der">{{ lempiras((float) $venta->importe_exento) }}</td></tr>
        <tr><td>Importe gravado 15 %</td><td class="der">{{ lempiras((float) $venta->importe_gravado_15) }}</td></tr>
        @if ((float) $venta->importe_gravado_18 > 0)
            <tr><td>Importe gravado 18 %</td><td class="der">{{ lempiras((float) $venta->importe_gravado_18) }}</td></tr>
        @endif
        <tr><td>ISV 15 %</td><td class="der">{{ lempiras((float) $venta->isv_15) }}</td></tr>
        @if ((float) $venta->isv_18 > 0)
            <tr><td>ISV 18 %</td><td class="der">{{ lempiras((float) $venta->isv_18) }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL</td><td class="der">{{ lempiras((float) $venta->total) }}</td></tr>
    </table>
    <div class="letras">{{ $venta->totalEnLetras() }}</div>

    <div class="linea"></div>

    <table>
        @foreach ($venta->pagos as $pago)
            <tr>
                <td>{{ $pago->metodo->getLabel() }}{{ filled($pago->referencia) ? ' ('.$pago->referencia.')' : '' }}</td>
                <td class="der">{{ lempiras((float) $pago->monto) }}</td>
            </tr>
        @endforeach
        @if ((float) $venta->efectivo_recibido > 0)
            <tr><td>Efectivo recibido</td><td class="der">{{ lempiras((float) $venta->efectivo_recibido) }}</td></tr>
            <tr class="negrita"><td>Cambio</td><td class="der">{{ lempiras((float) $venta->cambio) }}</td></tr>
        @endif
    </table>

    <div class="linea"></div>

    @if ($venta->esFactura())
        <div class="pequeno">N.º orden de compra exenta: __________</div>
        <div class="pequeno">N.º constancia de registro de exonerado: __________</div>
        <div class="pequeno">N.º registro de la SAG: __________</div>
        <div class="centro pequeno" style="margin-top: 4px;">La factura es beneficio de todos. ¡Exíjala!</div>
        <div class="centro pequeno">Original: Cliente</div>
    @else
        <div class="centro pequeno">Comprobante interno. No es una factura.</div>
    @endif

    @if (filled($empresa->mensaje_ticket))
        <div class="centro" style="margin-top: 6px;">{{ $empresa->mensaje_ticket }}</div>
    @endif
@endsection
