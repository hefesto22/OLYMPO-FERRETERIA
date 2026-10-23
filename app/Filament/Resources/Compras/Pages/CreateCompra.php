<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Pages;

use App\Filament\Resources\Compras\CompraResource;
use App\Models\Compra;
use Filament\Resources\Pages\CreateRecord;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    protected function afterCreate(): void
    {
        /** @var Compra $compra */
        $compra = $this->getRecord();

        $compra->guardarRtnEnProveedor();
    }

    protected function getRedirectUrl(): string
    {
        return CompraResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Compra registrada';
    }
}
