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
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registrar una compra como viene en el papel del proveedor: qué dio
 * (factura o recibo), de quién y por cuánto. El ISV de cada gravado se
 * calcula solo y se puede corregir; el total se suma solo.
 */
class CompraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('¿Qué te dio el proveedor?')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema([
                        ToggleButtons::make('tipo_documento')
                            ->hiddenLabel()
                            ->options(TipoDocumentoCompra::class)
                            ->default(TipoDocumentoCompra::Factura)
                            ->required()
                            ->inline()
                            ->live()
                            ->helperText(fn (Get $get): ?string => self::tipo($get)?->getDescription()),
                    ]),

                Section::make('Proveedor')
                    ->icon(Heroicon::OutlinedTruck)
                    ->columns(2)
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
                            ]),
                        RTNField::make('rtn_proveedor')
                            ->label('RTN del proveedor')
                            ->validationAttribute('RTN del proveedor')
                            ->helperText('Necesario para que el SAR acepte el descuento del ISV. Si el proveedor no lo tenía, se le guarda.')
                            ->hidden(fn (Get $get): bool => self::esRecibo($get))
                            ->live(onBlur: true),
                    ]),

                Section::make('Montos')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(2)
                    ->schema([
                        self::gravado('gravado_15', 'Gravado 15 %', 'isv_15', TipoIsv::Gravado15),
                        self::isv('isv_15', 'I.S.V. 15 %'),
                        self::gravado('gravado_18', 'Gravado 18 %', 'isv_18', TipoIsv::Gravado18),
                        self::isv('isv_18', 'I.S.V. 18 %'),
                        MontoField::make('exento')
                            ->label(fn (Get $get): string => self::esRecibo($get) ? 'Total pagado' : 'Exento')
                            ->helperText(fn (Get $get): ?string => self::esRecibo($get) ? null : 'Lo que no lleva ISV.')
                            ->live(onBlur: true)
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    if (self::total($get) <= 0) {
                                        $fail(self::esRecibo($get) ? 'Escribe cuánto se pagó.' : 'Escribe los montos de la factura.');
                                    }
                                },
                            ]),
                        TextEntry::make('total_calculado')
                            ->label('Total')
                            ->state(fn (Get $get): string => lempiras(self::total($get)))
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->helperText('Gravado + ISV + exento. Se suma solo.')
                            ->hidden(fn (Get $get): bool => self::esRecibo($get)),
                        TextEntry::make('isv_que_se_descuenta')
                            ->label('ISV que se descuenta de tus ventas')
                            ->state(fn (Get $get): string => self::isvAcreditable($get))
                            ->hidden(fn (Get $get): bool => self::esRecibo($get))
                            ->columnSpanFull(),
                        Textarea::make('notas')
                            ->label('Notas')
                            ->placeholder('Opcional')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
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
            ->hidden(fn (Get $get): bool => self::esRecibo($get))
            ->helperText('Se calcula solo. Corrígelo si la factura dice otra cosa.');
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

    private static function isvAcreditable(Get $get): string
    {
        $isv = round(self::numero($get('isv_15')) + self::numero($get('isv_18')), 2);

        if ($isv <= 0) {
            return 'Esta factura no trae ISV.';
        }

        if (blank($get('rtn_proveedor'))) {
            return 'Sin el RTN del proveedor, el SAR no acepta descontar los '.lempiras($isv).'.';
        }

        return lempiras($isv);
    }

    private static function numero(mixed $valor): float
    {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }
}
