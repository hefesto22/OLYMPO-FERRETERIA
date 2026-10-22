@php
    $pendientes = $this->pendientes();
    $precios = $pendientes['precios'];
    $rangos = $pendientes['rangos'];
    $productos = $pendientes['productos'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="Pendientes" icon="heroicon-o-bell-alert">
        <ul class="ol-pendientes">
            @if ($precios !== null)
                <li>
                    <a href="{{ $precios['url'] }}" class="ol-pendiente">
                        <span @class(['ol-pendiente__icono', 'es-aviso' => $precios['cantidad'] > 0])>
                            <x-filament::icon icon="heroicon-o-tag" />
                        </span>
                        <span class="ol-pendiente__texto">
                            <strong>Precios por revisar</strong>
                            <span>
                                @if ($precios['cantidad'] === 0)
                                    Ninguno por ahora
                                @elseif ($precios['cantidad'] === 1)
                                    Bajó el costo de 1 producto
                                @else
                                    Bajó el costo de {{ $precios['cantidad'] }} productos
                                @endif
                            </span>
                        </span>
                        @if ($precios['cantidad'] > 0)
                            <x-filament::badge color="warning">{{ $precios['cantidad'] }}</x-filament::badge>
                        @endif
                    </a>
                </li>
            @endif

            @if ($rangos !== null)
                @forelse ($rangos as $rango)
                    <li>
                        <a href="{{ $pendientes['rangos_url'] }}" class="ol-pendiente">
                            <span class="ol-pendiente__icono es-alerta">
                                <x-filament::icon icon="heroicon-o-document-text" />
                            </span>
                            <span class="ol-pendiente__texto">
                                <strong>CAI de {{ $rango['caja'] }}</strong>
                                <span>{{ $rango['detalle'] }}</span>
                            </span>
                            <x-filament::badge :color="$rango['color']">{{ $rango['estado'] }}</x-filament::badge>
                        </a>
                    </li>
                @empty
                    <li>
                        <a href="{{ $pendientes['rangos_url'] }}" class="ol-pendiente">
                            <span class="ol-pendiente__icono">
                                <x-filament::icon icon="heroicon-o-document-check" />
                            </span>
                            <span class="ol-pendiente__texto">
                                <strong>Rangos CAI</strong>
                                <span>Todos vigentes</span>
                            </span>
                        </a>
                    </li>
                @endforelse
            @endif

            @if ($productos !== null)
                <li>
                    <a href="{{ $pendientes['productos_url'] }}" class="ol-pendiente">
                        <span @class(['ol-pendiente__icono', 'es-aviso' => $pendientes['productos_total'] > 0])>
                            <x-filament::icon icon="heroicon-o-cube" />
                        </span>
                        <span class="ol-pendiente__texto">
                            <strong>Productos por pedir</strong>
                            <span>
                                @if ($pendientes['productos_total'] === 0)
                                    Todo con existencia
                                @else
                                    En su existencia mínima o debajo
                                @endif
                            </span>
                        </span>
                        @if ($pendientes['productos_total'] > 0)
                            <x-filament::badge color="warning">{{ $pendientes['productos_total'] }}</x-filament::badge>
                        @endif
                    </a>

                    @if (count($productos) > 0)
                        <ul class="ol-por-pedir">
                            @foreach ($productos as $producto)
                                <li>
                                    <span class="ol-por-pedir__nombre">{{ $producto['nombre'] }}</span>
                                    <span class="ol-por-pedir__cifra">{{ $producto['existencia'] }} <small>mín. {{ $producto['minimo'] }}</small></span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endif
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
