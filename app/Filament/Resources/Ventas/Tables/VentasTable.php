<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ventas\Tables;

use App\Enums\EstadoVenta;
use App\Enums\TipoDocumentoVenta;
use App\Filament\Resources\Ventas\Actions\AnularVentaAction;
use App\Filament\Resources\Ventas\Actions\TicketVentaAction;
use App\Models\Venta;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VentasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')
                    ->label('Número')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('tipo_documento')
                    ->label('Documento')
                    ->badge(),
                TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->searchable()
                    ->description(fn (Venta $record): ?string => $record->cliente_rtn !== null ? 'RTN '.$record->cliente_rtn : null)
                    ->wrap(),
                TextColumn::make('cajero.name')
                    ->label('Cajero')
                    ->toggleable(),
                TextColumn::make('caja.nombre')
                    ->label('Caja')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('tipo_documento')
                    ->label('Documento')
                    ->options(TipoDocumentoVenta::class),
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoVenta::class),
                SelectFilter::make('caja')
                    ->label('Caja')
                    ->relationship('caja', 'nombre'),
                SelectFilter::make('cajero')
                    ->label('Cajero')
                    ->relationship('cajero', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, mixed $desde): Builder => $q->whereDate('created_at', '>=', $desde))
                        ->when($data['hasta'] ?? null, fn (Builder $q, mixed $hasta): Builder => $q->whereDate('created_at', '<=', $hasta))),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    TicketVentaAction::make(),
                    AnularVentaAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['cajero', 'caja']));
    }
}
