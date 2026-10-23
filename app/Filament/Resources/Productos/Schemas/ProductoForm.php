<?php

declare(strict_types=1);

namespace App\Filament\Resources\Productos\Schemas;

use App\Enums\EquivalenciaPresentacion;
use App\Enums\TipoIsv;
use App\Enums\TipoMargen;
use App\Filament\Schemas\Components\CodigoBarrasField;
use App\Filament\Schemas\Components\MontoField;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use App\Support\Precios\CalculadoraPrecio;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class ProductoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::seccionIdentificacion(),
                self::seccionClasificacion(),
                self::seccionPrecios(),
                self::seccionInventario(),
                self::seccionPresentaciones(),
            ]);
    }

    private static function seccionIdentificacion(): Section
    {
        return Section::make('Identificación')
            ->description('Cómo se reconoce el producto en mostrador y con el escáner.')
            ->icon(Heroicon::OutlinedTag)
            ->columns(3)
            ->columnSpanFull()
            ->schema([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(200)
                    ->placeholder('Ej: Tornillo goloso 8 x 1 1/2"')
                    ->columnSpanFull(),
                TextInput::make('codigo')
                    ->label('Código interno')
                    ->maxLength(30)
                    ->unique()
                    ->placeholder('Automático (P-000123)'),
                CodigoBarrasField::make()
                    ->label('Código de barras')
                    ->unique()
                    ->placeholder('Escanéalo o escríbelo'),
                TextInput::make('ubicacion')
                    ->label('Ubicación')
                    ->maxLength(80)
                    ->prefixIcon(Heroicon::OutlinedMapPin)
                    ->placeholder('Pasillo 3, estante B'),
                Textarea::make('descripcion')
                    ->label('Descripción / especificaciones')
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpan(2),
                FileUpload::make('imagen_path')
                    ->label('Foto')
                    ->image()
                    ->directory('productos')
                    ->maxSize(2048),
            ]);
    }

    private static function seccionClasificacion(): Section
    {
        return Section::make('Clasificación')
            ->icon(Heroicon::OutlinedSwatch)
            ->columns(3)
            ->columnSpanFull()
            ->schema([
                Select::make('categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre', fn (Builder $query): Builder => $query->where('activo', true))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionModalHeading('Nueva categoría')
                    ->createOptionForm([
                        TextInput::make('nombre')
                            ->label('Nombre de la categoría')
                            ->required()
                            ->maxLength(120),
                    ]),
                Select::make('marca_id')
                    ->label('Marca')
                    ->relationship('marca', 'nombre', fn (Builder $query): Builder => $query->where('activo', true))
                    ->searchable()
                    ->preload()
                    ->placeholder('Sin marca')
                    ->createOptionModalHeading('Nueva marca')
                    ->createOptionForm([
                        TextInput::make('nombre')
                            ->label('Nombre de la marca')
                            ->required()
                            ->maxLength(80)
                            ->unique(table: 'marcas', column: 'nombre'),
                    ]),
                Select::make('unidad_medida_id')
                    ->label('Unidad base')
                    ->relationship('unidadMedida', 'nombre', fn (Builder $query): Builder => $query->where('activo', true)->orderBy('nombre'))
                    ->getOptionLabelFromRecordUsing(fn (UnidadMedida $record): string => "{$record->nombre} ({$record->abreviatura})")
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    // Con movimientos en el kardex, cambiar la unidad base
                    // reinterpretaría todo el stock: se bloquea.
                    ->disabled(fn (?Producto $record): bool => $record !== null && $record->movimientos()->exists())
                    ->helperText('El inventario se lleva en esta unidad. Cajas, rollos o cubetas van abajo como presentaciones.'),
            ]);
    }

    private static function seccionPrecios(): Section
    {
        return Section::make('Precio e impuesto')
            ->description('Con margen, el precio sale del costo ponderado de lo que hay en existencia: costo + margen + ISV, redondeado al lempira hacia arriba.')
            ->icon(Heroicon::OutlinedBanknotes)
            ->columns(['default' => 1, 'lg' => 5])
            ->columnSpanFull()
            ->schema([
                // A la izquierda lo que se teclea; a la derecha el precio que sale y de dónde.
                Grid::make(2)
                    ->columnSpan(['lg' => 3])
                    ->schema([
                        ToggleButtons::make('margen_tipo')
                            ->label('Cómo se calcula el precio')
                            ->options(TipoMargen::class)
                            ->default(TipoMargen::Porcentaje)
                            ->inline()
                            ->required()
                            ->live()
                            // Un servicio no tiene costo: su precio va a mano.
                            ->disableOptionWhen(fn (string $value, Get $get): bool => $value !== TipoMargen::Manual->value && ! (bool) $get('controla_stock'))
                            ->columnSpanFull(),

                        // ─── Con margen ─────────────────────────────
                        TextInput::make('margen_publico')
                            ->label('Margen público')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required(fn (Get $get): bool => self::usaMargen($get))
                            ->prefix(fn (Get $get): ?string => self::esMonto($get) ? (string) config('honduras.moneda.simbolo', 'L.') : null)
                            ->suffix(fn (Get $get): string => self::esMonto($get) ? 'por '.self::abreviaturaUnidad($get('unidad_medida_id')) : '%')
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => self::usaMargen($get)),
                        TextInput::make('margen_mayoreo')
                            ->label('Margen mayoreo / contratista')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix(fn (Get $get): ?string => self::esMonto($get) ? (string) config('honduras.moneda.simbolo', 'L.') : null)
                            ->suffix(fn (Get $get): string => self::esMonto($get) ? 'por '.self::abreviaturaUnidad($get('unidad_medida_id')) : '%')
                            ->helperText('Opcional. Vacío: sin precio mayoreo.')
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => self::usaMargen($get)),

                        // ─── A mano ─────────────────────────────────
                        MontoField::make('precio_venta', 'Precio de venta')
                            ->helperText('Por unidad base, con ISV incluido.')
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $costo = self::numero($get('costo_promedio')) ?? self::numero($get('costo_inicial')) ?? 0.0;
                                    $tasa = (TipoIsv::desdeEstado($get('tipo_isv')) ?? TipoIsv::Gravado15)->tasa();

                                    if (is_numeric($value) && $costo > 0 && (float) $value / (1 + $tasa) < $costo - 0.005) {
                                        $fail('Queda por debajo del costo ponderado: '.lempiras($costo).' sin ISV.');
                                    }
                                },
                            ])
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => ! self::usaMargen($get)),
                        MontoField::make('precio_mayoreo', 'Precio mayoreo / contratista')
                            ->required(false)
                            ->default(null)
                            ->helperText('Opcional. Con ISV incluido.')
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => ! self::usaMargen($get)),

                        Select::make('tipo_isv')
                            ->label('ISV')
                            ->options(TipoIsv::class)
                            ->default(TipoIsv::Gravado15)
                            ->required()
                            ->live(),

                        // Solo para cálculos en pantalla (el costo de cada presentación).
                        Hidden::make('costo_promedio')
                            ->dehydrated(false),
                        Hidden::make('costo_precio')
                            ->dehydrated(false),
                    ]),

                View::make('filament.productos.resumen-precio')
                    ->viewData(fn (Get $get, ?Producto $record): array => ['resumen' => self::resumenPrecio($get, $record)])
                    ->columnSpan(['lg' => 2]),
            ]);
    }

    private static function seccionInventario(): Section
    {
        return Section::make('Inventario')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->columns(3)
            ->columnSpanFull()
            ->schema([
                Toggle::make('controla_stock')
                    ->label('Controla existencias')
                    ->default(true)
                    ->live()
                    ->afterStateUpdated(function (bool $state, Set $set): void {
                        if (! $state) {
                            $set('margen_tipo', TipoMargen::Manual->value);
                        }
                    })
                    ->helperText('Desactívalo para servicios: corte de tubo, copia de llave, flete. Los servicios llevan precio a mano.'),
                TextInput::make('stock_minimo')
                    ->label('Existencia mínima')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->suffix(fn (Get $get): string => self::abreviaturaUnidad($get('unidad_medida_id')))
                    ->helperText('Avisa cuando la existencia baje a este número.')
                    ->visible(fn (Get $get): bool => (bool) $get('controla_stock')),
                TextEntry::make('stock_vista')
                    ->label('Existencia actual')
                    ->state(fn (?Producto $record): string => $record !== null
                        ? cantidadFormato((float) $record->stock).' '.$record->unidadMedida->abreviatura
                        : '—')
                    ->helperText('Solo cambia con compras, ventas y ajustes de inventario.')
                    ->visible(fn (Get $get, string $operation): bool => $operation !== 'create' && (bool) $get('controla_stock')),
                TextInput::make('stock_inicial')
                    ->label('Existencia inicial')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->dehydrated(false)
                    // En vivo: la tarjeta del precio muestra lo que va a quedar en existencia.
                    ->live(onBlur: true)
                    ->suffix(fn (Get $get): string => self::abreviaturaUnidad($get('unidad_medida_id')))
                    ->helperText('Queda en el kardex como inventario inicial.')
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('controla_stock')),
                TextInput::make('costo_inicial')
                    ->label('Costo unitario inicial (sin ISV)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->step(0.0001)
                    ->prefix((string) config('honduras.moneda.simbolo', 'L.'))
                    ->dehydrated(false)
                    // En vivo: con él se calcula el precio de la tarjeta.
                    ->live(onBlur: true)
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('controla_stock')),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true)
                    ->onColor('success')
                    ->helperText('Inactivo: no aparece para vender ni comprar.'),
            ]);
    }

    private static function seccionPresentaciones(): Section
    {
        return Section::make('Presentaciones')
            ->description('Otras formas de vender o comprar el producto: rollo de 50 lb, bolsa de 1 lb, clavo suelto (100 por LB). El inventario se sigue llevando en la unidad base. Si dejas el precio vacío, sale solo.')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->columnSpanFull()
            ->collapsible()
            ->schema([
                // Una línea por presentación con lo de todos los días. El nombre y
                // el margen propio, que casi no se usan, van en el botón de ajustes.
                Repeater::make('presentaciones')
                    ->hiddenLabel()
                    ->relationship()
                    ->table([
                        TableColumn::make('Presentación')->markAsRequired()->width('22%'),
                        TableColumn::make('Equivale a')->markAsRequired()->width('24%'),
                        TableColumn::make('Precio (ISV incl.)')->width('17%'),
                        TableColumn::make('Ganancia')->width('16%'),
                        TableColumn::make('Código de barras'),
                    ])
                    ->schema([
                        Group::make([
                            Select::make('unidad_medida_id')
                                ->label('Presentación')
                                ->options(fn (): array => UnidadMedida::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                                    ->all())
                                ->searchable()
                                ->required()
                                ->placeholder('Rollo, caja, bolsa…')
                                ->helperText(fn (Get $get): ?string => is_string($get('nombre')) && trim($get('nombre')) !== '' ? 'Se ve como «'.trim($get('nombre')).'»' : null),
                            // Se cambian con el botón de ajustes de la línea.
                            Hidden::make('nombre'),
                            Hidden::make('margen'),
                        ]),
                        FusedGroup::make([
                            TextInput::make('factor_cantidad')
                                ->label('Cantidad')
                                ->numeric()
                                ->required()
                                ->live(onBlur: true)
                                ->minValue(0.0001)
                                ->step(0.0001)
                                ->placeholder('50')
                                // El inventario va a 6 decimales: más de 10,000 por unidad base ya no descuenta exacto.
                                ->maxValue(fn (Get $get): ?int => EquivalenciaPresentacion::desdeEstado($get('factor_modo')) === EquivalenciaPresentacion::PorUnidadBase ? 10000 : null)
                                ->validationMessages([
                                    'max' => 'Hasta 10,000 por unidad base. Si son más, usa la unidad suelta como unidad base del producto.',
                                ])
                                ->columnSpan(2),
                            Select::make('factor_modo')
                                ->label('Cómo')
                                ->options(fn (Get $get): array => self::opcionesEquivalencia($get('../../unidad_medida_id')))
                                ->default(EquivalenciaPresentacion::Contiene->value)
                                // El modelo lo entrega como enum; el select trabaja con su valor.
                                ->formatStateUsing(fn (mixed $state): string => (EquivalenciaPresentacion::desdeEstado($state) ?? EquivalenciaPresentacion::Contiene)->value)
                                ->selectablePlaceholder(false)
                                ->required()
                                ->live()
                                ->columnSpan(3),
                        ])
                            ->label('Equivale a')
                            ->columns(5),
                        TextInput::make('precio_venta')
                            ->label('Precio (ISV incl.)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix((string) config('honduras.moneda.simbolo', 'L.'))
                            // Vacío = precio automático; se muestra el que va a salir.
                            ->placeholder(function (Get $get): string {
                                $automatico = self::datosPresentacion($get)['automatico'];

                                return $automatico !== null ? number_format($automatico, 2) : 'Sin costo';
                            })
                            ->helperText(fn (Get $get): ?string => self::textoMargenPropio($get))
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $datos = self::datosPresentacion($get);

                                    if (is_numeric($value) && $datos['costo'] > 0 && (float) $value / (1 + $datos['tasa']) < $datos['costo'] - 0.005) {
                                        $fail('Queda por debajo del costo: '.lempiras($datos['costo']).' sin ISV.');
                                    }
                                },
                            ])
                            ->live(onBlur: true),
                        TextEntry::make('ganancia_vista')
                            ->label('Ganancia')
                            ->state(fn (Get $get): string => self::gananciaPresentacion($get)['texto'])
                            ->color(fn (Get $get): string => self::gananciaPresentacion($get)['color'])
                            ->helperText(function (Get $get): string {
                                $costo = self::datosPresentacion($get)['costo'];

                                return $costo > 0 ? 'Cuesta '.costoLempiras($costo) : 'Sin costo todavía';
                            })
                            ->weight('bold'),
                        CodigoBarrasField::make()
                            ->label('Código de barras')
                            ->unique(table: ProductoPresentacion::class, column: 'codigo_barras'),
                    ])
                    ->extraItemActions([self::accionNombreYMargen()])
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addActionLabel('Agregar presentación'),
            ]);
    }

    /**
     * Nombre y margen propio de una presentación. Casi no se usan, así que
     * van en un modal y no le quitan espacio a la tabla.
     */
    private static function accionNombreYMargen(): Action
    {
        return Action::make('nombreYMargen')
            ->label('Nombre y margen propio')
            ->tooltip('Nombre y margen propio')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->iconButton()
            ->color('gray')
            ->modalHeading('Nombre y margen propio')
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel('Listo')
            ->fillForm(fn (array $arguments, Repeater $component): array => Arr::only($component->getRawItemState((string) $arguments['item']), ['nombre', 'margen']))
            ->schema(function (Repeater $component): array {
                $tipo = TipoMargen::desdeEstado($component->makeGetUtility()('margen_tipo')) ?? TipoMargen::Manual;

                return [
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->maxLength(80)
                        ->placeholder('Bolsa 1 lb, clavo suelto…')
                        ->helperText('Opcional. Vacío: se ve con la unidad y lo que lleva, como «Rollo (50 LB)».'),
                    TextInput::make('margen')
                        ->label('Margen propio')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix($tipo === TipoMargen::Monto ? 'L por presentación' : '%')
                        ->helperText($tipo === TipoMargen::Manual
                            ? 'El producto lleva precio a mano: no hay margen que aplicar.'
                            : 'Opcional. Vacío: usa el margen del producto. Sirve cuando esta presentación gana distinto (la caja suele ganar menos).')
                        ->disabled($tipo === TipoMargen::Manual),
                ];
            })
            ->action(function (array $data, array $arguments, Repeater $component): void {
                $estado = $component->getRawState();
                $clave = (string) $arguments['item'];
                $fila = is_array($estado) ? ($estado[$clave] ?? null) : null;

                if (! is_array($estado) || ! is_array($fila)) {
                    return;
                }

                $nombre = is_string($data['nombre'] ?? null) ? trim($data['nombre']) : '';
                $fila['nombre'] = $nombre !== '' ? $nombre : null;

                // Con precio a mano el margen va deshabilitado y no llega: se deja como estaba.
                if (array_key_exists('margen', $data)) {
                    $fila['margen'] = is_numeric($data['margen']) ? $data['margen'] : null;
                }

                $estado[$clave] = $fila;
                $component->rawState($estado);
            });
    }

    /**
     * "Margen propio: 15 %" debajo del precio, si la presentación lo tiene.
     */
    private static function textoMargenPropio(Get $get): ?string
    {
        $margen = self::numero($get('margen'));
        $tipo = TipoMargen::desdeEstado($get('../../margen_tipo')) ?? TipoMargen::Manual;

        if ($margen === null || $tipo === TipoMargen::Manual) {
            return null;
        }

        return $tipo === TipoMargen::Monto
            ? 'Margen propio: '.lempiras($margen)
            : 'Margen propio: '.cantidadFormato($margen, 2).' %';
    }

    private static function abreviaturaUnidad(mixed $unidadId): string
    {
        if (! is_numeric($unidadId)) {
            return '';
        }

        return UnidadMedida::query()->whereKey((int) $unidadId)->value('abreviatura') ?? '';
    }

    /**
     * @return array<string, string>
     */
    private static function opcionesEquivalencia(mixed $unidadId): array
    {
        $abreviatura = self::abreviaturaUnidad($unidadId);
        $opciones = [];

        foreach (EquivalenciaPresentacion::cases() as $caso) {
            $opciones[$caso->value] = $caso->etiqueta($abreviatura);
        }

        return $opciones;
    }

    /**
     * Costo, precio y ganancia de una fila de presentaciones con lo que se
     * está tecleando. El costo es el ponderado × lo que lleva (Caja 50 lb =
     * 50 × costo; clavo suelto = costo / 100). El precio automático sale
     * del costo con que se fijaron los precios vigentes, igual que al vender.
     *
     * @return array{costo: float, automatico: float|null, precio: float|null, objetivo: float|null, tasa: float}
     */
    private static function datosPresentacion(Get $get): array
    {
        $tipo = TipoMargen::desdeEstado($get('../../margen_tipo')) ?? TipoMargen::Manual;
        $tasa = (TipoIsv::desdeEstado($get('../../tipo_isv')) ?? TipoIsv::Gravado15)->tasa();
        $modo = EquivalenciaPresentacion::desdeEstado($get('factor_modo')) ?? EquivalenciaPresentacion::Contiene;
        $factor = $modo->factor(self::numero($get('factor_cantidad')) ?? 0.0);

        // Al crear todavía no hay costo ponderado: se usa el costo inicial tecleado.
        $costoPonderado = self::numero($get('../../costo_promedio')) ?? self::numero($get('../../costo_inicial')) ?? 0.0;
        $costoPrecio = self::numero($get('../../costo_precio')) ?? 0.0;
        $costoPrecio = $costoPrecio > 0 ? $costoPrecio : $costoPonderado;

        $margenPropio = self::numero($get('margen'));
        $margenProducto = self::numero($get('../../margen_publico'));
        $automatico = null;

        if ($factor > 0 && $tipo === TipoMargen::Manual) {
            $base = self::numero($get('../../precio_venta')) ?? 0.0;
            $automatico = $base > 0 ? CalculadoraPrecio::redondear($base * $factor) : null;
        } elseif ($factor > 0 && $costoPrecio > 0 && ($margenPropio ?? $margenProducto) !== null) {
            // El margen propio en lempiras es por presentación; la fórmula lo quiere por unidad base.
            $margen = $tipo === TipoMargen::Monto && $margenPropio !== null
                ? $margenPropio / $factor
                : (float) ($margenPropio ?? $margenProducto);

            $automatico = CalculadoraPrecio::precio($costoPrecio, $tipo, $margen, $tasa, $factor);
        }

        $costo = $costoPonderado * $factor;

        return [
            'costo'      => $costo,
            'automatico' => $automatico,
            'precio'     => self::numero($get('precio_venta')) ?? $automatico,
            'objetivo'   => CalculadoraPrecio::margenObjetivo($tipo, $margenPropio, $margenProducto, $factor, $costo),
            'tasa'       => $tasa,
        ];
    }

    /**
     * @return array{texto: string, color: string}
     */
    private static function gananciaPresentacion(Get $get): array
    {
        $datos = self::datosPresentacion($get);

        return ProductoInfolist::ganancia($datos['precio'], $datos['costo'], $datos['tasa'], $datos['objetivo']);
    }

    private static function numero(mixed $valor): ?float
    {
        return is_numeric($valor) ? (float) $valor : null;
    }

    private static function usaMargen(Get $get): bool
    {
        $tipo = TipoMargen::desdeEstado($get('margen_tipo'));

        return $tipo !== null && $tipo !== TipoMargen::Manual;
    }

    private static function esMonto(Get $get): bool
    {
        return TipoMargen::desdeEstado($get('margen_tipo')) === TipoMargen::Monto;
    }

    /**
     * Precio que va a quedar y de dónde sale, con lo que se está tecleando,
     * para la tarjeta de la derecha.
     *
     * Al crear se usa el costo inicial. Al editar: si se cambió el margen o
     * el ISV, el costo ponderado de hoy (así se guarda); si no, el costo con
     * que se calcularon los precios vigentes. La ganancia es siempre sobre
     * el costo ponderado de hoy, con el precio que se va a cobrar.
     *
     * @return array{con_margen: bool, sin_costo: bool, creando: bool, precio: float|null, mayoreo: float|null, costo: float, margen_texto: string, margen: float, isv_texto: string, isv: float, exacto: float, ganancia: float|null, ganancia_fraccion: float|null, existencia: float|null, unidad: string, valor_inventario: float|null, ultimo_costo: float|null, costo_ponderado: float, pendiente: float|null}
     */
    private static function resumenPrecio(Get $get, ?Producto $record): array
    {
        $tipo = TipoMargen::desdeEstado($get('margen_tipo')) ?? TipoMargen::Manual;
        $isv = TipoIsv::desdeEstado($get('tipo_isv')) ?? TipoIsv::Gravado15;
        $margen = self::numero($get('margen_publico')) ?? 0.0;
        $margenMayoreo = self::numero($get('margen_mayoreo'));
        $conMargen = $tipo !== TipoMargen::Manual;
        $unidad = self::abreviaturaUnidad($get('unidad_medida_id'));

        if ($record === null) {
            $ponderado = self::numero($get('costo_inicial')) ?? 0.0;
            $costo = $ponderado;
            $existencia = (bool) $get('controla_stock') ? (self::numero($get('stock_inicial')) ?? 0.0) : null;
        } else {
            $ponderado = (float) $record->costo_promedio;
            $costo = self::cambioDePrecio($record, $tipo, $isv, $margen, $margenMayoreo) ? $ponderado : (float) $record->costo_precio;
            $costo = $costo > 0 ? $costo : $ponderado;
            $existencia = $record->controla_stock ? (float) $record->stock : null;
        }

        $paso = CalculadoraPrecio::desglose($costo, $tipo, $margen, $isv->tasa());

        if ($conMargen) {
            $precio = $costo > 0 ? $paso['precio'] : null;
            $mayoreo = $costo > 0 && $margenMayoreo !== null ? CalculadoraPrecio::precio($costo, $tipo, $margenMayoreo, $isv->tasa()) : null;
        } else {
            $precio = self::numero($get('precio_venta'));
            $mayoreo = self::numero($get('precio_mayoreo'));
        }

        $sinIsv = $precio !== null ? $precio / (1 + $isv->tasa()) : null;
        $ganancia = $sinIsv !== null && $ponderado > 0 ? $sinIsv - $ponderado : null;

        return [
            'con_margen'        => $conMargen,
            'sin_costo'         => $conMargen && $costo <= 0,
            'creando'           => $record === null,
            'precio'            => $precio,
            'mayoreo'           => $mayoreo !== null && $mayoreo > 0 ? $mayoreo : null,
            'costo'             => $costo,
            'margen_texto'      => $tipo === TipoMargen::Monto ? lempiras($margen).' por '.($unidad !== '' ? $unidad : 'unidad') : cantidadFormato($margen, 2).' %',
            'margen'            => $paso['sin_isv'] - $paso['costo'],
            'isv_texto'         => 'ISV '.cantidadFormato($isv->tasa() * 100, 2).' %',
            'isv'               => $paso['exacto'] - $paso['sin_isv'],
            'exacto'            => $paso['exacto'],
            'ganancia'          => $ganancia,
            'ganancia_fraccion' => $ganancia !== null ? $ganancia / $ponderado : null,
            'existencia'        => $existencia,
            'unidad'            => $unidad,
            'valor_inventario'  => $existencia !== null && $existencia > 0 && $ponderado > 0 ? round($existencia * $ponderado, 2) : null,
            'ultimo_costo'      => $record !== null && (float) $record->ultimo_costo > 0 ? (float) $record->ultimo_costo : null,
            'costo_ponderado'   => $ponderado,
            // Bajó el costo y el precio sigue sobre el anterior hasta que el dueño decida.
            'pendiente' => $conMargen && $record !== null && $ponderado > 0 && $costo - $ponderado > 0.00005 ? $ponderado : null,
        ];
    }

    private static function cambioDePrecio(Producto $record, TipoMargen $tipo, TipoIsv $isv, float $margen, ?float $mayoreo): bool
    {
        $mayoreoGuardado = $record->margen_mayoreo !== null ? (float) $record->margen_mayoreo : null;

        return $record->margen_tipo !== $tipo
            || $record->tipo_isv !== $isv
            || abs((float) $record->margen_publico - $margen) > 0.001
            || ($mayoreo === null) !== ($mayoreoGuardado === null)
            || abs((float) $mayoreoGuardado - (float) $mayoreo) > 0.001;
    }
}
