<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Pages;

use App\Filament\Resources\AjustesInventario\AjusteInventarioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAjustesInventario extends ListRecords
{
    protected static string $resource = AjusteInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo ajuste'),
        ];
    }
}
