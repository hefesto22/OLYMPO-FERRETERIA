<?php

declare(strict_types=1);

namespace App\Filament\Resources\Productos\Pages;

use App\Enums\TipoMovimientoInventario;
use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Producto;
use App\Services\Inventario\InventarioService;
use App\Services\Precios\PrecioService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProducto extends CreateRecord
{
    protected static string $resource = ProductoResource::class;

    /**
     * La existencia inicial no es columna del producto: entra al kardex
     * como "Inventario inicial" con su costo, igual que cualquier entrada.
     * Con margen, ese primer costo es el que fija el precio.
     */
    protected function afterCreate(): void
    {
        /** @var Producto $producto */
        $producto = $this->getRecord();

        $cantidad = (float) ($this->data['stock_inicial'] ?? 0);

        if ($cantidad > 0 && $producto->controla_stock) {
            app(InventarioService::class)->registrarEntrada(
                $producto,
                $cantidad,
                (float) ($this->data['costo_inicial'] ?? 0),
                TipoMovimientoInventario::InventarioInicial,
                null,
                'Existencia inicial al crear el producto',
            );

            app(PrecioService::class)->revisar([$producto->id], 'Existencia inicial');
        }

        $producto->refresh();

        if ($producto->usaMargen() && (float) $producto->precio_venta <= 0) {
            Notification::make()
                ->warning()
                ->title('El producto aún no tiene precio')
                ->body('Su precio se calcula con el margen en cuanto tenga costo: con la primera compra o un ajuste de existencia inicial.')
                ->persistent()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return ProductoResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
