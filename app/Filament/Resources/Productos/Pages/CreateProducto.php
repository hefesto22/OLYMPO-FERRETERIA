<?php

declare(strict_types=1);

namespace App\Filament\Resources\Productos\Pages;

use App\Enums\TipoMovimientoInventario;
use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Producto;
use App\Services\Inventario\InventarioService;
use Filament\Resources\Pages\CreateRecord;

class CreateProducto extends CreateRecord
{
    protected static string $resource = ProductoResource::class;

    /**
     * La existencia inicial no es columna del producto: entra al kardex
     * como "Inventario inicial" con su costo, igual que cualquier entrada.
     */
    protected function afterCreate(): void
    {
        /** @var Producto $producto */
        $producto = $this->getRecord();

        $cantidad = (float) ($this->data['stock_inicial'] ?? 0);

        if ($cantidad <= 0 || ! $producto->controla_stock) {
            return;
        }

        app(InventarioService::class)->registrarEntrada(
            $producto,
            $cantidad,
            (float) ($this->data['costo_inicial'] ?? 0),
            TipoMovimientoInventario::InventarioInicial,
            null,
            'Existencia inicial al crear el producto',
        );
    }

    protected function getRedirectUrl(): string
    {
        return ProductoResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
