<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntradasInventario\Pages;

use App\Filament\Resources\EntradasInventario\Actions\AnularEntradaAction;
use App\Filament\Resources\EntradasInventario\Actions\ConfirmarEntradaAction;
use App\Filament\Resources\EntradasInventario\EntradaInventarioResource;
use App\Models\EntradaInventario;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEntradaInventario extends ViewRecord
{
    protected static string $resource = EntradaInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConfirmarEntradaAction::make(),
            EditAction::make()
                ->visible(fn (EntradaInventario $record): bool => $record->esBorrador()),
            AnularEntradaAction::make(),
        ];
    }
}
