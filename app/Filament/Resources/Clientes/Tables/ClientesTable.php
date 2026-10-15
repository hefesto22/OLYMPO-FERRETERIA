<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clientes\Tables;

use App\Enums\ListaPrecio;
use App\Models\Cliente;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Cliente $record): ?string => $record->rtn !== null ? 'RTN '.$record->rtn : null),
                TextColumn::make('rtn')
                    ->label('RTN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->formatStateUsing(fn (?string $state): string => telefonoHN($state))
                    ->placeholder('—'),
                TextColumn::make('tipo_precio')
                    ->label('Precio')
                    ->badge(),
                IconColumn::make('permite_credito')
                    ->label('Crédito')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('ventas_count')
                    ->label('Compras')
                    ->counts('ventas')
                    ->sortable()
                    ->alignEnd(),
                ToggleColumn::make('activo')
                    ->label('Activo'),
            ])
            ->defaultSort('nombre')
            ->filters([
                SelectFilter::make('tipo_precio')
                    ->label('Precio')
                    ->options(ListaPrecio::class),
                TrashedFilter::make()
                    ->label('Eliminados'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Cliente $record): bool => $record->ventas()->exists()),
                RestoreAction::make(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScopes([SoftDeletingScope::class]));
    }
}
