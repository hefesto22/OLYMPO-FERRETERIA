<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntradasInventario\Tables;

use App\Enums\EstadoDocumento;
use App\Filament\Resources\EntradasInventario\Actions\AnularEntradaAction;
use App\Filament\Resources\EntradasInventario\Actions\ConfirmarEntradaAction;
use App\Models\EntradaInventario;
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

class EntradasInventarioTable
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
                    ->placeholder('Sin proveedor')
                    ->wrap(),
                TextColumn::make('referencia')
                    ->label('Referencia')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('detalles_count')
                    ->label('Productos')
                    ->counts('detalles')
                    ->alignEnd()
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
                        ->visible(fn (EntradaInventario $record): bool => $record->esBorrador()),
                    ConfirmarEntradaAction::make(),
                    AnularEntradaAction::make(),
                    DeleteAction::make()
                        ->visible(fn (EntradaInventario $record): bool => $record->esBorrador()),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['proveedor', 'createdBy']));
    }
}
