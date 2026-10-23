<?php

declare(strict_types=1);

use App\Enums\TipoMargen;
use App\Models\Producto;
use Illuminate\Database\Migrations\Migration;

/**
 * El margen en lempiras ahora se suma sobre lo que te sale el producto
 * con ISV (L 11.50 + L 3 = L 14.50 → L 15), ya no antes del ISV. Los
 * productos con ese margen recalculan sus precios con la fórmula nueva,
 * sobre el mismo costo con que se calcularon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Producto::withTrashed()
            ->where('margen_tipo', TipoMargen::Monto->value)
            ->where('costo_precio', '>', 0)
            ->each(function (Producto $producto): void {
                $producto->calcularPreciosConMargen();
                $producto->saveQuietly();
            });
    }

    public function down(): void
    {
        // La fórmula anterior ya no existe en el código: los precios se quedan.
    }
};
