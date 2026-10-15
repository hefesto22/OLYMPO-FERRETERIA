<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Pages;

use App\Filament\Resources\Compras\CompraResource;
use App\Models\Compra;
use App\Services\Inventario\CompraService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Compra $compra */
        $compra = $this->getRecord();

        app(CompraService::class)->recalcularTotales($compra);
    }

    protected function getRedirectUrl(): string
    {
        return CompraResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
