<?php

declare(strict_types=1);

namespace App\Filament\Resources\RangosCai\Tables;

use App\Models\RangoCai;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RangosCaiTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('caja.nombre')
                    ->label('Caja')
                    ->weight('bold'),
                TextColumn::make('cai')
                    ->label('CAI')
                    ->copyable()
                    ->searchable()
                    ->fontFamily('mono')
                    ->size('sm'),
                TextColumn::make('rango')
                    ->label('Rango autorizado')
                    ->state(fn (RangoCai $record): string => $record->formatearNumero($record->rango_inicial).' al '.$record->formatearNumero($record->rango_final))
                    ->size('sm')
                    ->wrap(),
                TextColumn::make('siguiente')
                    ->label('Siguiente')
                    ->state(fn (RangoCai $record): string => $record->estaAgotado() ? '—' : $record->formatearNumero($record->siguiente))
                    ->description(fn (RangoCai $record): string => numeroFormato($record->restantes()).' disponibles')
                    ->size('sm'),
                TextColumn::make('fecha_limite_emision')
                    ->label('Fecha límite')
                    ->date('d/m/Y')
                    ->description(fn (RangoCai $record): string => $record->estaVencido()
                        ? 'Vencido'
                        : 'Faltan '.$record->diasParaVencer().' días')
                    ->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->state(fn (RangoCai $record): string => $record->etiquetaEstado())
                    ->badge()
                    ->color(fn (RangoCai $record): string => $record->colorEstado()),
                ToggleColumn::make('activo')
                    ->label('Activo'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('caja')
                    ->label('Caja')
                    ->relationship('caja', 'nombre'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (RangoCai $record): bool => $record->siguiente > $record->rango_inicial),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('caja'));
    }
}
