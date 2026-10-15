<?php

declare(strict_types=1);

namespace App\Filament\Resources\Marcas\Tables;

use App\Models\Marca;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MarcasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Marca')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('productos_count')
                    ->label('Productos')
                    ->counts('productos')
                    ->sortable()
                    ->alignEnd(),
                ToggleColumn::make('activo')
                    ->label('Activa'),
            ])
            ->defaultSort('nombre')
            ->filters([
                TrashedFilter::make()
                    ->label('Eliminadas'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Marca $record): bool => $record->productos()->exists()),
                RestoreAction::make(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScopes([SoftDeletingScope::class]));
    }
}
