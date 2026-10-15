<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Schemas;

use App\Filament\Schemas\Components\RTNField;
use App\Filament\Schemas\Components\TelefonoHondurasField;
use App\Models\Producto;
use App\Support\Inventario\OpcionesProducto;
use App\Support\Inventario\TotalesCompra;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class CompraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la compra')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('proveedor_id')
                            ->label('Proveedor')
                            ->relationship('proveedor', 'nombre', fn (Builder $query): Builder => $query->where('activo', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('nombre')
                                    ->label('Nombre o razón social')
                                    ->required()
                                    ->maxLength(150),
                                RTNField::make()
                                    ->unique(table: 'proveedores', column: 'rtn'),
                                TelefonoHondurasField::make('telefono', 'Teléfono'),
                            ]),
                        TextInput::make('numero_factura_proveedor')
                            ->label('N.º de factura del proveedor')
                            ->maxLength(40)
                            ->placeholder('000-001-01-00001234'),
                        DatePicker::make('fecha')
                            ->label('Fecha')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),
                        Textarea::make('notas')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Productos')
                    ->description('Costos SIN ISV, como vienen en la factura del proveedor. Puedes comprar por caja, rollo o cubeta: el sistema lo convierte a la unidad base.')
                    ->icon(Heroicon::OutlinedCube)
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('detalles')
                            ->hiddenLabel()
                            ->relationship()
                            ->table([
                                TableColumn::make('Producto')->markAsRequired()->width('45%'),
                                TableColumn::make('Presentación'),
                                TableColumn::make('Cantidad')->markAsRequired(),
                                TableColumn::make('Costo unitario sin ISV')->markAsRequired(),
                            ])
                            ->schema(self::camposLinea())
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->addActionLabel('Agregar producto')
                            ->live(),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('resumen_subtotal')
                                    ->label('Subtotal (sin ISV)')
                                    ->state(fn (Get $get): string => lempiras(self::totales($get)['subtotal'])),
                                TextEntry::make('resumen_isv')
                                    ->label('ISV')
                                    ->state(fn (Get $get): string => lempiras(self::totales($get)['isv'])),
                                TextEntry::make('resumen_total')
                                    ->label('Total')
                                    ->state(fn (Get $get): string => lempiras(self::totales($get)['total']))
                                    ->weight('bold'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Campos de una línea de la compra.
     *
     * @return array<int, Select|TextInput>
     */
    private static function camposLinea(): array
    {
        return [
            Select::make('producto_id')
                ->relationship('producto', 'nombre', fn (Builder $query): Builder => $query
                    ->where('activo', true)
                    ->with('unidadMedida'))
                ->getOptionLabelFromRecordUsing(fn (Producto $record): string => OpcionesProducto::etiqueta($record))
                ->searchable(['nombre', 'codigo', 'codigo_barras'])
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    $set('producto_presentacion_id', null);
                    $set('costo_unitario', OpcionesProducto::costoSugerido($state, null));
                }),
            Select::make('producto_presentacion_id')
                ->options(fn (Get $get): array => OpcionesProducto::presentaciones($get('producto_id')))
                ->placeholder(fn (Get $get): string => OpcionesProducto::etiquetaUnidadBase($get('producto_id')))
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                    $set('costo_unitario', OpcionesProducto::costoSugerido($get('producto_id'), $state));
                }),
            TextInput::make('cantidad')
                ->numeric()
                ->required()
                ->minValue(0.001)
                ->default(1)
                ->rule('integer', fn (Get $get): bool => ! OpcionesProducto::permiteDecimales($get('producto_id'), $get('producto_presentacion_id')))
                ->validationMessages(['integer' => 'Esta unidad no se vende en fracciones.'])
                ->live(onBlur: true),
            TextInput::make('costo_unitario')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.0001)
                ->prefix((string) config('honduras.moneda.simbolo', 'L.'))
                ->live(onBlur: true),
        ];
    }

    /**
     * @return array{subtotal: float, isv: float, total: float}
     */
    private static function totales(Get $get): array
    {
        $lineas = $get('detalles');

        return TotalesCompra::desdeFormulario(is_array($lineas) ? $lineas : null);
    }
}
