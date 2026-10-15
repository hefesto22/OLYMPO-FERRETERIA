<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ventas\Schemas;

use App\Models\Venta;
use App\Models\VentaDetalle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class VentaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $dinero = static fn (mixed $state): string => lempiras((float) $state);

        return $schema
            ->components([
                Section::make('Documento')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('numero')
                            ->label('Número')
                            ->weight('bold')
                            ->copyable(),
                        TextEntry::make('tipo_documento')
                            ->label('Documento')
                            ->badge(),
                        TextEntry::make('estado')
                            ->label('Estado')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label('Fecha')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('cliente_nombre')
                            ->label('Cliente'),
                        TextEntry::make('cliente_rtn')
                            ->label('RTN')
                            ->placeholder('—'),
                        TextEntry::make('lista_precio')
                            ->label('Precio')
                            ->badge(),
                        TextEntry::make('cajero.name')
                            ->label('Cajero')
                            ->state(fn (Venta $record): string => $record->cajero->name.' · '.$record->caja->nombre),
                        TextEntry::make('cai')
                            ->label('CAI')
                            ->columnSpan(2)
                            ->visible(fn (Venta $record): bool => $record->esFactura()),
                        TextEntry::make('rango_cai')
                            ->label('Rango autorizado')
                            ->state(fn (Venta $record): string => "{$record->cai_rango_desde} al {$record->cai_rango_hasta}")
                            ->visible(fn (Venta $record): bool => $record->esFactura()),
                        TextEntry::make('cai_fecha_limite')
                            ->label('Fecha límite de emisión')
                            ->date('d/m/Y')
                            ->visible(fn (Venta $record): bool => $record->esFactura()),
                        TextEntry::make('anulada_at')
                            ->label('Anulada')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn (Venta $record): bool => $record->estaAnulada()),
                        TextEntry::make('anuladaPor.name')
                            ->label('Anuló')
                            ->visible(fn (Venta $record): bool => $record->estaAnulada()),
                        TextEntry::make('motivo_anulacion')
                            ->label('Motivo')
                            ->columnSpan(2)
                            ->visible(fn (Venta $record): bool => $record->estaAnulada()),
                    ]),

                Section::make('Productos')
                    ->icon(Heroicon::OutlinedCube)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('detalles')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Producto'),
                                TableColumn::make('Unidad'),
                                TableColumn::make('Cantidad'),
                                TableColumn::make('Precio'),
                                TableColumn::make('Descuento'),
                                TableColumn::make('ISV'),
                                TableColumn::make('Total'),
                            ])
                            ->schema([
                                TextEntry::make('descripcion'),
                                TextEntry::make('unidad'),
                                TextEntry::make('cantidad')
                                    ->formatStateUsing(fn (mixed $state): string => cantidadFormato((float) $state)),
                                TextEntry::make('precio_unitario')
                                    ->formatStateUsing($dinero),
                                TextEntry::make('descuento')
                                    ->state(fn (VentaDetalle $record): string => (float) $record->descuento > 0
                                        ? lempiras((float) $record->descuento).' ('.cantidadFormato((float) $record->descuento_pct, 2).' %)'
                                        : '—'),
                                TextEntry::make('tipo_isv')
                                    ->badge(),
                                TextEntry::make('total')
                                    ->formatStateUsing($dinero)
                                    ->weight('bold'),
                            ]),
                    ]),

                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Totales')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->columns(2)
                            ->schema([
                                TextEntry::make('descuento')->label('Descuentos')->formatStateUsing($dinero),
                                TextEntry::make('importe_exento')->label('Importe exento')->formatStateUsing($dinero),
                                TextEntry::make('importe_gravado_15')->label('Importe gravado 15 %')->formatStateUsing($dinero),
                                TextEntry::make('isv_15')->label('ISV 15 %')->formatStateUsing($dinero),
                                TextEntry::make('importe_gravado_18')->label('Importe gravado 18 %')->formatStateUsing($dinero),
                                TextEntry::make('isv_18')->label('ISV 18 %')->formatStateUsing($dinero),
                                TextEntry::make('total')
                                    ->label('Total')
                                    ->formatStateUsing($dinero)
                                    ->weight('bold')
                                    ->size('lg'),
                                TextEntry::make('total_letras')
                                    ->label('En letras')
                                    ->state(fn (Venta $record): string => $record->totalEnLetras()),
                            ]),
                        Section::make('Pago')
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->schema([
                                RepeatableEntry::make('pagos')
                                    ->hiddenLabel()
                                    ->table([
                                        TableColumn::make('Método'),
                                        TableColumn::make('Monto'),
                                        TableColumn::make('Referencia'),
                                    ])
                                    ->schema([
                                        TextEntry::make('metodo')->badge(),
                                        TextEntry::make('monto')->formatStateUsing($dinero),
                                        TextEntry::make('referencia')->placeholder('—'),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('efectivo_recibido')->label('Efectivo recibido')->formatStateUsing($dinero),
                                        TextEntry::make('cambio')->label('Cambio')->formatStateUsing($dinero),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
