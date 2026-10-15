<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categorias\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class CategoriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(120)
                    // Único dentro de la misma categoría padre.
                    ->unique(modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                        ->where('parent_id', $get('parent_id'))
                        ->whereNull('deleted_at')),
                Select::make('parent_id')
                    ->label('Pertenece a')
                    ->relationship(
                        'parent',
                        'nombre',
                        fn (Builder $query): Builder => $query->whereNull('parent_id'),
                        ignoreRecord: true,
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->placeholder('Categoría principal')
                    ->helperText('Déjalo vacío para una categoría principal.'),
                TextInput::make('descripcion')
                    ->label('Descripción')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Toggle::make('activo')
                    ->label('Activa')
                    ->default(true),
            ]);
    }
}
