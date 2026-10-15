<?php

declare(strict_types=1);

namespace App\Filament\Resources\Productos\Tables;

use App\Filament\Resources\Productos\ProductoResource;
use App\Filament\Resources\Productos\Schemas\ProductoInfolist;
use App\Models\Producto;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('nombre')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap()
                    ->description(fn (Producto $record): string => collect([$record->marca?->nombre, $record->categoria?->nombre])
                        ->filter()
                        ->implode(' · ')),
                TextColumn::make('codigo_barras')
                    ->label('Código de barras')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unidadMedida.abreviatura')
                    ->label('Unidad'),
                TextColumn::make('precio_venta')
                    ->label('Precio')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('precio_mayoreo')
                    ->label('Mayoreo')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stock')
                    ->label('Existencia')
                    ->formatStateUsing(fn (mixed $state, Producto $record): string => $record->controla_stock
                        ? cantidadFormato((float) $state)
                        : 'Servicio')
                    ->badge()
                    ->color(fn (Producto $record): string => ProductoInfolist::colorExistencia($record))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('costo_promedio')
                    ->label('Costo prom.')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state, 4))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ubicacion')
                    ->label('Ubicación')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->toggleable(),
            ])
            ->defaultSort('nombre')
            ->recordUrl(fn (Producto $record): string => ProductoResource::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('categoria')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('marca')
                    ->label('Marca')
                    ->relationship('marca', 'nombre')
                    ->searchable()
                    ->preload(),
                Filter::make('bajo_minimo')
                    ->label('En o bajo el mínimo')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('controla_stock', true)
                        ->whereColumn('stock', '<=', 'stock_minimo')),
                Filter::make('sin_existencia')
                    ->label('Sin existencia')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('controla_stock', true)
                        ->where('stock', '<=', 0)),
                TernaryFilter::make('activo')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
                TrashedFilter::make()
                    ->label('Eliminados'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['marca', 'categoria', 'unidadMedida'])
                ->withoutGlobalScopes([SoftDeletingScope::class]));
    }
}
