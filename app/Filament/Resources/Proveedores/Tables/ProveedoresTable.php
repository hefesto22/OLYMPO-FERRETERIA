<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proveedores\Tables;

use App\Models\Proveedor;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProveedoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Proveedor $record): ?string => $record->contacto),
                TextColumn::make('rtn')
                    ->label('RTN')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->formatStateUsing(fn (?string $state): string => telefonoHN($state))
                    ->placeholder('—'),
                TextColumn::make('dias_credito')
                    ->label('Crédito')
                    ->formatStateUsing(fn (mixed $state): string => (int) $state > 0 ? ((int) $state).' días' : 'Contado')
                    ->toggleable(),
                TextColumn::make('compras_count')
                    ->label('Compras')
                    ->counts('compras')
                    ->sortable()
                    ->alignEnd(),
                ToggleColumn::make('activo')
                    ->label('Activo'),
            ])
            ->defaultSort('nombre')
            ->filters([
                TrashedFilter::make()
                    ->label('Eliminados'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Proveedor $record): bool => $record->tieneCompras()),
                RestoreAction::make(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScopes([SoftDeletingScope::class]));
    }
}
