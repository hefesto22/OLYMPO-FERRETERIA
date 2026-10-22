<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\CambiosPrecio\CambioPrecioResource;
use App\Filament\Resources\Productos\ProductoResource;
use App\Filament\Resources\RangosCai\RangoCaiResource;
use App\Models\CambioPrecio;
use App\Models\Producto;
use App\Models\RangoCai;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Lo que necesita atención: precios por revisar, rangos CAI por vencer o
 * agotarse y productos por pedir. Cada parte se muestra según los permisos.
 */
class Pendientes extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.pendientes';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User
            && ($usuario->can('viewAny', Producto::class)
                || $usuario->can('viewAny', CambioPrecio::class)
                || $usuario->can('viewAny', RangoCai::class));
    }

    /**
     * @return array{
     *     precios: array{cantidad: int, url: string}|null,
     *     rangos: list<array{caja: string, estado: string, color: string, detalle: string}>|null,
     *     rangos_url: string,
     *     productos: list<array{nombre: string, existencia: string, minimo: string}>|null,
     *     productos_total: int,
     *     productos_url: string,
     * }
     */
    public function pendientes(): array
    {
        $usuario = Filament::auth()->user();
        $puede = static fn (string $modelo): bool => $usuario instanceof User && $usuario->can('viewAny', $modelo);

        $precios = $puede(CambioPrecio::class)
            ? ['cantidad' => CambioPrecio::query()->pendientes()->count(), 'url' => CambioPrecioResource::getUrl('index')]
            : null;

        $rangos = null;

        if ($puede(RangoCai::class)) {
            $rangos = array_values(RangoCai::query()
                ->with('caja')
                ->where('activo', true)
                ->orderBy('fecha_limite_emision')
                ->get()
                ->filter(fn (RangoCai $rango): bool => $rango->etiquetaEstado() !== 'Vigente')
                ->map(fn (RangoCai $rango): array => [
                    'caja'    => $rango->caja->nombre,
                    'estado'  => $rango->etiquetaEstado(),
                    'color'   => $rango->colorEstado(),
                    'detalle' => sprintf(
                        'Quedan %s números, vence el %s',
                        number_format(max(0, $rango->restantes())),
                        $rango->fecha_limite_emision->format('d/m/Y'),
                    ),
                ])
                ->all());
        }

        $productos = null;
        $productosTotal = 0;

        if ($puede(Producto::class)) {
            $productosTotal = Producto::query()->bajoMinimo()->count();
            $productos = array_values(Producto::query()
                ->bajoMinimo()
                ->with('unidadMedida')
                ->orderByRaw('stock - stock_minimo')
                ->limit(5)
                ->get()
                ->map(fn (Producto $producto): array => [
                    'nombre'     => $producto->nombre,
                    'existencia' => cantidadFormato((float) $producto->stock).' '.$producto->unidadMedida->abreviatura,
                    'minimo'     => cantidadFormato((float) $producto->stock_minimo),
                ])
                ->all());
        }

        return [
            'precios'         => $precios,
            'rangos'          => $rangos,
            'rangos_url'      => RangoCaiResource::getUrl('index'),
            'productos'       => $productos,
            'productos_total' => $productosTotal,
            'productos_url'   => ProductoResource::getUrl('index'),
        ];
    }
}
