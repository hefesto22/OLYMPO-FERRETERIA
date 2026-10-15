<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clientes\Schemas;

use App\Enums\ListaPrecio;
use App\Filament\Schemas\Components\MontoField;
use App\Filament\Schemas\Components\RTNField;
use App\Filament\Schemas\Components\TelefonoHondurasField;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ClienteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre o razón social')
                    ->required()
                    ->maxLength(150)
                    ->columnSpanFull(),
                RTNField::make()
                    ->unique(),
                TelefonoHondurasField::make('telefono', 'Teléfono'),
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->maxLength(150),
                ToggleButtons::make('tipo_precio')
                    ->label('Precio')
                    ->options(ListaPrecio::class)
                    ->default(ListaPrecio::Publico)
                    ->inline()
                    ->required()
                    ->helperText('Contratistas y clientes frecuentes: mayoreo. El cajero lo puede cambiar en la venta.'),
                TextInput::make('direccion')
                    ->label('Dirección')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Fieldset::make('Crédito')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('permite_credito')
                            ->label('Puede comprar al crédito')
                            ->live()
                            ->inline(false),
                        MontoField::make('limite_credito', 'Límite de crédito')
                            ->visible(fn (Get $get): bool => (bool) $get('permite_credito')),
                        TextInput::make('dias_credito')
                            ->label('Días de crédito')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(365)
                            ->default(0)
                            ->visible(fn (Get $get): bool => (bool) $get('permite_credito')),
                    ]),
                Textarea::make('notas')
                    ->label('Notas')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true),
            ]);
    }
}
