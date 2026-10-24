{{--
    Una opción del buscador de productos (entradas y ajustes): nombre,
    código y marca a la izquierda; cuánto hay a la derecha. Cerrado, el
    select solo deja ver el nombre y la unidad (ver olympo.css).
    Datos de App\Support\Inventario\OpcionesProducto::etiquetaHtml().
--}}
<span class="ol-producto">
    <span class="ol-producto__principal">
        <span class="ol-producto__nombre">{{ $producto->nombre }} <span class="ol-producto__unidad">{{ $unidad }}</span></span>
        @if (filled($producto->codigo) || $producto->marca !== null)
            <span class="ol-producto__meta">
                @if (filled($producto->codigo))
                    <span class="ol-producto__codigo">{{ $producto->codigo }}</span>
                @endif
                @if ($producto->marca !== null)
                    <span>{{ $producto->marca->nombre }}</span>
                @endif
            </span>
        @endif
    </span>
    @if ($existencia === null)
        <span class="ol-producto__existencia es-sin-control">
            <span class="ol-producto__nota">No lleva inventario</span>
        </span>
    @else
        <span @class(['ol-producto__existencia', 'es-agotado' => $existencia <= 0])>
            <span class="ol-producto__cantidad">{{ cantidadFormato($existencia) }} {{ $unidad }}</span>
            <span class="ol-producto__nota">{{ $existencia > 0 ? 'en existencia' : 'sin existencia' }}</span>
        </span>
    @endif
</span>
