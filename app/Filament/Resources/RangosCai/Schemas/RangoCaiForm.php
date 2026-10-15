<?php

declare(strict_types=1);

namespace App\Filament\Resources\RangosCai\Schemas;

use App\Models\Caja;
use App\Models\ConfiguracionEmpresa;
use App\Models\RangoCai;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RangoCaiForm
{
    /** Formato SAR: 6 bloques hexadecimales (el último de 2). */
    private const REGEX_CAI = '/^[A-Fa-f0-9]{6}-[A-Fa-f0-9]{6}-[A-Fa-f0-9]{6}-[A-Fa-f0-9]{6}-[A-Fa-f0-9]{6}-[A-Fa-f0-9]{2}$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('caja_id')
                    ->label('Caja')
                    ->relationship('caja', 'nombre')
                    ->required()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $caja = is_numeric($state) ? Caja::query()->find((int) $state) : null;

                        $set('punto_emision', $caja?->punto_emision);
                    })
                    ->disabled(fn (?RangoCai $record): bool => self::enUso($record)),
                TextInput::make('cai')
                    ->label('CAI')
                    ->required()
                    ->maxLength(37)
                    ->regex(self::REGEX_CAI)
                    ->validationMessages(['regex' => 'Formato: XXXXXX-XXXXXX-XXXXXX-XXXXXX-XXXXXX-XX (letras A-F y números).'])
                    ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state)))
                    ->placeholder('3E1F2A-8B9C0D-4E5F6A-7B8C9D-0E1F2A-3B'),
                TextInput::make('establecimiento')
                    ->label('Establecimiento')
                    ->required()
                    ->length(3)
                    ->regex('/^\d{3}$/')
                    ->default(fn (): string => ConfiguracionEmpresa::actual()->establecimiento)
                    ->disabled(fn (?RangoCai $record): bool => self::enUso($record)),
                TextInput::make('punto_emision')
                    ->label('Punto de emisión')
                    ->required()
                    ->length(3)
                    ->regex('/^\d{3}$/')
                    ->readOnly()
                    ->helperText('Se toma de la caja.'),
                TextInput::make('tipo_documento')
                    ->label('Tipo de documento')
                    ->required()
                    ->length(2)
                    ->default('01')
                    ->readOnly()
                    ->helperText('01 = Factura'),
                DatePicker::make('fecha_limite_emision')
                    ->label('Fecha límite de emisión')
                    ->required(),
                TextInput::make('rango_inicial')
                    ->label('Número inicial')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->disabled(fn (?RangoCai $record): bool => self::enUso($record)),
                TextInput::make('rango_final')
                    ->label('Número final')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->gte('rango_inicial')
                    ->disabled(fn (?RangoCai $record): bool => self::enUso($record)),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true)
                    ->helperText('Al activar este rango se desactiva el anterior de la misma caja.'),
                TextInput::make('notas')
                    ->label('Notas')
                    ->maxLength(255),
            ]);
    }

    /**
     * Un rango del que ya salieron facturas no cambia de caja ni de números.
     */
    private static function enUso(?RangoCai $rango): bool
    {
        return $rango !== null && $rango->siguiente > $rango->rango_inicial;
    }
}
