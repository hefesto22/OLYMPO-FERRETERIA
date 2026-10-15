<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Pages;

use App\Filament\Resources\AjustesInventario\AjusteInventarioResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAjusteInventario extends CreateRecord
{
    protected static string $resource = AjusteInventarioResource::class;

    /**
     * Se guarda como borrador y se abre la vista, donde se aplica.
     */
    protected function getRedirectUrl(): string
    {
        return AjusteInventarioResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Ajuste guardado como borrador. Revísalo y aplícalo para que mueva el inventario.';
    }
}
