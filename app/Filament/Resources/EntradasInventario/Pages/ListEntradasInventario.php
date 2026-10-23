<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntradasInventario\Pages;

use App\Filament\Resources\EntradasInventario\EntradaInventarioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEntradasInventario extends ListRecords
{
    protected static string $resource = EntradaInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva entrada'),
        ];
    }
}
