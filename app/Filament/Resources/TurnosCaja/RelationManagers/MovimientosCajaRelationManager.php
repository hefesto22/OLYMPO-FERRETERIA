<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnosCaja\RelationManagers;

use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MovimientosCajaRelationManager extends RelationManager
{
    protected static string $relationship = 'movimientos';

    protected static ?string $title = 'Entradas y retiros de efectivo';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedArrowsUpDown;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Hora')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('monto')
                    ->label('Monto')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                    ->alignEnd(),
                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->wrap(),
                TextColumn::make('usuario.name')
                    ->label('Registró')
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc');
    }
}
