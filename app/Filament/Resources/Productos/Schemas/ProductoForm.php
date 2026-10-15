<?php

declare(strict_types=1);

namespace App\Filament\Resources\Productos\Schemas;

use App\Enums\EquivalenciaPresentacion;
use App\Enums\TipoIsv;
use App\Enums\TipoMargen;
use App\Filament\Schemas\Components\MontoField;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use App\Support\Precios\CalculadoraPrecio;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

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
                TextInput::make('codigo_barras')
                    ->label('Código de barras')
                    ->maxLength(60)
                    ->unique()
                    ->prefixIcon(Heroicon::OutlinedQrCode)
                    ->placeholder('Escanéalo aquí'),
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
            ->description('Con margen, el precio sale del costo promedio: costo + margen + ISV, redondeado al lempira hacia arriba.')
            ->icon(Heroicon::OutlinedBanknotes)
            ->columns(3)
            ->columnSpanFull()
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
                    ->columnSpan(2),
                Select::make('tipo_isv')
                    ->label('ISV')
                    ->options(TipoIsv::class)
                    ->default(TipoIsv::Gravado15)
                    ->required()
                    ->live(),

                // ─── Con margen ─────────────────────────────────────
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
                TextEntry::make('precio_calculado')
                    ->label('Precio de venta (ISV incl.)')
                    ->state(fn (Get $get, ?Producto $record): string => self::vistaPrecio($get, $record)['precio'])
                    ->helperText(fn (Get $get, ?Producto $record): string => self::vistaPrecio($get, $record)['explicacion'])
                    ->weight('bold')
                    ->visible(fn (Get $get): bool => self::usaMargen($get)),

                // ─── A mano ─────────────────────────────────────────
                MontoField::make('precio_venta', 'Precio de venta')
                    ->helperText('Por unidad base, con ISV incluido.')
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => ! self::usaMargen($get)),
                MontoField::make('precio_mayoreo', 'Precio mayoreo / contratista')
                    ->required(false)
                    ->default(null)
                    ->helperText('Opcional. Con ISV incluido.')
                    ->visible(fn (Get $get): bool => ! self::usaMargen($get)),

                // ─── Costos ─────────────────────────────────────────
                TextEntry::make('costo_promedio_vista')
                    ->label('Costo promedio (sin ISV)')
                    ->state(fn (?Producto $record): string => $record !== null ? lempiras((float) $record->costo_promedio, 4) : '—')
                    ->hiddenOn('create'),
                TextEntry::make('ultimo_costo_vista')
                    ->label('Último costo de compra (sin ISV)')
                    ->state(fn (?Producto $record): string => $record !== null ? lempiras((float) $record->ultimo_costo, 4) : '—')
                    ->hiddenOn('create'),
                TextEntry::make('ganancia_vista')
                    ->label('Ganancia sobre el costo promedio')
                    ->state(fn (Get $get, ?Producto $record): string => self::ganancia($get, $record))
                    ->hiddenOn('create'),
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
            ->description('Otras formas de vender o comprar: caja de 50 lb, bolsa de 1 lb, o el clavo suelto ("100 por LB"). El inventario se sigue llevando en la unidad base.')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->columnSpanFull()
            ->collapsible()
            ->schema([
                Repeater::make('presentaciones')
                    ->hiddenLabel()
                    ->relationship()
                    ->table([
                        TableColumn::make('Unidad')->markAsRequired(),
                        TableColumn::make('Cantidad')->markAsRequired(),
                        TableColumn::make('Equivale a')->markAsRequired(),
                        TableColumn::make('Nombre'),
                        TableColumn::make('Código de barras'),
                        TableColumn::make('Precio (ISV incl.)'),
                    ])
                    ->schema([
                        Select::make('unidad_medida_id')
                            ->options(fn (): array => UnidadMedida::query()
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        TextInput::make('factor_cantidad')
                            ->numeric()
                            ->required()
                            ->minValue(0.0001)
                            ->step(0.0001)
                            // El inventario va a 6 decimales: más de 10,000 por unidad base ya no descuenta exacto.
                            ->maxValue(fn (Get $get): ?int => EquivalenciaPresentacion::desdeEstado($get('factor_modo')) === EquivalenciaPresentacion::PorUnidadBase ? 10000 : null)
                            ->validationMessages([
                                'max' => 'Hasta 10,000 por unidad base. Si son más, usa la unidad suelta como unidad base del producto.',
                            ]),
                        Select::make('factor_modo')
                            ->options(fn (Get $get): array => self::opcionesEquivalencia($get('../../unidad_medida_id')))
                            ->default(EquivalenciaPresentacion::Contiene->value)
                            // El modelo lo entrega como enum; el select trabaja con su valor.
                            ->formatStateUsing(fn (mixed $state): string => (EquivalenciaPresentacion::desdeEstado($state) ?? EquivalenciaPresentacion::Contiene)->value)
                            ->selectablePlaceholder(false)
                            ->required(),
                        TextInput::make('nombre')
                            ->maxLength(80)
                            ->placeholder('Bolsa 1 lb'),
                        TextInput::make('codigo_barras')
                            ->maxLength(60)
                            ->unique(table: ProductoPresentacion::class, column: 'codigo_barras'),
                        TextInput::make('precio_venta')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix((string) config('honduras.moneda.simbolo', 'L.'))
                            ->placeholder(fn (Get $get): string => TipoMargen::desdeEstado($get('../../margen_tipo')) === TipoMargen::Manual
                                ? 'Base × cantidad'
                                : 'Automático'),
                    ])
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addActionLabel('Agregar presentación'),
            ]);
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
     * Precio que va a quedar y de dónde sale, con lo que se está tecleando.
     *
     * Al crear se usa el costo inicial. Al editar: si se cambió el margen o
     * el ISV, el costo promedio de hoy (así se guarda); si no, el costo con
     * que se calcularon los precios vigentes.
     *
     * @return array{precio: string, explicacion: string}
     */
    private static function vistaPrecio(Get $get, ?Producto $record): array
    {
        $tipo = TipoMargen::desdeEstado($get('margen_tipo')) ?? TipoMargen::Manual;
        $isv = TipoIsv::desdeEstado($get('tipo_isv')) ?? TipoIsv::Gravado15;
        $margen = is_numeric($get('margen_publico')) ? (float) $get('margen_publico') : 0.0;
        $mayoreo = is_numeric($get('margen_mayoreo')) ? (float) $get('margen_mayoreo') : null;

        if ($record === null) {
            $costo = is_numeric($get('costo_inicial')) ? (float) $get('costo_inicial') : 0.0;
        } elseif (self::cambioDePrecio($record, $tipo, $isv, $margen, $mayoreo)) {
            $costo = (float) $record->costo_promedio;
        } else {
            $costo = (float) $record->costo_precio;
        }

        if ($costo <= 0) {
            return [
                'precio'      => 'Sin costo todavía',
                'explicacion' => 'El precio se calcula con la primera compra o la existencia inicial.',
            ];
        }

        $paso = CalculadoraPrecio::desglose($costo, $tipo, $margen, $isv->tasa());
        $margenTexto = $tipo === TipoMargen::Monto ? lempiras($margen) : cantidadFormato($margen, 2).' %';
        $explicacion = 'Costo '.lempiras($costo).' + '.$margenTexto.' = '.lempiras($paso['sin_isv']);

        if ($isv->tasa() > 0) {
            $explicacion .= ' · con ISV '.lempiras($paso['exacto']);
        }

        $explicacion .= ' · redondeado: '.lempiras($paso['precio']).'.';
        $precio = lempiras($paso['precio']);

        if ($mayoreo !== null) {
            $precio .= ' · mayoreo '.lempiras(CalculadoraPrecio::precio($costo, $tipo, $mayoreo, $isv->tasa()));
        }

        return ['precio' => $precio, 'explicacion' => $explicacion];
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

    /**
     * Ganancia sobre el costo promedio con el precio que se va a cobrar
     * (el tecleado o el calculado con el margen).
     */
    private static function ganancia(Get $get, ?Producto $producto): string
    {
        $costo = $producto !== null ? (float) $producto->costo_promedio : 0.0;
        $isv = TipoIsv::desdeEstado($get('tipo_isv')) ?? TipoIsv::Gravado15;

        if (self::usaMargen($get) && $producto !== null) {
            $precio = (float) $producto->precio_venta;
        } else {
            $precio = is_numeric($get('precio_venta')) ? (float) $get('precio_venta') : 0.0;
        }

        $precioSinIsv = $precio / (1 + $isv->tasa());

        if ($costo <= 0 || $precioSinIsv <= 0) {
            return '—';
        }

        return porcentaje(($precioSinIsv - $costo) / $costo, 1);
    }
}
