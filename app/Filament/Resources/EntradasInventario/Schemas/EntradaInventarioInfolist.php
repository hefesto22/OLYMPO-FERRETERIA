<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntradasInventario\Schemas;

use App\Enums\EstadoDocumento;
use App\Models\EntradaInventario;
use App\Models\EntradaInventarioDetalle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EntradaInventarioInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entrada')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('numero')
                            ->label('Número')
                            ->weight('bold'),
                        TextEntry::make('estado')
                            ->label('Estado')
                            ->badge(),
                        TextEntry::make('fecha')
                            ->label('Fecha')
                            ->date('d/m/Y'),
                        TextEntry::make('createdBy.name')
                            ->label('Registró')
                            ->placeholder('—'),
                        TextEntry::make('confirmada_at')
                            ->label('Confirmada')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Pendiente'),
                        TextEntry::make('confirmadaPor.name')
                            ->label('Confirmó')
                            ->placeholder('—'),
                        TextEntry::make('anulada_at')
                            ->label('Anulada')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn (EntradaInventario $record): bool => $record->estado === EstadoDocumento::Anulado),
                        TextEntry::make('anuladaPor.name')
                            ->label('Anuló')
                            ->visible(fn (EntradaInventario $record): bool => $record->estado === EstadoDocumento::Anulado),
                        TextEntry::make('motivo_anulacion')
                            ->label('Motivo de anulación')
                            ->columnSpan(2)
                            ->visible(fn (EntradaInventario $record): bool => $record->estado === EstadoDocumento::Anulado),
                        TextEntry::make('notas')
                            ->label('Nota')
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
                                TableColumn::make('Producto'),
                                TableColumn::make('Presentación'),
                                TableColumn::make('Cantidad'),
                                TableColumn::make('Costo neto'),
                                TableColumn::make('Con ISV'),
                                TableColumn::make('Subtotal'),
                                TableColumn::make('ISV'),
                                TableColumn::make('Total'),
                            ])
                            ->schema([
                                TextEntry::make('producto.nombre'),
                                TextEntry::make('presentacion_etiqueta')
                                    ->state(fn (EntradaInventarioDetalle $record): string => $record->etiquetaUnidad()),
                                TextEntry::make('cantidad')
                                    ->formatStateUsing(fn (mixed $state): string => cantidadFormato((float) $state)),
                                TextEntry::make('costo_unitario')
                                    ->formatStateUsing(fn (mixed $state): string => costoLempiras((float) $state)),
                                TextEntry::make('costo_con_isv')
                                    ->state(fn (EntradaInventarioDetalle $record): string => costoLempiras(round((float) $record->costo_unitario * (1 + $record->tipo_isv->tasa()), 4))),
                                TextEntry::make('subtotal')
                                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state)),
                                TextEntry::make('isv')
                                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state)),
                                TextEntry::make('total')
                                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                                    ->weight('bold'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Subtotal (sin ISV)')
                                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state)),
                                TextEntry::make('isv')
                                    ->label('ISV')
                                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state)),
                                TextEntry::make('total')
                                    ->label('Total')
                                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                                    ->weight('bold'),
                            ]),
                    ]),
            ]);
    }
}
