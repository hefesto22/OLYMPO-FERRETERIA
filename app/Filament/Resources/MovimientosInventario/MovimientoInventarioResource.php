<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovimientosInventario;

use App\Filament\Resources\MovimientosInventario\Pages\ListMovimientosInventario;
use App\Filament\Resources\MovimientosInventario\Tables\MovimientosInventarioTable;
use App\Models\MovimientoInventario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Kardex general: todos los movimientos de inventario. Solo lectura.
 */
class MovimientoInventarioResource extends Resource
{
    protected static ?string $model = MovimientoInventario::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $modelLabel = 'Movimiento de inventario';

    protected static ?string $pluralModelLabel = 'Kardex';

    protected static ?string $navigationLabel = 'Kardex';

    protected static ?string $slug = 'kardex';

    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function table(Table $table): Table
    {
        return MovimientosInventarioTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMovimientosInventario::route('/'),
        ];
    }
}
