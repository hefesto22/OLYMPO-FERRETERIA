{{--
    Tarjeta del formulario de producto: el precio que va a quedar y de dónde
    sale (costo ponderado + ISV = lo que te sale, + margen, al lempira hacia
    arriba), la ganancia y lo que hay en existencia. Datos de
    ProductoForm::resumenPrecio().
--}}
@php
    $unidad = $resumen['unidad'] !== '' ? $resumen['unidad'] : 'unidad';
    $etiquetaCosto = $resumen['creando'] ? 'Costo inicial' : 'Costo ponderado';
@endphp

<div class="ol-precio">
    <div class="ol-precio__cabeza">
        <p class="ol-precio__etiqueta">Precio de venta</p>

        @if ($resumen['sin_costo'])
            <p class="ol-precio__sin-costo">Sin costo todavía</p>
            <p class="ol-precio__nota">
                {{ $resumen['creando'] ? 'Escribe el costo inicial abajo, en Inventario, para ver el precio.' : 'El precio sale con la primera entrada de inventario o la existencia inicial.' }}
            </p>
        @elseif ($resumen['precio'] === null)
            <p class="ol-precio__sin-costo">Escribe el precio</p>
        @else
            <p class="ol-precio__monto">{{ lempiras($resumen['precio']) }}</p>
            <p class="ol-precio__nota">
                Por {{ $unidad }}, ISV incluido.@if ($resumen['mayoreo'] !== null) Mayoreo {{ lempiras($resumen['mayoreo']) }}.@endif
            </p>
        @endif
    </div>

    @if ($resumen['con_margen'] && ! $resumen['sin_costo'])
        <dl class="ol-precio__pasos">
            <div>
                <dt>{{ $resumen['pendiente'] !== null ? 'Costo de los precios vigentes' : $etiquetaCosto }}</dt>
                <dd>{{ costoLempiras($resumen['costo']) }}</dd>
            </div>
            @if ($resumen['isv'] > 0)
                <div>
                    <dt>{{ $resumen['isv_texto'] }}</dt>
                    <dd>+ {{ lempiras($resumen['isv']) }}</dd>
                </div>
                <div class="es-subtotal">
                    <dt>Te sale en</dt>
                    <dd>{{ lempiras($resumen['con_isv']) }}</dd>
                </div>
            @endif
            <div>
                <dt>Margen de {{ $resumen['margen_texto'] }}</dt>
                <dd>+ {{ lempiras($resumen['margen']) }}</dd>
            </div>
            <div class="es-total">
                <dt>Al lempira hacia arriba</dt>
                <dd>{{ lempiras((float) $resumen['precio']) }}</dd>
            </div>
        </dl>
    @endif

    <dl class="ol-precio__datos">
        @if ($resumen['ganancia'] !== null)
            <div>
                <dt>Ganancia</dt>
                <dd @class(['es-negativa' => $resumen['ganancia'] < 0, 'es-positiva' => $resumen['ganancia'] >= 0])>
                    {{ $resumen['ganancia'] < 0 ? '−' : '+' }}{{ lempiras(abs($resumen['ganancia'])) }} por {{ $unidad }}, {{ porcentaje((float) $resumen['ganancia_fraccion'], 1) }}
                </dd>
            </div>
        @endif

        @if (! $resumen['con_margen'] && $resumen['costo_ponderado'] > 0)
            <div>
                <dt>{{ $etiquetaCosto }}</dt>
                <dd>{{ costoLempiras($resumen['costo_ponderado']) }}</dd>
            </div>
        @endif

        @if ($resumen['existencia'] !== null)
            <div>
                <dt>{{ $resumen['creando'] ? 'Existencia inicial' : 'En existencia' }}</dt>
                <dd>
                    {{ trim(cantidadFormato($resumen['existencia']).' '.$resumen['unidad']) }}@if ($resumen['valor_inventario'] !== null), valen {{ lempiras($resumen['valor_inventario']) }}@endif
                </dd>
            </div>
        @endif

        @if ($resumen['ultimo_costo'] !== null)
            <div>
                <dt>Último costo de entrada</dt>
                <dd>{{ costoLempiras($resumen['ultimo_costo']) }}</dd>
            </div>
        @endif
    </dl>

    @if ($resumen['pendiente'] !== null)
        <p class="ol-precio__aviso">
            El costo ponderado bajó a {{ costoLempiras($resumen['pendiente']) }}. El precio sigue sobre el costo anterior hasta que lo revises en Cambios de precio.
        </p>
    @endif
</div>
