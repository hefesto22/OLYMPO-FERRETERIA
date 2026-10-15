<?php

declare(strict_types=1);

namespace App\Filament\Resources\RangosCai;

use App\Filament\Resources\RangosCai\Pages\ManageRangosCai;
use App\Filament\Resources\RangosCai\Schemas\RangoCaiForm;
use App\Filament\Resources\RangosCai\Tables\RangosCaiTable;
use App\Models\RangoCai;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RangoCaiResource extends Resource
{
    protected static ?string $model = RangoCai::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $modelLabel = 'Rango CAI';

    protected static ?string $pluralModelLabel = 'Rangos CAI';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Facturación';
    }

    public static function form(Schema $schema): Schema
    {
        return RangoCaiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RangosCaiTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRangosCai::route('/'),
        ];
    }

    /**
     * Avisa en el menú cuántos rangos activos están por vencer, vencidos o agotados.
     */
    public static function getNavigationBadge(): ?string
    {
        $conAviso = RangoCai::query()
            ->where('activo', true)
            ->get()
            ->filter(fn (RangoCai $rango): bool => $rango->etiquetaEstado() !== 'Vigente')
            ->count();

        return $conAviso > 0 ? (string) $conAviso : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Rangos CAI por terminarse, vencidos o agotados';
    }
}
