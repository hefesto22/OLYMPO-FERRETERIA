<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Schemas;

use App\Filament\Schemas\Components\RTNField;
use App\Filament\Schemas\Components\TelefonoHondurasField;
use App\Models\Producto;
use App\Support\Inventario\OpcionesProducto;
use App\Support\Inventario\ProyeccionCompra;
use App\Support\Inventario\TotalesCompra;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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
                            ->createOptionModalHeading('Nuevo proveedor')
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
                    ->description('Costos sin ISV, como vienen en la factura del proveedor. Se puede comprar por caja, rollo o cubeta: el sistema lo pasa a la unidad base. "Al confirmar" muestra cómo queda el costo ponderado con lo que ya hay en existencia, y el precio de venta.')
                    ->icon(Heroicon::OutlinedCube)
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('detalles')
                            ->hiddenLabel()
                            ->relationship()
                            ->table([
                                TableColumn::make('Producto')->markAsRequired()->width('30%'),
                                TableColumn::make('Presentación')->width('15%'),
                                TableColumn::make('Cantidad')->markAsRequired()->width('9%'),
                                TableColumn::make('Costo sin ISV')->markAsRequired()->width('13%'),
                                TableColumn::make('Al confirmar'),
                            ])
                            ->schema(self::camposLinea())
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->addActionLabel('Agregar producto')
                            ->live(),

                        View::make('filament.compras.totales')
                            ->viewData(fn (Get $get): array => ['totales' => self::totales($get)]),
                    ]),
            ]);
    }

    /**
     * Campos de una línea de la compra y, al final, cómo queda el producto
     * si se confirma (costo ponderado y precio de venta).
     *
     * @return array<int, Select|TextInput|View>
     */
    private static function camposLinea(): array
    {
        return [
            // Las etiquetas se ven en el teléfono, donde cada línea va apilada.
            Select::make('producto_id')
                ->label('Producto')
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
                ->label('Presentación')
                ->options(fn (Get $get): array => OpcionesProducto::presentaciones($get('producto_id')))
                ->placeholder(fn (Get $get): string => OpcionesProducto::etiquetaUnidadBase($get('producto_id')))
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                    $set('costo_unitario', OpcionesProducto::costoSugerido($get('producto_id'), $state));
                }),
            TextInput::make('cantidad')
                ->label('Cantidad')
                ->numeric()
                ->required()
                ->minValue(0.001)
                ->default(1)
                ->rule('integer', fn (Get $get): bool => ! OpcionesProducto::permiteDecimales($get('producto_id'), $get('producto_presentacion_id')))
                ->validationMessages(['integer' => 'Esta unidad no se vende en fracciones.'])
                ->live(onBlur: true),
            TextInput::make('costo_unitario')
                ->label('Costo sin ISV')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.0001)
                ->prefix((string) config('honduras.moneda.simbolo', 'L.'))
                ->live(onBlur: true),
            View::make('filament.compras.proyeccion-linea')
                ->viewData(fn (Get $get, Component $component): array => [
                    'linea' => ProyeccionCompra::desdeFormulario(self::lineas($get('../../detalles')))[self::claveLinea($component)] ?? null,
                ]),
        ];
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function lineas(mixed $estado): ?array
    {
        return is_array($estado) ? $estado : null;
    }

    /**
     * Clave de la línea en el repeater ("record-12" o un uuid): el último
     * tramo de la ruta del estado de su fila.
     */
    private static function claveLinea(Component $component): string
    {
        return Str::afterLast((string) $component->getContainer()->getStatePath(), '.');
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
