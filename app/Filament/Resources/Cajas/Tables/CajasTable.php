<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cajas\Tables;

use App\Models\Caja;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CajasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Caja')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('punto_emision')
                    ->label('Punto de emisión')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('turno')
                    ->label('Turno')
                    ->state(fn (Caja $record): string => $record->turnoAbierto !== null
                        ? 'Abierto · '.$record->turnoAbierto->cajero->name
                        : 'Libre')
                    ->badge()
                    ->color(fn (Caja $record): string => $record->turnoAbierto !== null ? 'success' : 'gray'),
                TextColumn::make('cai')
                    ->label('CAI activo')
                    ->state(fn (Caja $record): string => $record->rangoActivo !== null
                        ? $record->rangoActivo->etiquetaEstado().' · '.numeroFormato($record->rangoActivo->restantes()).' facturas'
                        : 'Sin CAI')
                    ->badge()
                    ->color(fn (Caja $record): string => $record->rangoActivo?->colorEstado() ?? 'danger'),
                ToggleColumn::make('activo')
                    ->label('Activa'),
            ])
            ->defaultSort('punto_emision')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Caja $record): bool => $record->turnos()->exists() || $record->rangosCai()->exists()),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['turnoAbierto.cajero', 'rangoActivo']));
    }
}
