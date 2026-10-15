<?php

declare(strict_types=1);

namespace App\Filament\Resources\Productos\Pages;

use App\Filament\Resources\Productos\ProductoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProducto extends EditRecord
{
    protected static string $resource = ProductoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ProductoResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
