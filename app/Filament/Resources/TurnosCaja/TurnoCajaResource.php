<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnosCaja;

use App\Filament\Resources\TurnosCaja\Pages\ListTurnosCaja;
use App\Filament\Resources\TurnosCaja\Pages\ViewTurnoCaja;
use App\Filament\Resources\TurnosCaja\RelationManagers\MovimientosCajaRelationManager;
use App\Filament\Resources\TurnosCaja\RelationManagers\VentasRelationManager;
use App\Filament\Resources\TurnosCaja\Schemas\TurnoCajaInfolist;
use App\Filament\Resources\TurnosCaja\Tables\TurnosCajaTable;
use App\Models\TurnoCaja;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Turnos de caja y sus arqueos. Los turnos se abren desde el punto de
 * venta; aquí se consultan, se reimprime el cierre y un supervisor
 * puede cerrar el turno de otro cajero.
 */
class TurnoCajaResource extends Resource
{
    protected static ?string $model = TurnoCaja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $modelLabel = 'Turno de caja';

    protected static ?string $pluralModelLabel = 'Turnos de caja';

    protected static ?string $slug = 'turnos-caja';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    public static function infolist(Schema $schema): Schema
    {
        return TurnoCajaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TurnosCajaTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VentasRelationManager::class,
            MovimientosCajaRelationManager::class,
        ];
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
            'index' => ListTurnosCaja::route('/'),
            'view'  => ViewTurnoCaja::route('/{record}'),
        ];
    }
}
