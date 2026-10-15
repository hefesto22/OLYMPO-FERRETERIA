<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnosCaja\RelationManagers;

use App\Filament\Resources\Ventas\Actions\TicketVentaAction;
use App\Filament\Resources\Ventas\VentaResource;
use App\Models\Venta;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VentasRelationManager extends RelationManager
{
    protected static string $relationship = 'ventas';

    protected static ?string $title = 'Ventas del turno';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedReceiptPercent;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')
                    ->label('Número')
                    ->weight('bold'),
                TextColumn::make('created_at')
                    ->label('Hora')
                    ->dateTime('H:i'),
                TextColumn::make('tipo_documento')
                    ->label('Documento')
                    ->badge(),
                TextColumn::make('cliente_nombre')
                    ->label('Cliente'),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                    ->alignEnd(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Venta $record): string => VentaResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                TicketVentaAction::make(),
            ]);
    }
}
