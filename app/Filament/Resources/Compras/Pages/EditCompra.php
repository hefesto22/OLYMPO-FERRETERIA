<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Pages;

use App\Filament\Resources\Compras\CompraResource;
use App\Models\Compra;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Compra $compra */
        $compra = $this->getRecord();

        $compra->guardarRtnEnProveedor();
    }

    protected function getRedirectUrl(): string
    {
        return CompraResource::getUrl('index');
    }
}
