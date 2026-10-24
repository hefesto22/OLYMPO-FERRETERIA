{{--
    Cuánto vale el ajuste, a la derecha, antes de aplicarlo: al costo neto
    y con el ISV que se pagó al comprar (una merma se pierde con todo y
    su ISV). $palabraSalida: "Pérdida" o "Gasto".
    Datos de App\Support\Inventario\ResumenAjuste::desdeFormulario().
--}}
@php
    $productos = $resumen['productos'] === 1 ? '1 producto' : $resumen['productos'].' productos';
    $entra = $resumen['entra'] + $resumen['entra_isv'];
    $sale = $resumen['sale'] + $resumen['sale_isv'];
    $diferencia = round($entra - $sale, 2);
@endphp

@if ($resumen['productos'] === 0)
    <p class="ol-ajuste-resumen__vacio">Agrega los productos para ver cuánto vale el ajuste.</p>
@elseif ($resumen['tipo'] === 'conteo')
    <dl class="ol-totales">
        @if ($resumen['suben'] > 0)
            <div>
                <dt>Sobran en {{ $resumen['suben'] === 1 ? '1 producto' : $resumen['suben'].' productos' }}</dt>
                <dd>+{{ lempiras($entra) }}</dd>
            </div>
        @endif
        @if ($resumen['bajan'] > 0)
            <div>
                <dt>Faltan en {{ $resumen['bajan'] === 1 ? '1 producto' : $resumen['bajan'].' productos' }}</dt>
                <dd>−{{ lempiras($sale) }}</dd>
            </div>
        @endif
        @if ($resumen['iguales'] > 0)
            <div>
                <dt>Cuadran</dt>
                <dd>{{ $resumen['iguales'] === 1 ? '1 producto' : $resumen['iguales'].' productos' }}</dd>
            </div>
        @endif
        <div class="es-total">
            <dt>Diferencia con ISV</dt>
            <dd>{{ $diferencia < 0 ? '−' : '+' }}{{ lempiras(abs($diferencia)) }}</dd>
        </div>
    </dl>
@elseif ($resumen['tipo'] === 'salida')
    <dl class="ol-totales">
        <div>
            <dt>Salen</dt>
            <dd>{{ $productos }}</dd>
        </div>
        <div>
            <dt>Costo sin ISV</dt>
            <dd>{{ lempiras($resumen['sale']) }}</dd>
        </div>
        <div>
            <dt>ISV que se pagó</dt>
            <dd>{{ lempiras($resumen['sale_isv']) }}</dd>
        </div>
        <div class="es-total">
            <dt>{{ $palabraSalida }} con ISV</dt>
            <dd>−{{ lempiras($sale) }}</dd>
        </div>
    </dl>
@else
    <dl class="ol-totales">
        <div>
            <dt>Entran</dt>
            <dd>{{ $productos }}</dd>
        </div>
        <div>
            <dt>Costo sin ISV</dt>
            <dd>{{ lempiras($resumen['entra']) }}</dd>
        </div>
        <div>
            <dt>ISV que se pagó</dt>
            <dd>{{ lempiras($resumen['entra_isv']) }}</dd>
        </div>
        <div class="es-total">
            <dt>Valor con ISV</dt>
            <dd>+{{ lempiras($entra) }}</dd>
        </div>
    </dl>
@endif
