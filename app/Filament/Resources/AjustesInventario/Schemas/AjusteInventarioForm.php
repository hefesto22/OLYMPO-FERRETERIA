<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Schemas;

use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
use App\Models\Producto;
use App\Support\Inventario\OpcionesProducto;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class AjusteInventarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del ajuste')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        ToggleButtons::make('tipo')
                            ->label('Tipo')
                            ->options(TipoAjuste::class)
                            ->default(TipoAjuste::Entrada)
                            ->required()
                            ->inline()
                            ->live()
                            ->columnSpan(2),
                        DatePicker::make('fecha')
                            ->label('Fecha')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),
                        Select::make('motivo')
                            ->label('Motivo')
                            ->options(MotivoAjuste::class)
                            ->default(MotivoAjuste::ConteoFisico)
                            ->required(),
                        Textarea::make('notas')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpan(2),
                    ]),

                Section::make('Productos')
                    ->description('Cantidades en la unidad base de cada producto. En las entradas, deja el costo vacío para usar el costo promedio actual.')
                    ->icon(Heroicon::OutlinedCube)
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('detalles')
                            ->hiddenLabel()
                            ->relationship()
                            ->table([
                                TableColumn::make('Producto')->markAsRequired()->width('45%'),
                                TableColumn::make('Existencia actual'),
                                TableColumn::make('Cantidad')->markAsRequired(),
                                TableColumn::make('Costo unitario sin ISV'),
                            ])
                            ->schema([
                                Select::make('producto_id')
                                    ->relationship('producto', 'nombre', fn (Builder $query): Builder => $query
                                        ->where('activo', true)
                                        ->where('controla_stock', true)
                                        ->with('unidadMedida'))
                                    ->getOptionLabelFromRecordUsing(fn (Producto $record): string => OpcionesProducto::etiqueta($record))
                                    ->searchable(['nombre', 'codigo', 'codigo_barras'])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        $set('costo_unitario', OpcionesProducto::costoSugerido($state, null));
                                    }),
                                TextInput::make('existencia')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder(fn (Get $get): string => self::existencia($get('producto_id'))),
                                TextInput::make('cantidad')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.001)
                                    ->suffix(fn (Get $get): string => OpcionesProducto::abreviaturaBase($get('producto_id')))
                                    ->rule('integer', fn (Get $get): bool => ! OpcionesProducto::permiteDecimales($get('producto_id'), null))
                                    ->validationMessages(['integer' => 'Esta unidad no se maneja en fracciones.']),
                                TextInput::make('costo_unitario')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.0001)
                                    ->prefix((string) config('honduras.moneda.simbolo', 'L.'))
                                    ->placeholder('Costo promedio')
                                    ->disabled(fn (Get $get): bool => TipoAjuste::desdeEstado($get('../../tipo')) === TipoAjuste::Salida),
                            ])
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->addActionLabel('Agregar producto'),
                    ]),
            ]);
    }

    private static function existencia(mixed $productoId): string
    {
        $existencia = OpcionesProducto::existencia($productoId);

        if ($existencia === null) {
            return '—';
        }

        return cantidadFormato($existencia).' '.OpcionesProducto::abreviaturaBase($productoId);
    }
}
