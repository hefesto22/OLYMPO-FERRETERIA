<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Tables;

use App\Enums\EstadoDocumento;
use App\Filament\Resources\Compras\Actions\AnularCompraAction;
use App\Filament\Resources\Compras\Actions\ConfirmarCompraAction;
use App\Models\Compra;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ComprasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')
                    ->label('Número')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('numero_factura_proveedor')
                    ->label('Factura proveedor')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('createdBy.name')
                    ->label('Registró')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoDocumento::class),
                SelectFilter::make('proveedor')
                    ->label('Proveedor')
                    ->relationship('proveedor', 'nombre')
                    ->searchable()
                    ->preload(),
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, mixed $desde): Builder => $q->whereDate('fecha', '>=', $desde))
                        ->when($data['hasta'] ?? null, fn (Builder $q, mixed $hasta): Builder => $q->whereDate('fecha', '<=', $hasta))),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn (Compra $record): bool => $record->esBorrador()),
                    ConfirmarCompraAction::make(),
                    AnularCompraAction::make(),
                    DeleteAction::make()
                        ->visible(fn (Compra $record): bool => $record->esBorrador()),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['proveedor', 'createdBy']));
    }
}
