<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntradasInventario\Pages;

use App\Filament\Resources\EntradasInventario\EntradaInventarioResource;
use App\Models\EntradaInventario;
use App\Services\Inventario\EntradaInventarioService;
use Filament\Resources\Pages\CreateRecord;

class CreateEntradaInventario extends CreateRecord
{
    protected static string $resource = EntradaInventarioResource::class;

    protected function afterCreate(): void
    {
        /** @var EntradaInventario $entrada */
        $entrada = $this->getRecord();

        app(EntradaInventarioService::class)->recalcularTotales($entrada);
    }

    /**
     * Se guarda como borrador y se abre la vista, donde se confirma.
     */
    protected function getRedirectUrl(): string
    {
        return EntradaInventarioResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Entrada guardada como borrador. Revísala y confírmala para que sume al inventario.';
    }
}
