{{--
    Cómo queda el producto de esta línea del ajuste: en un conteo, cuánto
    sobra o falta contra el sistema; en una salida, cuánto queda.
    Datos de App\Support\Inventario\ResumenAjuste::linea().
--}}
<div class="ol-ajuste">
    @if ($linea === null)
        <p class="ol-ajuste__detalle">
            @if (! $elegido)
                Elige el producto.
            @else
                {{ $tipo === 'conteo' ? 'Escribe lo que contaste.' : 'Escribe cuántos salen.' }}
            @endif
        </p>
    @elseif ($tipo === 'conteo')
        @if (abs($linea['cambio']) < 0.0000005)
            <p class="ol-ajuste__cambio es-igual">Sin cambio</p>
            <p class="ol-ajuste__detalle">Cuadra con el sistema</p>
        @else
            <p @class(['ol-ajuste__cambio', 'es-entra' => $linea['cambio'] > 0, 'es-sale' => $linea['cambio'] < 0])>
                {{ $linea['cambio'] > 0 ? 'Sobran' : 'Faltan' }} {{ cantidadFormato(abs($linea['cambio'])) }} {{ $linea['unidad'] }}
            </p>
            <p class="ol-ajuste__detalle">{{ lempiras($linea['valor']) }} al costo</p>
        @endif
    @else
        <p @class(['ol-ajuste__cambio', 'es-sale' => $linea['queda'] < 0])>
            {{ cantidadFormato($linea['queda']) }} {{ $linea['unidad'] }}
        </p>
        <p class="ol-ajuste__detalle">
            @if ($linea['queda'] < 0)
                No alcanza: solo hay {{ cantidadFormato($linea['existencia']) }} {{ $linea['unidad'] }}
            @else
                Salen {{ lempiras($linea['valor']) }} al costo
            @endif
        </p>
    @endif
</div>
