<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras;

use App\Filament\Resources\Compras\Pages\CreateCompra;
use App\Filament\Resources\Compras\Pages\EditCompra;
use App\Filament\Resources\Compras\Pages\ListCompras;
use App\Filament\Resources\Compras\Pages\ViewCompra;
use App\Filament\Resources\Compras\Schemas\CompraForm;
use App\Filament\Resources\Compras\Schemas\CompraInfolist;
use App\Filament\Resources\Compras\Tables\ComprasTable;
use App\Models\Compra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CompraResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $recordTitleAttribute = 'numero';

    protected static ?string $modelLabel = 'Compra';

    protected static ?string $pluralModelLabel = 'Compras';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Compras';
    }

    public static function form(Schema $schema): Schema
    {
        return CompraForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompraInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComprasTable::configure($table);
    }

    /**
     * Solo los borradores se editan: una compra confirmada ya movió el inventario.
     */
    public static function canEdit(Model $record): bool
    {
        return $record instanceof Compra && $record->esBorrador() && parent::canEdit($record);
    }

    /**
     * Solo se borran borradores. Lo confirmado se anula, no se borra.
     */
    public static function canDelete(Model $record): bool
    {
        return $record instanceof Compra && $record->esBorrador() && parent::canDelete($record);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCompras::route('/'),
            'create' => CreateCompra::route('/create'),
            'view'   => ViewCompra::route('/{record}'),
            'edit'   => EditCompra::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'numero_factura_proveedor'];
    }
}
