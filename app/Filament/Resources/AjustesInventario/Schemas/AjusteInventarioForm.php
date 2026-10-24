<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Schemas;

use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
use App\Models\Producto;
use App\Support\Inventario\OpcionesProducto;
use App\Support\Inventario\ResumenAjuste;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ajuste en un modal: primero qué pasó (el motivo ya dice si suma, resta
 * o deja lo contado) y después los productos. Cada línea muestra lo que
 * hay y cómo queda; abajo, cuánto vale el ajuste al costo.
 *
 * En un conteo físico se escribe lo contado y el sistema saca la
 * diferencia. La fecha y quién lo registra los pone el sistema.
 */
class AjusteInventarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ToggleButtons::make('motivo')
                    ->label('¿Qué pasó?')
                    ->options(MotivoAjuste::class)
                    // Como Recibo/Factura en la caja: la opción elegida va oscura.
                    ->colors(array_fill_keys(array_column(MotivoAjuste::cases(), 'value'), 'gray'))
                    ->default(MotivoAjuste::ConteoFisico)
                    ->required()
                    ->inline()
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $tipo = MotivoAjuste::desdeEstado($state)?->tipo();

                        if ($tipo !== null) {
                            $set('tipo', $tipo->value);
                        }
                    })
                    ->helperText(fn (Get $get): ?string => MotivoAjuste::desdeEstado($get('motivo'))?->getDescription()),

                // Solo "Otro" pregunta; los demás motivos ya lo dicen.
                ToggleButtons::make('tipo')
                    ->label('¿Suma o resta?')
                    ->options([
                        TipoAjuste::Entrada->value => 'Suma al inventario',
                        TipoAjuste::Salida->value  => 'Resta del inventario',
                    ])
                    ->icons([
                        TipoAjuste::Entrada->value => TipoAjuste::Entrada->getIcon(),
                        TipoAjuste::Salida->value  => TipoAjuste::Salida->getIcon(),
                    ])
                    ->colors([
                        TipoAjuste::Entrada->value => 'gray',
                        TipoAjuste::Salida->value  => 'gray',
                    ])
                    ->default(TipoAjuste::Entrada->value)
                    ->required()
                    ->inline()
                    ->live()
                    ->visible(fn (Get $get): bool => MotivoAjuste::desdeEstado($get('motivo')) === MotivoAjuste::Otro),

                Repeater::make('detalles')
                    ->label('Productos')
                    ->relationship()
                    ->table(fn (Get $get): array => self::columnas(self::tipo($get)))
                    ->schema(self::camposLinea())
                    ->minItems(1)
                    ->defaultItems(1)
                    ->reorderable(false)
                    ->addActionLabel('Agregar producto')
                    ->live()
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, Get $get): array => self::datosLinea($data, self::tipo($get)))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, Get $get): array => self::datosLinea($data, self::tipo($get))),

                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('notas')
                            ->label('Nota')
                            ->placeholder('Opcional: qué se encontró, quién contó…')
                            ->maxLength(255),
                        View::make('filament.ajustes.resumen')
                            ->viewData(fn (Get $get): array => [
                                'resumen' => ResumenAjuste::desdeFormulario(self::tipo($get), self::lineas($get('detalles'))),
                            ]),
                    ]),
            ]);
    }

    /**
     * Encabezados según el ajuste: en un conteo se escribe lo contado y se
     * ve la diferencia; en una salida, lo que queda; en una entrada, el costo.
     *
     * @return list<TableColumn>
     */
    private static function columnas(TipoAjuste $tipo): array
    {
        return [
            TableColumn::make('Producto')->markAsRequired()->width('38%'),
            TableColumn::make($tipo === TipoAjuste::Conteo ? 'En el sistema' : 'En existencia')->width('16%'),
            TableColumn::make(match ($tipo) {
                TipoAjuste::Conteo => 'Contado',
                TipoAjuste::Salida => 'Salen',
                default            => 'Entran',
            })->markAsRequired()->width('18%'),
            TableColumn::make(match ($tipo) {
                TipoAjuste::Conteo => 'Diferencia',
                TipoAjuste::Salida => 'Quedan',
                default            => 'Costo neto (sin ISV)',
            }),
        ];
    }

    /**
     * Una línea: producto, lo que hay, la cantidad (o lo contado) y el
     * costo (entradas) o cómo queda (salidas y conteos).
     *
     * @return array<int, Select|TextEntry|Group>
     */
    private static function camposLinea(): array
    {
        return [
            // Las etiquetas se ven en el teléfono, donde cada línea va apilada.
            Select::make('producto_id')
                ->label('Producto')
                ->relationship('producto', 'nombre', fn (Builder $query): Builder => $query
                    ->where('activo', true)
                    ->where('controla_stock', true)
                    ->with('unidadMedida'))
                ->getOptionLabelFromRecordUsing(fn (Producto $record): string => OpcionesProducto::etiqueta($record))
                ->searchable(['nombre', 'codigo', 'codigo_barras'])
                ->required()
                ->distinct()
                ->validationMessages(['distinct' => 'Este producto ya está en otra línea.'])
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    $set('costo_unitario', OpcionesProducto::costoSugerido($state, null));
                }),
            TextEntry::make('existencia_vista')
                ->label('En existencia')
                ->state(fn (Get $get): string => self::existencia($get('producto_id'))),
            Group::make([
                TextInput::make('contado')
                    ->label('Contado')
                    ->numeric()
                    ->minValue(0)
                    ->required(fn (Get $get): bool => self::tipo($get, '../../') === TipoAjuste::Conteo)
                    ->suffix(fn (Get $get): string => OpcionesProducto::abreviaturaBase($get('producto_id')))
                    ->rule('integer', fn (Get $get): bool => ! OpcionesProducto::permiteDecimales($get('producto_id'), null))
                    ->validationMessages(['integer' => 'Esta unidad no se cuenta en fracciones.'])
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => self::tipo($get, '../../') === TipoAjuste::Conteo),
                TextInput::make('cantidad')
                    ->label('Cantidad')
                    ->numeric()
                    ->minValue(0.001)
                    ->required(fn (Get $get): bool => self::tipo($get, '../../') !== TipoAjuste::Conteo)
                    ->suffix(fn (Get $get): string => OpcionesProducto::abreviaturaBase($get('producto_id')))
                    ->rule('integer', fn (Get $get): bool => ! OpcionesProducto::permiteDecimales($get('producto_id'), null))
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            self::validarSalida($get, $value, $fail);
                        },
                    ])
                    ->validationMessages(['integer' => 'Esta unidad no se maneja en fracciones.'])
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => self::tipo($get, '../../') !== TipoAjuste::Conteo),
            ]),
            Group::make([
                TextInput::make('costo_unitario')
                    ->label('Costo neto (sin ISV)')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.0001)
                    ->prefix((string) config('honduras.moneda.simbolo', 'L.'))
                    ->placeholder('Costo promedio')
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => self::tipo($get, '../../') === TipoAjuste::Entrada),
                View::make('filament.ajustes.resultado-linea')
                    ->viewData(fn (Get $get): array => [
                        'tipo'    => self::tipo($get, '../../')->value,
                        'elegido' => filled($get('producto_id')),
                        'linea'   => ResumenAjuste::linea(self::tipo($get, '../../'), [
                            'producto_id'    => $get('producto_id'),
                            'contado'        => $get('contado'),
                            'cantidad'       => $get('cantidad'),
                            'costo_unitario' => $get('costo_unitario'),
                        ]),
                    ])
                    ->visible(fn (Get $get): bool => self::tipo($get, '../../') !== TipoAjuste::Entrada),
            ]),
        ];
    }

    /**
     * El tipo que sale del motivo; con "Otro", el que se escogió. `$ruta`
     * sube desde una línea del repeater ("../../").
     */
    private static function tipo(Get $get, string $ruta = ''): TipoAjuste
    {
        return MotivoAjuste::desdeEstado($get($ruta.'motivo'))?->tipo()
            ?? TipoAjuste::desdeEstado($get($ruta.'tipo'))
            ?? TipoAjuste::Entrada;
    }

    /**
     * Lo que se guarda de cada línea según el ajuste: en un conteo, lo
     * contado (la cantidad la saca el modelo); si no, la cantidad. El costo
     * solo cuenta en las entradas.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function datosLinea(array $data, TipoAjuste $tipo): array
    {
        if ($tipo === TipoAjuste::Conteo) {
            return [...$data, 'costo_unitario' => 0];
        }

        return [
            ...$data,
            'contado'        => null,
            'costo_unitario' => $tipo === TipoAjuste::Entrada && is_numeric($data['costo_unitario'] ?? null) ? $data['costo_unitario'] : 0,
        ];
    }

    /**
     * Una salida no puede sacar más de lo que hay (salvo que la empresa
     * permita existencias en negativo).
     */
    private static function validarSalida(Get $get, mixed $cantidad, Closure $fail): void
    {
        if (self::tipo($get, '../../') !== TipoAjuste::Salida || ! is_numeric($cantidad)) {
            return;
        }

        if ((bool) config('ferreteria.inventario.permitir_stock_negativo', false)) {
            return;
        }

        $existencia = OpcionesProducto::existencia($get('producto_id'));

        if ($existencia !== null && (float) $cantidad > $existencia + 0.000001) {
            $fail('Solo hay '.trim(cantidadFormato($existencia).' '.OpcionesProducto::abreviaturaBase($get('producto_id'))).'.');
        }
    }

    private static function existencia(mixed $productoId): string
    {
        $existencia = OpcionesProducto::existencia($productoId);

        if ($existencia === null) {
            return '—';
        }

        return trim(cantidadFormato($existencia).' '.OpcionesProducto::abreviaturaBase($productoId));
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function lineas(mixed $estado): ?array
    {
        return is_array($estado) ? $estado : null;
    }
}
