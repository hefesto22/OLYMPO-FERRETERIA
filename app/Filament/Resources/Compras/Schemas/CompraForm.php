<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Schemas;

use App\Enums\CategoriaGasto;
use App\Enums\TipoDocumentoCompra;
use App\Enums\TipoIsv;
use App\Filament\Schemas\Components\MontoField;
use App\Filament\Schemas\Components\RTNField;
use App\Filament\Schemas\Components\TelefonoHondurasField;
use App\Models\Compra;
use App\Models\Proveedor;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registrar una compra como viene en el papel del proveedor, en un
 * modal: qué dio (factura o recibo), de quién y por cuánto. El ISV de
 * cada gravado se calcula solo y se puede corregir; el total se suma
 * solo y se ve a la derecha, como cierra la factura.
 */
class CompraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ToggleButtons::make('tipo_documento')
                    ->label('¿Qué te dio el proveedor?')
                    ->options(TipoDocumentoCompra::class)
                    // Como Recibo/Factura en la caja: la opción elegida va oscura.
                    ->colors([
                        TipoDocumentoCompra::Factura->value => 'gray',
                        TipoDocumentoCompra::Recibo->value  => 'gray',
                    ])
                    ->default(TipoDocumentoCompra::Factura)
                    ->required()
                    ->inline()
                    ->live()
                    ->helperText(fn (Get $get): ?string => self::tipo($get)?->getDescription()),

                Grid::make(['default' => 1, 'md' => 3])
                    ->schema([
                        DatePicker::make('fecha')
                            ->label('Fecha de compra')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),
                        TextInput::make('numero_factura')
                            ->label(fn (Get $get): string => self::esRecibo($get) ? 'N.º de recibo' : 'N.º de factura')
                            ->placeholder(fn (Get $get): string => self::esRecibo($get) ? 'Opcional' : '000-001-01-00001234')
                            ->required(fn (Get $get): bool => ! self::esRecibo($get))
                            ->maxLength(40)
                            // Una factura del mismo proveedor no se registra dos veces; sin número no se compara.
                            ->scopedUnique(
                                ignoreRecord: true,
                                modifyQueryUsing: fn (Builder $query, Get $get, mixed $state): Builder => blank($state)
                                    ? $query->whereRaw('1 = 0')
                                    : $query->where('proveedor_id', $get('proveedor_id')),
                            )
                            ->validationMessages(['unique' => 'Esta factura de este proveedor ya está registrada.']),
                        Select::make('categoria')
                            ->label('¿En qué se gastó?')
                            ->options(CategoriaGasto::class)
                            ->default(CategoriaGasto::Mercaderia)
                            ->required()
                            ->native(false),
                        Select::make('proveedor_id')
                            ->label('Proveedor (empresa)')
                            ->relationship('proveedor', 'nombre', fn (Builder $query): Builder => $query->where('activo', true))
                            ->searchable(['nombre', 'rtn'])
                            ->preload()
                            ->placeholder(fn (Get $get): string => self::esRecibo($get) ? 'Opcional' : 'Busca o regístralo con +')
                            ->required(fn (Get $get): bool => ! self::esRecibo($get))
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                $set('rtn_proveedor', is_numeric($state) ? Proveedor::query()->whereKey((int) $state)->value('rtn') : null);
                            })
                            ->createOptionModalHeading('Nuevo proveedor')
                            ->createOptionForm([
                                TextInput::make('nombre')
                                    ->label('Nombre o razón social')
                                    ->required()
                                    ->maxLength(150),
                                RTNField::make()
                                    ->unique(table: 'proveedores', column: 'rtn'),
                                TelefonoHondurasField::make('telefono', 'Teléfono'),
                            ])
                            ->columnSpan(['md' => 2]),
                        RTNField::make('rtn_proveedor')
                            ->label('RTN del proveedor')
                            ->validationAttribute('RTN del proveedor')
                            ->helperText('Lo pide el SAR para descontar el ISV.')
                            ->hidden(fn (Get $get): bool => self::esRecibo($get))
                            ->live(onBlur: true),
                    ]),

                Fieldset::make(fn (Get $get): string => self::esRecibo($get) ? 'Lo que se pagó' : 'Montos de la factura')
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->schema([
                        self::gravado('gravado_15', 'Gravado 15 %', 'isv_15', TipoIsv::Gravado15),
                        self::isv('isv_15', 'ISV 15 %'),
                        self::gravado('gravado_18', 'Gravado 18 %', 'isv_18', TipoIsv::Gravado18),
                        self::isv('isv_18', 'ISV 18 %'),
                        Text::make('El ISV se calcula solo al escribir el gravado. Corrígelo si la factura dice otra cosa.')
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->hidden(fn (Get $get): bool => self::esRecibo($get))
                            ->columnSpanFull(),
                        MontoField::make('exento')
                            ->label(fn (Get $get): string => self::esRecibo($get) ? 'Total del recibo' : 'Exento')
                            ->live(onBlur: true)
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    if (self::total($get) <= 0) {
                                        $fail(self::esRecibo($get) ? 'Escribe cuánto se pagó.' : 'Escribe los montos de la factura.');
                                    }
                                },
                            ]),
                        View::make('filament.compras.totales')
                            ->viewData(fn (Get $get): array => self::totales($get))
                            ->hidden(fn (Get $get): bool => self::esRecibo($get))
                            ->columnSpan(['sm' => 1, 'lg' => 3]),
                    ]),

                Textarea::make('notas')
                    ->label('Notas')
                    ->placeholder('Opcional')
                    ->rows(2),
            ]);
    }

    /**
     * Monto gravado: al escribirlo se calcula su ISV (15 % o 18 %).
     */
    private static function gravado(string $campo, string $etiqueta, string $campoIsv, TipoIsv $tipo): TextInput
    {
        return MontoField::make($campo, $etiqueta)
            ->live(onBlur: true)
            ->hidden(fn (Get $get): bool => self::esRecibo($get))
            ->afterStateUpdated(function (Set $set, mixed $state) use ($campoIsv, $tipo): void {
                $set($campoIsv, is_numeric($state) ? Compra::isvDe((float) $state, $tipo) : 0);
            });
    }

    private static function isv(string $campo, string $etiqueta): TextInput
    {
        return MontoField::make($campo, $etiqueta)
            ->live(onBlur: true)
            ->hidden(fn (Get $get): bool => self::esRecibo($get));
    }

    private static function tipo(Get $get): ?TipoDocumentoCompra
    {
        return TipoDocumentoCompra::desdeEstado($get('tipo_documento'));
    }

    private static function esRecibo(Get $get): bool
    {
        return self::tipo($get) === TipoDocumentoCompra::Recibo;
    }

    /**
     * Igual que Compra::calcularTotales(): un recibo es solo lo pagado.
     */
    private static function total(Get $get): float
    {
        $exento = self::numero($get('exento'));

        if (self::esRecibo($get)) {
            return round($exento, 2);
        }

        return round(
            self::numero($get('gravado_15')) + self::numero($get('isv_15'))
            + self::numero($get('gravado_18')) + self::numero($get('isv_18'))
            + $exento,
            2,
        );
    }

    /**
     * Lo que muestra el cuadro de la derecha: sin ISV, ISV, total y si
     * ese ISV se puede descontar.
     *
     * @return array{sin_isv: float, isv: float, total: float, sin_rtn: bool}
     */
    private static function totales(Get $get): array
    {
        $isv = round(self::numero($get('isv_15')) + self::numero($get('isv_18')), 2);
        $total = self::total($get);

        return [
            'sin_isv' => round($total - $isv, 2),
            'isv'     => $isv,
            'total'   => $total,
            'sin_rtn' => $isv > 0 && blank($get('rtn_proveedor')),
        ];
    }

    private static function numero(mixed $valor): float
    {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }
}
