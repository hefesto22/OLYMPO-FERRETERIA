<?php

declare(strict_types=1);

namespace App\Filament\Resources\Marcas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MarcaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(80)
                    ->unique()
                    ->placeholder('Ej: Truper'),
                Toggle::make('activo')
                    ->label('Activa')
                    ->default(true),
            ]);
    }
}
