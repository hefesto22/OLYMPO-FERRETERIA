<?php

declare(strict_types=1);

namespace App\Filament\Resources\Productos\Schemas;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Producto')
                    ->icon(Heroicon::OutlinedTag)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('nombre')
                            ->label('Nombre')
                            ->weight('bold')
                            ->columnSpan(3),
                        ImageEntry::make('imagen_path')
                            ->label('')
                            ->height(80)
                            ->visible(fn (Producto $record): bool => $record->imagen_path !== null),
                        TextEntry::make('codigo')
                            ->label('Código')
                            ->copyable(),
                        TextEntry::make('codigo_barras')
                            ->label('Código de barras')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('categoria.nombre')
                            ->label('Categoría'),
                        TextEntry::make('marca.nombre')
                            ->label('Marca')
                            ->placeholder('Sin marca'),
                        TextEntry::make('unidadMedida.nombre')
                            ->label('Unidad base'),
                        TextEntry::make('ubicacion')
                            ->label('Ubicación')
                            ->placeholder('—'),
                        IconEntry::make('activo')
                            ->label('Activo')
                            ->boolean(),
                        TextEntry::make('descripcion')
                            ->label('Descripción')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Precios y costos')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('precio_venta')
                            ->label('Precio de venta (ISV incl.)')
                            ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                            ->weight('bold'),
                        TextEntry::make('precio_mayoreo')
                            ->label('Precio mayoreo (ISV incl.)')
                            ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                            ->placeholder('—'),
                        TextEntry::make('tipo_isv')
                            ->label('ISV')
                            ->badge(),
                        TextEntry::make('costo_promedio')
                            ->label('Costo promedio (sin ISV)')
                            ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state, 4)),
                        TextEntry::make('ultimo_costo')
                            ->label('Último costo (sin ISV)')
                            ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state, 4)),
                        TextEntry::make('margen')
                            ->label('Margen')
                            ->state(function (Producto $record): string {
                                $margen = $record->margen();

                                return $margen !== null ? porcentaje($margen, 1) : '—';
                            }),
                    ]),

                Section::make('Existencias')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('stock')
                            ->label('Existencia actual')
                            ->state(fn (Producto $record): string => $record->controla_stock
                                ? cantidadFormato((float) $record->stock).' '.$record->unidadMedida->abreviatura
                                : 'No controla existencias (servicio)')
                            ->badge()
                            ->color(fn (Producto $record): string => self::colorExistencia($record)),
                        TextEntry::make('stock_minimo')
                            ->label('Existencia mínima')
                            ->formatStateUsing(fn (mixed $state, Producto $record): string => cantidadFormato((float) $state).' '.$record->unidadMedida->abreviatura)
                            ->visible(fn (Producto $record): bool => $record->controla_stock),
                        TextEntry::make('valor_inventario')
                            ->label('Valor del inventario (al costo)')
                            ->state(fn (Producto $record): string => lempiras(max((float) $record->stock, 0) * (float) $record->costo_promedio))
                            ->visible(fn (Producto $record): bool => $record->controla_stock),
                    ]),

                Section::make('Presentaciones')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->columnSpanFull()
                    ->visible(fn (Producto $record): bool => $record->presentaciones()->exists())
                    ->schema([
                        RepeatableEntry::make('presentaciones')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Presentación'),
                                TableColumn::make('Contiene'),
                                TableColumn::make('Código de barras'),
                                TableColumn::make('Precio (ISV incl.)'),
                            ])
                            ->schema([
                                TextEntry::make('etiqueta')
                                    ->state(fn (ProductoPresentacion $record): string => $record->etiqueta()),
                                TextEntry::make('factor')
                                    ->formatStateUsing(fn (mixed $state, ProductoPresentacion $record): string => cantidadFormato((float) $state).' '.$record->producto->unidadMedida->abreviatura),
                                TextEntry::make('codigo_barras')
                                    ->placeholder('—'),
                                TextEntry::make('precio_venta')
                                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                                    ->placeholder('Precio base × factor'),
                            ]),
                    ]),
            ]);
    }

    public static function colorExistencia(Producto $producto): string
    {
        return match (true) {
            ! $producto->controla_stock   => 'gray',
            (float) $producto->stock <= 0 => 'danger',
            $producto->estaBajoMinimo()   => 'warning',
            default                       => 'success',
        };
    }
}
