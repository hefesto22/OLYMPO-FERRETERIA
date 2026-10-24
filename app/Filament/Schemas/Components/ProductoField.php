<?php

declare(strict_types=1);

namespace App\Filament\Schemas\Components;

use App\Models\Producto;
use App\Support\Inventario\OpcionesProducto;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * Buscador de productos activos para las líneas de entradas y ajustes:
 * busca por nombre, código o código de barras, y cada resultado muestra
 * el código, la marca y cuánto hay. La línea debe tener la relación
 * `producto`.
 *
 * Uso:
 *   ProductoField::make()                             // los que llevan existencia
 *   ProductoField::make(soloConInventario: false)     // también servicios (punto de venta)
 */
final class ProductoField
{
    public static function make(string $name = 'producto_id', bool $soloConInventario = true): Select
    {
        return Select::make($name)
            ->label('Producto')
            ->relationship('producto', 'nombre', fn (Builder $query): Builder => $query
                ->where('activo', true)
                ->when($soloConInventario, fn (Builder $query): Builder => $query->where('controla_stock', true))
                ->with(['unidadMedida', 'marca']))
            ->getOptionLabelFromRecordUsing(fn (Producto $record): string => OpcionesProducto::etiquetaHtml($record))
            ->allowHtml()
            ->searchable(['nombre', 'codigo', 'codigo_barras'])
            ->placeholder('Buscar producto')
            ->searchPrompt('Nombre, código o código de barras')
            ->searchingMessage('Buscando…')
            ->noSearchResultsMessage('Ningún producto coincide con lo que escribiste.');
    }
}
