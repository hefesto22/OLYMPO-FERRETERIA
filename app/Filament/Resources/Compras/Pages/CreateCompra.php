<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Pages;

use App\Filament\Resources\Compras\CompraResource;
use App\Models\Compra;
use App\Services\Inventario\CompraService;
use Filament\Resources\Pages\CreateRecord;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    protected function afterCreate(): void
    {
        /** @var Compra $compra */
        $compra = $this->getRecord();

        app(CompraService::class)->recalcularTotales($compra);
    }

    /**
     * Se guarda como borrador y se abre la vista, donde se confirma.
     */
    protected function getRedirectUrl(): string
    {
        return CompraResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Compra guardada como borrador. Revísala y confírmala para que entre al inventario.';
    }
}
