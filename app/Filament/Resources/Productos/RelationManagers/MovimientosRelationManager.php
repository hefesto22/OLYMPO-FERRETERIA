<?php

declare(strict_types=1);

namespace App\Filament\Resources\Productos\RelationManagers;

use App\Filament\Resources\MovimientosInventario\Tables\MovimientosInventarioTable;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Kardex del producto dentro de su ficha. Solo lectura.
 */
class MovimientosRelationManager extends RelationManager
{
    protected static string $relationship = 'movimientos';

    protected static ?string $title = 'Kardex';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedQueueList;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return MovimientosInventarioTable::configure($table, mostrarProducto: false);
    }
}
