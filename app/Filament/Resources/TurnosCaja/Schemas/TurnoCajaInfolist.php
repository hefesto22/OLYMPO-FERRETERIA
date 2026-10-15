<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnosCaja\Schemas;

use App\Models\TurnoCaja;
use App\Services\Ventas\TurnoCajaService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TurnoCajaInfolist
{
    /**
     * Resumen en vivo de turnos abiertos, memorizado por request.
     *
     * @var array<int, array<string, float|int>>
     */
    private static array $resumenes = [];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Turno')
                    ->icon(Heroicon::OutlinedClock)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('caja.nombre')
                            ->label('Caja')
                            ->weight('bold'),
                        TextEntry::make('cajero.name')
                            ->label('Cajero'),
                        TextEntry::make('estado')
                            ->label('Estado')
                            ->badge(),
                        TextEntry::make('fondo_inicial')
                            ->label('Fondo inicial')
                            ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state)),
                        TextEntry::make('abierto_at')
                            ->label('Apertura')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('cerrado_at')
                            ->label('Cierre')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Abierto'),
                        TextEntry::make('cerradoPor.name')
                            ->label('Cerró')
                            ->placeholder('—'),
                        TextEntry::make('notas_cierre')
                            ->label('Notas')
                            ->placeholder('—'),
                    ]),

                Section::make('Arqueo')
                    ->description(fn (TurnoCaja $record): string => $record->estaAbierto()
                        ? 'Turno en curso: los montos se calculan al momento.'
                        : 'Foto de los totales al cerrar el turno.')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('a_ventas')
                            ->label('Ventas')
                            ->state(fn (TurnoCaja $record): string => ((int) self::valor($record, 'cantidad_ventas')).' · '.lempiras(self::valor($record, 'total_ventas'))),
                        TextEntry::make('a_efectivo')
                            ->label('Ventas en efectivo')
                            ->state(fn (TurnoCaja $record): string => lempiras(self::valor($record, 'total_efectivo'))),
                        TextEntry::make('a_tarjeta')
                            ->label('Tarjeta')
                            ->state(fn (TurnoCaja $record): string => lempiras(self::valor($record, 'total_tarjeta'))),
                        TextEntry::make('a_transferencia')
                            ->label('Transferencia')
                            ->state(fn (TurnoCaja $record): string => lempiras(self::valor($record, 'total_transferencia'))),
                        TextEntry::make('a_ingresos')
                            ->label('Entradas de efectivo')
                            ->state(fn (TurnoCaja $record): string => lempiras(self::valor($record, 'total_ingresos'))),
                        TextEntry::make('a_egresos')
                            ->label('Retiros de efectivo')
                            ->state(fn (TurnoCaja $record): string => lempiras(self::valor($record, 'total_egresos'))),
                        TextEntry::make('a_esperado')
                            ->label('Efectivo esperado')
                            ->state(fn (TurnoCaja $record): string => lempiras(self::valor($record, 'efectivo_esperado')))
                            ->weight('bold'),
                        TextEntry::make('a_contado')
                            ->label('Efectivo contado / diferencia')
                            ->state(fn (TurnoCaja $record): string => $record->efectivo_contado !== null
                                ? lempiras((float) $record->efectivo_contado).' / '.lempiras((float) $record->diferencia)
                                : '—')
                            ->weight('bold'),
                    ]),
            ]);
    }

    /**
     * Valor guardado al cerrar, o calculado en vivo si el turno sigue abierto.
     */
    private static function valor(TurnoCaja $turno, string $campo): float
    {
        if (! $turno->estaAbierto()) {
            return (float) $turno->getAttribute($campo);
        }

        self::$resumenes[$turno->id] ??= app(TurnoCajaService::class)->resumen($turno);

        return (float) (self::$resumenes[$turno->id][$campo] ?? 0);
    }
}
