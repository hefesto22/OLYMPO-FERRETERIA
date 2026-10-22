<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Margen propio por presentación: la caja cerrada suele llevar menos
 * margen que la libra o el clavo suelto. Vacío = el margen del producto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_presentaciones', function (Blueprint $table): void {
            $table->decimal('margen', 12, 2)->nullable()
                ->comment('Margen propio (% o lempiras por presentación); vacío = el del producto');
        });
    }

    public function down(): void
    {
        Schema::table('producto_presentaciones', function (Blueprint $table): void {
            $table->dropColumn('margen');
        });
    }
};
