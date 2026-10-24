{{--
    Cuánto vale el ajuste al costo, a la derecha, antes de aplicarlo.
    Datos de App\Support\Inventario\ResumenAjuste::desdeFormulario().
--}}
@php
    $productos = $resumen['productos'] === 1 ? '1 producto' : $resumen['productos'].' productos';
@endphp

@if ($resumen['productos'] === 0)
    <p class="ol-ajuste-resumen__vacio">Agrega los productos para ver cuánto vale el ajuste.</p>
@elseif ($resumen['tipo'] === 'conteo')
    <dl class="ol-totales">
        @if ($resumen['suben'] > 0)
            <div>
                <dt>Sobran en {{ $resumen['suben'] === 1 ? '1 producto' : $resumen['suben'].' productos' }}</dt>
                <dd>+{{ lempiras($resumen['entra']) }}</dd>
            </div>
        @endif
        @if ($resumen['bajan'] > 0)
            <div>
                <dt>Faltan en {{ $resumen['bajan'] === 1 ? '1 producto' : $resumen['bajan'].' productos' }}</dt>
                <dd>−{{ lempiras($resumen['sale']) }}</dd>
            </div>
        @endif
        @if ($resumen['iguales'] > 0)
            <div>
                <dt>Cuadran</dt>
                <dd>{{ $resumen['iguales'] === 1 ? '1 producto' : $resumen['iguales'].' productos' }}</dd>
            </div>
        @endif
        <div class="es-total">
            <dt>Diferencia al costo</dt>
            <dd>{{ $resumen['entra'] - $resumen['sale'] < 0 ? '−' : '+' }}{{ lempiras(abs($resumen['entra'] - $resumen['sale'])) }}</dd>
        </div>
    </dl>
@elseif ($resumen['tipo'] === 'salida')
    <dl class="ol-totales">
        <div>
            <dt>Salen</dt>
            <dd>{{ $productos }}</dd>
        </div>
        <div class="es-total">
            <dt>Valor al costo</dt>
            <dd>−{{ lempiras($resumen['sale']) }}</dd>
        </div>
    </dl>
@else
    <dl class="ol-totales">
        <div>
            <dt>Entran</dt>
            <dd>{{ $productos }}</dd>
        </div>
        <div class="es-total">
            <dt>Valor al costo</dt>
            <dd>+{{ lempiras($resumen['entra']) }}</dd>
        </div>
    </dl>
@endif
