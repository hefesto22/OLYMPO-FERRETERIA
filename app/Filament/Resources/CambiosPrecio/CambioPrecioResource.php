<?php

declare(strict_types=1);

namespace App\Filament\Resources\CambiosPrecio;

use App\Filament\Resources\CambiosPrecio\Pages\ListCambiosPrecio;
use App\Filament\Resources\CambiosPrecio\Tables\CambiosPrecioTable;
use App\Models\CambioPrecio;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Precios que se movieron por el costo: las subidas automáticas y las
 * bajas que esperan que el dueño decida. Solo lectura, salvo aprobar o
 * mantener (permiso Aprobar:CambioPrecio).
 */
class CambioPrecioResource extends Resource
{
    protected static ?string $model = CambioPrecio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingDown;

    protected static ?string $modelLabel = 'Cambio de precio';

    protected static ?string $pluralModelLabel = 'Cambios de precio';

    protected static ?string $slug = 'cambios-precio';

    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function table(Table $table): Table
    {
        return CambiosPrecioTable::configure($table);
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
            'index' => ListCambiosPrecio::route('/'),
        ];
    }

    /**
     * Cuántas bajas de costo esperan decisión.
     */
    public static function getNavigationBadge(): ?string
    {
        $pendientes = CambioPrecio::query()->pendientes()->count();

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Precios por revisar';
    }
}
