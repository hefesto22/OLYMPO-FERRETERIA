<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proveedores;

use App\Filament\Resources\Proveedores\Pages\ManageProveedores;
use App\Filament\Resources\Proveedores\Schemas\ProveedorForm;
use App\Filament\Resources\Proveedores\Tables\ProveedoresTable;
use App\Models\Proveedor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProveedorResource extends Resource
{
    protected static ?string $model = Proveedor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $modelLabel = 'Proveedor';

    protected static ?string $pluralModelLabel = 'Proveedores';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Compras';
    }

    public static function form(Schema $schema): Schema
    {
        return ProveedorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProveedoresTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProveedores::route('/'),
        ];
    }
}
