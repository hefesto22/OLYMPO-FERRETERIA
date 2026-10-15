<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovimientosInventario\Tables;

use App\Enums\TipoMovimientoInventario;
use App\Models\MovimientoInventario;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabla del kardex: la usan el recurso "Kardex" (todos los productos)
 * y la pestaña Kardex de la ficha de un producto.
 */
class MovimientosInventarioTable
{
    public static function configure(Table $table, bool $mostrarProducto = true): Table
    {
        $filtros = [
            SelectFilter::make('tipo')
                ->label('Movimiento')
                ->options(TipoMovimientoInventario::class)
                ->multiple(),
            Filter::make('fecha')
                ->schema([
                    DatePicker::make('desde')->label('Desde'),
                    DatePicker::make('hasta')->label('Hasta'),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['desde'] ?? null, fn (Builder $q, mixed $desde): Builder => $q->whereDate('created_at', '>=', $desde))
                    ->when($data['hasta'] ?? null, fn (Builder $q, mixed $hasta): Builder => $q->whereDate('created_at', '<=', $hasta))),
        ];

        if ($mostrarProducto) {
            array_unshift(
                $filtros,
                SelectFilter::make('producto')
                    ->label('Producto')
                    ->relationship('producto', 'nombre')
                    ->searchable(),
            );
        }

        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->description(fn (MovimientoInventario $record): string => $record->producto->codigo ?? '')
                    ->searchable()
                    ->wrap()
                    ->visible($mostrarProducto),
                TextColumn::make('tipo')
                    ->label('Movimiento')
                    ->badge(),
                TextColumn::make('descripcion')
                    ->label('Detalle')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('entrada')
                    ->label('Entrada')
                    ->state(fn (MovimientoInventario $record): ?string => self::cantidad($record->entrada()))
                    ->color('success')
                    ->alignEnd(),
                TextColumn::make('salida')
                    ->label('Salida')
                    ->state(fn (MovimientoInventario $record): ?string => self::cantidad($record->salida()))
                    ->color('danger')
                    ->alignEnd(),
                TextColumn::make('stock_nuevo')
                    ->label('Saldo')
                    ->formatStateUsing(fn (mixed $state): string => cantidadFormato((float) $state))
                    ->weight('bold')
                    ->alignEnd(),
                TextColumn::make('costo_unitario')
                    ->label('Costo unit.')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state, 4))
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('costo_promedio')
                    ->label('Costo prom.')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state, 4))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('usuario.name')
                    ->label('Usuario')
                    ->placeholder('Sistema')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters($filtros)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['producto', 'usuario']));
    }

    private static function cantidad(?float $valor): ?string
    {
        return $valor !== null ? cantidadFormato($valor) : null;
    }
}
