<?php

declare(strict_types=1);

namespace App\Filament\Resources\Productos\Schemas;

use App\Enums\TipoIsv;
use App\Filament\Schemas\Components\MontoField;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
            ->icon(Heroicon::OutlinedBanknotes)
            ->columns(3)
            ->columnSpanFull()
            ->schema([
                MontoField::make('precio_venta', 'Precio de venta')
                    ->helperText('Por unidad base, con ISV incluido.')
                    ->live(onBlur: true),
                MontoField::make('precio_mayoreo', 'Precio mayoreo / contratista')
                    ->required(false)
                    ->default(null)
                    ->helperText('Opcional. Con ISV incluido.'),
                Select::make('tipo_isv')
                    ->label('ISV')
                    ->options(TipoIsv::class)
                    ->default(TipoIsv::Gravado15)
                    ->required()
                    ->live(),
                TextEntry::make('costo_promedio_vista')
                    ->label('Costo promedio (sin ISV)')
                    ->state(fn (?Producto $record): string => $record !== null ? lempiras((float) $record->costo_promedio, 4) : '—')
                    ->hiddenOn('create'),
                TextEntry::make('ultimo_costo_vista')
                    ->label('Último costo de compra (sin ISV)')
                    ->state(fn (?Producto $record): string => $record !== null ? lempiras((float) $record->ultimo_costo, 4) : '—')
                    ->hiddenOn('create'),
                TextEntry::make('margen_vista')
                    ->label('Margen sobre el costo promedio')
                    ->state(fn (Get $get, ?Producto $record): string => self::margen($get('precio_venta'), $get('tipo_isv'), $record))
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
                    ->helperText('Desactívalo para servicios: corte de tubo, copia de llave, flete.'),
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
            ->description('Otras unidades en que se compra o vende: caja de 100, rollo de 100 m, cubeta de 5 galones. El inventario se sigue llevando en la unidad base.')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->columnSpanFull()
            ->collapsible()
            ->schema([
                Repeater::make('presentaciones')
                    ->hiddenLabel()
                    ->relationship()
                    ->table([
                        TableColumn::make('Unidad')->markAsRequired(),
                        TableColumn::make('Contiene')->markAsRequired(),
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
                        TextInput::make('factor')
                            ->numeric()
                            ->required()
                            ->minValue(0.0001)
                            ->step(0.0001)
                            ->suffix(fn (Get $get): string => self::abreviaturaUnidad($get('../../unidad_medida_id'))),
                        TextInput::make('nombre')
                            ->maxLength(80)
                            ->placeholder('Caja x100'),
                        TextInput::make('codigo_barras')
                            ->maxLength(60)
                            ->unique(table: ProductoPresentacion::class, column: 'codigo_barras'),
                        TextInput::make('precio_venta')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix((string) config('honduras.moneda.simbolo', 'L.'))
                            ->placeholder('Base × factor'),
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
     * Margen con el precio que se está tecleando y el costo promedio guardado.
     */
    private static function margen(mixed $precio, mixed $tipoIsv, ?Producto $producto): string
    {
        $costo = $producto !== null ? (float) $producto->costo_promedio : 0.0;
        $tasa = TipoIsv::desdeEstado($tipoIsv)?->tasa() ?? 0.0;
        $precioSinIsv = is_numeric($precio) ? (float) $precio / (1 + $tasa) : 0.0;

        if ($costo <= 0 || $precioSinIsv <= 0) {
            return '—';
        }

        return porcentaje(($precioSinIsv - $costo) / $precioSinIsv, 1);
    }
}
