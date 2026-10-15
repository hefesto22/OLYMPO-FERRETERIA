@extends('tickets.layout')

@section('titulo', 'Cierre de turno')

@section('contenido')
    @php
        $diferencia = $turno->diferencia !== null ? (float) $turno->diferencia : null;
    @endphp

    <div class="centro">
        <div class="grande negrita">{{ $empresa->nombreVisible() }}</div>
        <div class="negrita">{{ $turno->estaAbierto() ? 'CORTE PARCIAL (TURNO ABIERTO)' : 'CIERRE DE TURNO' }}</div>
    </div>

    <div class="linea"></div>

    <table>
        <tr><td>Caja:</td><td class="der">{{ $turno->caja->nombre }}</td></tr>
        <tr><td>Cajero:</td><td class="der">{{ $turno->cajero->name }}</td></tr>
        <tr><td>Apertura:</td><td class="der">{{ $turno->abierto_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Cierre:</td><td class="der">{{ $turno->cerrado_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
        @if ($turno->cerradoPor !== null && $turno->cerrado_por !== $turno->user_id)
            <tr><td>Cerró:</td><td class="der">{{ $turno->cerradoPor->name }}</td></tr>
        @endif
    </table>

    <div class="linea"></div>

    <table>
        <tr><td>Ventas</td><td class="der">{{ $resumen['cantidad_ventas'] }}</td></tr>
        <tr><td>Facturas emitidas</td><td class="der">{{ $facturas['cantidad'] }}</td></tr>
        @if ($facturas['cantidad'] > 0)
            <tr><td colspan="2" class="pequeno sangria">Del {{ $facturas['primera'] }}</td></tr>
            <tr><td colspan="2" class="pequeno sangria">al {{ $facturas['ultima'] }}</td></tr>
        @endif
        <tr><td>Recibos</td><td class="der">{{ $recibos }}</td></tr>
        <tr><td>Anuladas</td><td class="der">{{ $anuladas }}</td></tr>
    </table>

    <div class="linea"></div>

    <table>
        <tr class="negrita"><td>Total vendido</td><td class="der">{{ lempiras($resumen['total_ventas']) }}</td></tr>
        <tr><td class="sangria">Efectivo</td><td class="der">{{ lempiras($resumen['total_efectivo']) }}</td></tr>
        <tr><td class="sangria">Tarjeta</td><td class="der">{{ lempiras($resumen['total_tarjeta']) }}</td></tr>
        <tr><td class="sangria">Transferencia</td><td class="der">{{ lempiras($resumen['total_transferencia']) }}</td></tr>
    </table>

    <div class="linea"></div>

    <table>
        <tr><td>Fondo inicial</td><td class="der">{{ lempiras((float) $turno->fondo_inicial) }}</td></tr>
        <tr><td>+ Ventas en efectivo</td><td class="der">{{ lempiras($resumen['total_efectivo']) }}</td></tr>
        <tr><td>+ Entradas de efectivo</td><td class="der">{{ lempiras($resumen['total_ingresos']) }}</td></tr>
        <tr><td>− Retiros de efectivo</td><td class="der">{{ lempiras($resumen['total_egresos']) }}</td></tr>
        <tr class="negrita"><td>Efectivo esperado</td><td class="der">{{ lempiras($resumen['efectivo_esperado']) }}</td></tr>
        @if ($turno->efectivo_contado !== null)
            <tr><td>Efectivo contado</td><td class="der">{{ lempiras((float) $turno->efectivo_contado) }}</td></tr>
        @endif
        @if ($diferencia !== null)
            <tr class="total">
                <td>{{ abs($diferencia) < 0.005 ? 'CUADRA' : ($diferencia < 0 ? 'FALTANTE' : 'SOBRANTE') }}</td>
                <td class="der">{{ lempiras(abs($diferencia)) }}</td>
            </tr>
        @endif
    </table>

    @if ($turno->movimientos->isNotEmpty())
        <div class="linea"></div>
        <div class="negrita">Entradas y retiros</div>
        <table>
            @foreach ($turno->movimientos as $movimiento)
                <tr>
                    <td class="pequeno">{{ $movimiento->created_at->format('H:i') }} {{ $movimiento->motivo }}</td>
                    <td class="der pequeno">{{ $movimiento->tipo === \App\Enums\TipoMovimientoCaja::Egreso ? '−' : '+' }} {{ lempiras((float) $movimiento->monto) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (filled($turno->notas_cierre))
        <div class="linea"></div>
        <div class="pequeno">Notas: {{ $turno->notas_cierre }}</div>
    @endif

    <div class="firma centro">______________________________</div>
    <div class="centro pequeno">Firma del cajero</div>
    <div class="firma centro">______________________________</div>
    <div class="centro pequeno">Firma de quien recibe</div>
@endsection
