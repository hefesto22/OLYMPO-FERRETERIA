<?php

declare(strict_types=1);

namespace App\Filament\Resources\UnidadesMedida\Tables;

use App\Models\UnidadMedida;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class UnidadesMedidaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Unidad')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('abreviatura')
                    ->label('Abreviatura')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                IconColumn::make('permite_decimales')
                    ->label('Fracciones')
                    ->boolean(),
                TextColumn::make('productos_count')
                    ->label('Productos')
                    ->counts('productos')
                    ->sortable()
                    ->alignEnd(),
                ToggleColumn::make('activo')
                    ->label('Activa'),
            ])
            ->defaultSort('nombre')
            ->recordActions([
                EditAction::make(),
                // Una unidad en uso no se borra (la base lo impide): se desactiva.
                DeleteAction::make()
                    ->hidden(fn (UnidadMedida $record): bool => $record->productos()->exists()),
            ]);
    }
}
