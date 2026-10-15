<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Schemas\Components\RTNField;
use App\Models\ConfiguracionEmpresa;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Datos del emisor que salen en facturas y recibos, y topes de descuento
 * por rol. Singleton: siempre edita la única fila de configuracion_empresa.
 *
 * @property Schema $form
 */
class ConfiguracionEmpresaPage extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.configuracion-empresa';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Datos de la empresa';

    protected static ?string $title = 'Datos de la empresa';

    protected static ?string $slug = 'datos-empresa';

    protected static ?int $navigationSort = 3;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Facturación';
    }

    public function mount(): void
    {
        $configuracion = ConfiguracionEmpresa::actual();

        $this->form->fill($configuracion->only([
            'razon_social',
            'nombre_comercial',
            'rtn',
            'direccion',
            'telefono',
            'correo',
            'establecimiento',
            'mensaje_ticket',
            'descuento_max_cajero',
            'descuento_max_supervisor',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Emisor')
                    ->description('Así aparece la empresa en facturas y recibos. Debe coincidir con el registro del SAR.')
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->columns(2)
                    ->schema([
                        TextInput::make('razon_social')
                            ->label('Razón social')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('nombre_comercial')
                            ->label('Nombre comercial')
                            ->maxLength(150),
                        RTNField::make('rtn', true),
                        TextInput::make('establecimiento')
                            ->label('Código de establecimiento')
                            ->required()
                            ->length(3)
                            ->regex('/^\d{3}$/')
                            ->default('000')
                            ->helperText('Primer bloque del número de factura (000-001-01-…).'),
                        TextInput::make('direccion')
                            ->label('Dirección')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('correo')
                            ->label('Correo')
                            ->email()
                            ->maxLength(150),
                        TextInput::make('mensaje_ticket')
                            ->label('Mensaje al pie del ticket')
                            ->maxLength(255)
                            ->placeholder('¡Gracias por su compra!')
                            ->columnSpanFull(),
                    ]),

                Section::make('Descuentos en caja')
                    ->description('Máximo descuento por línea que puede dar cada rol. Administrador y super admin no tienen tope.')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->columns(2)
                    ->schema([
                        TextInput::make('descuento_max_cajero')
                            ->label('Cajero')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),
                        TextInput::make('descuento_max_supervisor')
                            ->label('Supervisor')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        ConfiguracionEmpresa::actual()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Datos guardados')
            ->body('Se aplican a los documentos que se emitan desde ahora.')
            ->send();
    }
}
