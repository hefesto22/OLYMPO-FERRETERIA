<?php

declare(strict_types=1);

namespace App\Filament\Resources\UnidadesMedida;

use App\Filament\Resources\UnidadesMedida\Pages\ManageUnidadesMedida;
use App\Filament\Resources\UnidadesMedida\Schemas\UnidadMedidaForm;
use App\Filament\Resources\UnidadesMedida\Tables\UnidadesMedidaTable;
use App\Models\UnidadMedida;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UnidadMedidaResource extends Resource
{
    protected static ?string $model = UnidadMedida::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $modelLabel = 'Unidad de medida';

    protected static ?string $pluralModelLabel = 'Unidades de medida';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function form(Schema $schema): Schema
    {
        return UnidadMedidaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnidadesMedidaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUnidadesMedida::route('/'),
        ];
    }
}
