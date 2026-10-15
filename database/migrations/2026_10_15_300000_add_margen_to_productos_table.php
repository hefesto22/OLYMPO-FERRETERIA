<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Precio por margen de ganancia: el precio sale del costo promedio más
 * un margen (en % o en lempiras), con ISV, redondeado al lempira.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->string('margen_tipo', 20)->default('manual')
                ->comment('manual: precio tecleado; porcentaje o monto: el precio sale del costo');
            $table->decimal('margen_publico', 12, 2)->nullable()
                ->comment('Margen sobre el costo: % o lempiras por unidad base');
            $table->decimal('margen_mayoreo', 12, 2)->nullable()
                ->comment('Margen del precio mayoreo; vacío = sin precio mayoreo');
            $table->decimal('costo_precio', 14, 4)->default(0)
                ->comment('Costo con que se calcularon los precios vigentes. Solo lo mueve PrecioService');
        });

        DB::statement('UPDATE productos SET costo_precio = costo_promedio');
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->dropColumn(['margen_tipo', 'margen_publico', 'margen_mayoreo', 'costo_precio']);
        });
    }
};
