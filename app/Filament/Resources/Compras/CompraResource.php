<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras;

use App\Filament\Resources\Compras\Pages\CreateCompra;
use App\Filament\Resources\Compras\Pages\EditCompra;
use App\Filament\Resources\Compras\Pages\ListCompras;
use App\Filament\Resources\Compras\Schemas\CompraForm;
use App\Filament\Resources\Compras\Tables\ComprasTable;
use App\Filament\Resources\Compras\Widgets\ResumenComprasMes;
use App\Models\Compra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Facturas y recibos de los proveedores, solo con sus totales: lo que se
 * gastó y el ISV que se descuenta. Los productos que entran al stock se
 * registran en Entradas de inventario.
 */
class CompraResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $recordTitleAttribute = 'numero_factura';

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

    public static function table(Table $table): Table
    {
        return ComprasTable::configure($table);
    }

    /**
     * Una compra borrada se puede abrir para restaurarla.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getWidgets(): array
    {
        return [
            ResumenComprasMes::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCompras::route('/'),
            'create' => CreateCompra::route('/create'),
            'edit'   => EditCompra::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'numero_factura', 'proveedor.nombre'];
    }
}
