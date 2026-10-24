<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntradasInventario;

use App\Filament\Resources\EntradasInventario\Pages\CreateEntradaInventario;
use App\Filament\Resources\EntradasInventario\Pages\EditEntradaInventario;
use App\Filament\Resources\EntradasInventario\Pages\ListEntradasInventario;
use App\Filament\Resources\EntradasInventario\Pages\ViewEntradaInventario;
use App\Filament\Resources\EntradasInventario\Schemas\EntradaInventarioForm;
use App\Filament\Resources\EntradasInventario\Schemas\EntradaInventarioInfolist;
use App\Filament\Resources\EntradasInventario\Tables\EntradasInventarioTable;
use App\Models\EntradaInventario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EntradaInventarioResource extends Resource
{
    protected static ?string $model = EntradaInventario::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static ?string $recordTitleAttribute = 'numero';

    protected static ?string $modelLabel = 'Entrada de inventario';

    protected static ?string $pluralModelLabel = 'Entradas de inventario';

    protected static ?string $slug = 'entradas-inventario';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function form(Schema $schema): Schema
    {
        return EntradaInventarioForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EntradaInventarioInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EntradasInventarioTable::configure($table);
    }

    /**
     * Solo los borradores se editan: una entrada confirmada ya movió el inventario.
     */
    public static function canEdit(Model $record): bool
    {
        return $record instanceof EntradaInventario && $record->esBorrador() && parent::canEdit($record);
    }

    /**
     * Solo se borran borradores. Lo confirmado se anula, no se borra.
     */
    public static function canDelete(Model $record): bool
    {
        return $record instanceof EntradaInventario && $record->esBorrador() && parent::canDelete($record);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListEntradasInventario::route('/'),
            'create' => CreateEntradaInventario::route('/create'),
            'view'   => ViewEntradaInventario::route('/{record}'),
            'edit'   => EditEntradaInventario::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero'];
    }
}
