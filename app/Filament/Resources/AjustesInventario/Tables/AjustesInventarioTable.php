<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Tables;

use App\Enums\EstadoDocumento;
use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
use App\Filament\Resources\AjustesInventario\Actions\AplicarAjusteAction;
use App\Models\AjusteInventario;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
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
                TextColumn::make('motivo')
                    ->label('Qué pasó')
                    ->icon(fn (AjusteInventario $record): Heroicon => $record->motivo->getIcon()),
                TextColumn::make('tipo')
                    ->label('Movimiento')
                    ->badge(),
                TextColumn::make('detalles_count')
                    ->label('Productos')
                    ->counts('detalles')
                    ->alignEnd(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('createdBy.name')
                    ->label('Registró')
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoDocumento::class),
                SelectFilter::make('motivo')
                    ->label('Qué pasó')
                    ->options(MotivoAjuste::class),
                SelectFilter::make('tipo')
                    ->label('Movimiento')
                    ->options(TipoAjuste::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (AjusteInventario $record): string => "Ajuste {$record->numero}")
                    ->modalWidth(Width::FourExtraLarge),
                ActionGroup::make([
                    EditAction::make()
                        ->label('Editar borrador')
                        ->modalHeading(fn (AjusteInventario $record): string => "Editar el ajuste {$record->numero}")
                        ->modalWidth(Width::FiveExtraLarge)
                        ->modalSubmitActionLabel('Guardar borrador')
                        ->modalSubmitAction(fn (Action $action): Action => $action->color(AplicarAjusteAction::puedeAplicar() ? 'gray' : 'primary'))
                        ->extraModalFooterActions(fn (EditAction $action): array => AplicarAjusteAction::puedeAplicar()
                            ? [
                                $action->makeModalSubmitAction('guardarYAplicar', arguments: ['aplicar' => true])
                                    ->label('Guardar y aplicar'),
                            ]
                            : [])
                        ->after(function (AjusteInventario $record, array $arguments): void {
                            if ($arguments['aplicar'] ?? false) {
                                AplicarAjusteAction::aplicar($record);
                            }
                        })
                        ->visible(fn (AjusteInventario $record): bool => $record->esBorrador()),
                    AplicarAjusteAction::make(),
                    DeleteAction::make()
                        ->visible(fn (AjusteInventario $record): bool => $record->esBorrador()),
                ]),
            ])
            ->emptyStateHeading('Todavía no hay ajustes')
            ->emptyStateDescription('Usa un ajuste para el inventario inicial, un conteo físico, mermas, robos o lo que se usó en la tienda. La mercadería comprada se carga en Entradas de inventario.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('createdBy'));
    }
}
