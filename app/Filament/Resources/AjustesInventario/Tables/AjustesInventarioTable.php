<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Tables;

use App\Enums\EstadoDocumento;
use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
use App\Filament\Resources\AjustesInventario\Actions\AplicarAjusteAction;
use App\Models\AjusteInventario;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AjustesInventarioTable
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
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('motivo')
                    ->label('Motivo'),
                TextColumn::make('detalles_count')
                    ->label('Productos')
                    ->counts('detalles')
                    ->alignEnd(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
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
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(TipoAjuste::class),
                SelectFilter::make('motivo')
                    ->label('Motivo')
                    ->options(MotivoAjuste::class),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn (AjusteInventario $record): bool => $record->esBorrador()),
                    AplicarAjusteAction::make(),
                    DeleteAction::make()
                        ->visible(fn (AjusteInventario $record): bool => $record->esBorrador()),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('createdBy'));
    }
}
