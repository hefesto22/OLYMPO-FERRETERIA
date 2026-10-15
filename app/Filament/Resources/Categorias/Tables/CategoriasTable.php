<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categorias\Tables;

use App\Models\Categoria;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Categoria $record): ?string => $record->parent?->nombre),
                TextColumn::make('productos_count')
                    ->label('Productos')
                    ->counts('productos')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('activo')
                    ->label('Activa'),
            ])
            ->defaultSort('nombre')
            ->filters([
                TernaryFilter::make('activo')
                    ->label('Estado')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),
                TrashedFilter::make()
                    ->label('Eliminadas'),
            ])
            ->recordActions([
                EditAction::make(),
                // Con productos no se elimina: se desactiva.
                DeleteAction::make()
                    ->hidden(fn (Categoria $record): bool => $record->productos()->exists()),
                RestoreAction::make(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('parent')
                ->withoutGlobalScopes([SoftDeletingScope::class]));
    }
}
