<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnosCaja\Tables;

use App\Enums\EstadoTurno;
use App\Filament\Resources\TurnosCaja\Actions\CerrarTurnoAction;
use App\Filament\Resources\TurnosCaja\Actions\TicketTurnoAction;
use App\Models\TurnoCaja;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TurnosCajaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('caja.nombre')
                    ->label('Caja')
                    ->weight('bold'),
                TextColumn::make('cajero.name')
                    ->label('Cajero')
                    ->searchable(),
                TextColumn::make('abierto_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('cerrado_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('total_ventas')
                    ->label('Ventas')
                    ->state(fn (TurnoCaja $record): string => $record->estaAbierto()
                        ? 'En curso'
                        : lempiras((float) $record->total_ventas))
                    ->description(fn (TurnoCaja $record): ?string => $record->estaAbierto() ? null : $record->cantidad_ventas.' ventas')
                    ->alignEnd(),
                TextColumn::make('diferencia')
                    ->label('Diferencia')
                    ->state(fn (TurnoCaja $record): ?string => $record->diferencia !== null ? lempiras((float) $record->diferencia) : null)
                    ->badge()
                    ->color(fn (TurnoCaja $record): string => match (true) {
                        $record->diferencia === null             => 'gray',
                        abs((float) $record->diferencia) < 0.005 => 'success',
                        (float) $record->diferencia < 0          => 'danger',
                        default                                  => 'warning',
                    })
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->defaultSort('abierto_at', 'desc')
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoTurno::class),
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
                        ->when($data['desde'] ?? null, fn (Builder $q, mixed $desde): Builder => $q->whereDate('abierto_at', '>=', $desde))
                        ->when($data['hasta'] ?? null, fn (Builder $q, mixed $hasta): Builder => $q->whereDate('abierto_at', '<=', $hasta))),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    TicketTurnoAction::make(),
                    CerrarTurnoAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['caja', 'cajero']));
    }
}
