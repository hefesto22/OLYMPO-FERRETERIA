{{--
    Cómo queda el producto de esta línea si se confirma la compra:
    lo que hay en existencia + lo que entra = costo ponderado nuevo, y el
    precio de venta que resulta. Datos de App\Support\Inventario\ProyeccionCompra.
--}}
@use('App\Support\Inventario\ProyeccionCompra')

@php
    $textoPrecio = null;

    if ($linea !== null) {
        $textoPrecio = match ($linea['estado']) {
            ProyeccionCompra::SUBE   => 'Sube de '.lempiras($linea['precio_antes']),
            ProyeccionCompra::BAJA   => 'Bajaría a '.lempiras((float) $linea['precio_propuesto']).': tú decides',
            ProyeccionCompra::NUEVO  => 'Primer precio',
            ProyeccionCompra::MANUAL => 'A mano, no cambia',
            default                  => 'Sigue igual',
        };
    }
@endphp

<div class="ol-proyeccion">
    @if ($linea === null)
        <p class="ol-proyeccion__vacio">Elige el producto.</p>
    @elseif ($linea['estado'] === ProyeccionCompra::SERVICIO)
        <p class="ol-proyeccion__vacio">Servicio: no lleva existencia.</p>
    @elseif ($linea['entra'] <= 0)
        <p class="ol-proyeccion__vacio">
            Hay {{ cantidadFormato($linea['existencia']) }} {{ $linea['unidad'] }} a {{ costoLempiras($linea['costo_antes']) }}. Escribe la cantidad.
        </p>
    @else
        <p class="ol-proyeccion__cuenta">
            @if ($linea['existencia'] > 0)
                <span>{{ cantidadFormato($linea['existencia']) }} {{ $linea['unidad'] }} a {{ costoLempiras($linea['costo_antes']) }}</span>
                <span class="ol-proyeccion__signo">+</span>
            @else
                <span>Sin existencia</span>
                <span class="ol-proyeccion__signo">+</span>
            @endif
            <span>{{ cantidadFormato($linea['entra']) }} {{ $linea['unidad'] }} a {{ costoLempiras($linea['costo_entrada']) }}</span>
        </p>
        <p class="ol-proyeccion__resultado">
            <span>Costo <strong>{{ costoLempiras($linea['costo_nuevo']) }}</strong> por {{ $linea['unidad'] }}</span>
            <span>Precio <strong>{{ lempiras($linea['precio_nuevo']) }}</strong></span>
            <span @class(['ol-proyeccion__estado', 'es-'.$linea['estado']])>{{ $textoPrecio }}</span>
        </p>
    @endif
</div>
