<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Schemas;

use App\Enums\TipoAjuste;
use App\Models\AjusteInventarioDetalle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AjusteInventarioInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ajuste')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('numero')
                            ->label('Número')
                            ->weight('bold'),
                        TextEntry::make('estado')
                            ->label('Estado')
                            ->badge(),
                        TextEntry::make('tipo')
                            ->label('Tipo')
                            ->badge(),
                        TextEntry::make('motivo')
                            ->label('Motivo'),
                        TextEntry::make('fecha')
                            ->label('Fecha')
                            ->date('d/m/Y'),
                        TextEntry::make('createdBy.name')
                            ->label('Registró')
                            ->placeholder('—'),
                        TextEntry::make('aplicado_at')
                            ->label('Aplicado')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Pendiente'),
                        TextEntry::make('aplicadoPor.name')
                            ->label('Aplicó')
                            ->placeholder('—'),
                        TextEntry::make('notas')
                            ->label('Notas')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Productos')
                    ->icon(Heroicon::OutlinedCube)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('detalles')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Código'),
                                TableColumn::make('Producto'),
                                TableColumn::make('Cantidad'),
                                TableColumn::make('Costo unitario'),
                            ])
                            ->schema([
                                TextEntry::make('producto.codigo'),
                                TextEntry::make('producto.nombre'),
                                TextEntry::make('cantidad')
                                    ->formatStateUsing(fn (mixed $state, AjusteInventarioDetalle $record): string => cantidadFormato((float) $state).' '.$record->producto->unidadMedida->abreviatura),
                                TextEntry::make('costo_unitario')
                                    ->state(fn (AjusteInventarioDetalle $record): string => self::costo($record)),
                            ]),
                    ]),
            ]);
    }

    private static function costo(AjusteInventarioDetalle $detalle): string
    {
        if ($detalle->ajuste->tipo === TipoAjuste::Salida) {
            return 'Costo promedio';
        }

        return (float) $detalle->costo_unitario > 0
            ? lempiras((float) $detalle->costo_unitario, 4)
            : 'Costo promedio';
    }
}
