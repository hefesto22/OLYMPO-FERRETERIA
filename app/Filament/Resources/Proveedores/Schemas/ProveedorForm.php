<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proveedores\Schemas;

use App\Filament\Schemas\Components\RTNField;
use App\Filament\Schemas\Components\TelefonoHondurasField;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProveedorForm
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
                TextInput::make('contacto')
                    ->label('Persona de contacto')
                    ->maxLength(120),
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->maxLength(150),
                TextInput::make('direccion')
                    ->label('Dirección')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('dias_credito')
                    ->label('Días de crédito')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(365)
                    ->default(0),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true)
                    ->inline(false),
                Textarea::make('notas')
                    ->label('Notas')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
