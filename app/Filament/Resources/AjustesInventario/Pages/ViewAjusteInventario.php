<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Pages;

use App\Filament\Resources\AjustesInventario\Actions\AplicarAjusteAction;
use App\Filament\Resources\AjustesInventario\AjusteInventarioResource;
use App\Models\AjusteInventario;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAjusteInventario extends ViewRecord
{
    protected static string $resource = AjusteInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AplicarAjusteAction::make(),
            EditAction::make()
                ->visible(fn (AjusteInventario $record): bool => $record->esBorrador()),
        ];
    }
}
