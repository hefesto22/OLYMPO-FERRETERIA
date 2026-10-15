<?php

declare(strict_types=1);

namespace App\Filament\Resources\UnidadesMedida\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UnidadMedidaForm
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
                    ->placeholder('Ej: Metro'),
                TextInput::make('abreviatura')
                    ->label('Abreviatura')
                    ->required()
                    ->maxLength(10)
                    ->unique()
                    ->placeholder('Ej: M'),
                Toggle::make('permite_decimales')
                    ->label('Se vende en fracciones')
                    ->helperText('Actívalo para metro, libra, galón… (permite vender 1.5).')
                    ->default(false),
                Toggle::make('activo')
                    ->label('Activa')
                    ->default(true),
            ]);
    }
}
