<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntradasInventario\Pages;

use App\Filament\Resources\EntradasInventario\EntradaInventarioResource;
use App\Models\EntradaInventario;
use App\Services\Inventario\EntradaInventarioService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEntradaInventario extends EditRecord
{
    protected static string $resource = EntradaInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var EntradaInventario $entrada */
        $entrada = $this->getRecord();

        app(EntradaInventarioService::class)->recalcularTotales($entrada);
    }

    protected function getRedirectUrl(): string
    {
        return EntradaInventarioResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
