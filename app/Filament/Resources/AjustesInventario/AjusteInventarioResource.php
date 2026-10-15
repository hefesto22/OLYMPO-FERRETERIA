<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario;

use App\Filament\Resources\AjustesInventario\Pages\CreateAjusteInventario;
use App\Filament\Resources\AjustesInventario\Pages\EditAjusteInventario;
use App\Filament\Resources\AjustesInventario\Pages\ListAjustesInventario;
use App\Filament\Resources\AjustesInventario\Pages\ViewAjusteInventario;
use App\Filament\Resources\AjustesInventario\Schemas\AjusteInventarioForm;
use App\Filament\Resources\AjustesInventario\Schemas\AjusteInventarioInfolist;
use App\Filament\Resources\AjustesInventario\Tables\AjustesInventarioTable;
use App\Models\AjusteInventario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AjusteInventarioResource extends Resource
{
    protected static ?string $model = AjusteInventario::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $recordTitleAttribute = 'numero';

    protected static ?string $modelLabel = 'Ajuste de inventario';

    protected static ?string $pluralModelLabel = 'Ajustes de inventario';

    protected static ?string $slug = 'ajustes-inventario';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function form(Schema $schema): Schema
    {
        return AjusteInventarioForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AjusteInventarioInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AjustesInventarioTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof AjusteInventario && $record->esBorrador() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof AjusteInventario && $record->esBorrador() && parent::canDelete($record);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAjustesInventario::route('/'),
            'create' => CreateAjusteInventario::route('/create'),
            'view'   => ViewAjusteInventario::route('/{record}'),
            'edit'   => EditAjusteInventario::route('/{record}/edit'),
        ];
    }
}
