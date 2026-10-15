@php
    $turno = $this->turno();
    $carrito = $this->carrito;
    $totales = $this->totales;
    $cobro = $this->cobro();
    $descuentoMaximo = $this->descuentoMaximo();
@endphp

<x-filament-panels::page>
    <div
        x-data
        x-on:keydown.f2.window.prevent="$refs.busqueda?.focus()"
        x-on:keydown.f9.window.prevent="$wire.cobrar()"
        x-on:enfocar-busqueda.window="$nextTick(() => $refs.busqueda?.focus())"
        x-on:imprimir-ticket.window="
            document.getElementById('ticket-frame')?.remove();
            const marco = document.createElement('iframe');
            marco.id = 'ticket-frame';
            marco.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
            marco.src = $event.detail.url;
            document.body.appendChild(marco);
        "
    >
        @if ($turno === null)
            <x-filament::section>
                <div class="flex flex-col items-center gap-3 py-12 text-center">
                    <x-filament::icon icon="heroicon-o-lock-closed" class="h-12 w-12 text-gray-400" />
                    <p class="text-lg font-semibold">No tienes un turno de caja abierto</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Usa el botón «Abrir turno» de arriba para empezar a vender.</p>
                </div>
            </x-filament::section>
        @else
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                {{-- ── Buscador y carrito ─────────────────────────────── --}}
                <div class="space-y-6 xl:col-span-2">
                    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <x-filament::badge color="success" icon="heroicon-o-lock-open">
                            {{ $turno->caja->nombre }}
                        </x-filament::badge>
                        <span>Turno desde {{ $turno->abierto_at->format('d/m/Y H:i') }} · {{ $turno->cajero->name }}</span>
                    </div>

                    <x-filament::section>
                        <div class="relative">
                            <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                                <x-filament::input
                                    type="text"
                                    x-ref="busqueda"
                                    wire:model.live.debounce.250ms="busqueda"
                                    wire:keydown.enter.prevent="agregarPorCodigo"
                                    x-on:keydown.escape="$wire.set('busqueda', '')"
                                    placeholder="Escanea el código de barras o escribe el nombre del producto (F2)"
                                    autocomplete="off"
                                    autofocus
                                />
                            </x-filament::input.wrapper>

                            @if (count($resultados) > 0)
                                <div class="absolute z-20 mt-2 max-h-96 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-900">
                                    @foreach ($resultados as $resultado)
                                        <button
                                            type="button"
                                            wire:key="resultado-{{ $resultado['producto_id'] }}"
                                            wire:click="agregar({{ $resultado['producto_id'] }})"
                                            class="flex w-full items-center justify-between gap-4 px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-white/5"
                                        >
                                            <span>
                                                <span class="block font-medium">{{ $resultado['etiqueta'] }}</span>
                                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $resultado['detalle'] }}</span>
                                            </span>
                                            <span class="whitespace-nowrap font-semibold">{{ lempiras($resultado['precio']) }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </x-filament::section>

                    <x-filament::section :heading="'Productos (' . count($carrito) . ')'">
                        @if (count($carrito) === 0)
                            <div class="flex flex-col items-center gap-2 py-10 text-center text-gray-500 dark:text-gray-400">
                                <x-filament::icon icon="heroicon-o-shopping-cart" class="h-10 w-10" />
                                <p>Escanea o busca un producto para agregarlo.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                                            <th class="py-2 pr-3 font-medium">Producto</th>
                                            <th class="py-2 pr-3 font-medium">Presentación</th>
                                            <th class="py-2 pr-3 text-center font-medium">Cantidad</th>
                                            <th class="py-2 pr-3 text-right font-medium">Precio</th>
                                            <th class="py-2 pr-3 text-right font-medium">Desc. %</th>
                                            <th class="py-2 pr-3 text-right font-medium">Total</th>
                                            <th class="py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                        @foreach ($carrito as $linea)
                                            <tr wire:key="linea-{{ $linea['clave'] }}">
                                                <td class="py-3 pr-3 align-middle">
                                                    <div class="font-medium">{{ $linea['producto']->nombre }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $linea['producto']->codigo }}
                                                        @if ($linea['existencia'] !== null)
                                                            · Existencia: {{ cantidadFormato($linea['existencia']) }} {{ $linea['producto']->unidadMedida->abreviatura }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="py-3 pr-3 align-middle">
                                                    @if (count($linea['opciones']) > 0)
                                                        <x-filament::input.wrapper>
                                                            <x-filament::input.select wire:model.live="lineas.{{ $linea['clave'] }}.presentacion_id">
                                                                <option value="">{{ $linea['unidad_base'] }}</option>
                                                                @foreach ($linea['opciones'] as $presentacionId => $etiqueta)
                                                                    <option value="{{ $presentacionId }}">{{ $etiqueta }}</option>
                                                                @endforeach
                                                            </x-filament::input.select>
                                                        </x-filament::input.wrapper>
                                                    @else
                                                        <span class="text-gray-500 dark:text-gray-400">{{ $linea['unidad_base'] }}</span>
                                                    @endif
                                                </td>
                                                <td class="py-3 pr-3 align-middle">
                                                    <div class="flex items-center justify-center gap-1">
                                                        <x-filament::icon-button
                                                            icon="heroicon-o-minus"
                                                            color="gray"
                                                            label="Uno menos"
                                                            wire:click="sumar('{{ $linea['clave'] }}', -1)"
                                                        />
                                                        <div class="w-24">
                                                            <x-filament::input.wrapper>
                                                                <x-filament::input
                                                                    type="number"
                                                                    step="any"
                                                                    min="0"
                                                                    class="text-center"
                                                                    wire:model.live.debounce.500ms="lineas.{{ $linea['clave'] }}.cantidad"
                                                                />
                                                            </x-filament::input.wrapper>
                                                        </div>
                                                        <x-filament::icon-button
                                                            icon="heroicon-o-plus"
                                                            color="gray"
                                                            label="Uno más"
                                                            wire:click="sumar('{{ $linea['clave'] }}', 1)"
                                                        />
                                                    </div>
                                                </td>
                                                <td class="whitespace-nowrap py-3 pr-3 text-right align-middle">
                                                    {{ lempiras($linea['precio']) }}
                                                </td>
                                                <td class="py-3 pr-3 align-middle">
                                                    <div class="ml-auto w-20">
                                                        <x-filament::input.wrapper :disabled="$descuentoMaximo <= 0">
                                                            <x-filament::input
                                                                type="number"
                                                                step="any"
                                                                min="0"
                                                                :max="$descuentoMaximo"
                                                                class="text-right"
                                                                :disabled="$descuentoMaximo <= 0"
                                                                wire:model.live.debounce.500ms="lineas.{{ $linea['clave'] }}.descuento"
                                                            />
                                                        </x-filament::input.wrapper>
                                                    </div>
                                                </td>
                                                <td class="whitespace-nowrap py-3 pr-3 text-right align-middle font-semibold">
                                                    {{ lempiras($linea['total']) }}
                                                    @if ($linea['descuento'] > 0)
                                                        <div class="text-xs font-normal text-danger-600 dark:text-danger-400">− {{ lempiras($linea['descuento']) }}</div>
                                                    @endif
                                                </td>
                                                <td class="py-3 text-right align-middle">
                                                    <x-filament::icon-button
                                                        icon="heroicon-o-trash"
                                                        color="danger"
                                                        label="Quitar"
                                                        wire:click="quitar('{{ $linea['clave'] }}')"
                                                    />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-filament::section>
                </div>

                {{-- ── Documento, cliente, pago y total ──────────────── --}}
                <div class="space-y-6">
                    <x-filament::section heading="Venta">
                        {{ $this->form }}
                    </x-filament::section>

                    <x-filament::section>
                        <dl class="space-y-1.5 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Subtotal (sin ISV)</dt>
                                <dd>{{ lempiras($totales['subtotal']) }}</dd>
                            </div>
                            @if ($totales['descuento'] > 0)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">Descuentos</dt>
                                    <dd class="text-danger-600 dark:text-danger-400">− {{ lempiras($totales['descuento']) }}</dd>
                                </div>
                            @endif
                            @if ($totales['importe_exento'] > 0)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">Importe exento</dt>
                                    <dd>{{ lempiras($totales['importe_exento']) }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">ISV 15 %</dt>
                                <dd>{{ lempiras($totales['isv_15']) }}</dd>
                            </div>
                            @if ($totales['isv_18'] > 0)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">ISV 18 %</dt>
                                    <dd>{{ lempiras($totales['isv_18']) }}</dd>
                                </div>
                            @endif
                        </dl>

                        <div class="mt-4 flex items-end justify-between border-t border-gray-200 pt-4 dark:border-white/10">
                            <span class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total</span>
                            <span class="text-4xl font-bold tracking-tight">{{ lempiras($totales['total']) }}</span>
                        </div>

                        @if ($cobro['cambio'] !== null && $totales['total'] > 0)
                            <div @class([
                                'mt-3 flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold',
                                'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $cobro['cambio'] >= 0,
                                'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $cobro['cambio'] < 0,
                            ])>
                                <span>{{ $cobro['cambio'] >= 0 ? 'Cambio' : 'Falta' }}</span>
                                <span class="text-lg">{{ lempiras(abs($cobro['cambio'])) }}</span>
                            </div>
                        @endif

                        <div class="mt-4">
                            <x-filament::button
                                wire:click="cobrar"
                                wire:loading.attr="disabled"
                                wire:target="cobrar"
                                size="xl"
                                icon="heroicon-o-banknotes"
                                class="w-full"
                                :disabled="count($carrito) === 0"
                            >
                                Cobrar (F9)
                            </x-filament::button>
                        </div>

                        <div class="mt-3 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <x-filament::link tag="button" color="gray" size="sm" wire:click="vaciar">
                                Vaciar venta
                            </x-filament::link>
                            @if ($descuentoMaximo < 100)
                                <span>Descuento máximo por línea: {{ cantidadFormato($descuentoMaximo, 2) }} %</span>
                            @endif
                        </div>
                    </x-filament::section>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
