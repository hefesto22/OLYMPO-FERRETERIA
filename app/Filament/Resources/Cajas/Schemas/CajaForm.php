<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cajas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CajaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(60)
                    ->unique()
                    ->placeholder('Caja 2'),
                TextInput::make('punto_emision')
                    ->label('Punto de emisión')
                    ->required()
                    ->length(3)
                    ->regex('/^\d{3}$/')
                    ->unique()
                    ->placeholder('002')
                    ->helperText('Segundo bloque del número de factura. Cada caja tiene el suyo y su propio CAI.'),
                Toggle::make('activo')
                    ->label('Activa')
                    ->default(true),
            ]);
    }
}
