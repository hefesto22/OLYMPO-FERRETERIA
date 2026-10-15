<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Presentaciones de un producto: otras unidades en que se compra o vende,
 * convertidas a la unidad base con un factor.
 *
 *   Tornillo (base: Unidad)  → Caja x100       factor 100
 *   Cable #12 (base: Metro)  → Rollo 100 m     factor 100
 *   Pintura (base: Galón)    → Cubeta 5 gal    factor 5
 *                            → Cuarto          factor 0.25
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_presentaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida')->restrictOnDelete();
            $table->string('nombre', 80)->nullable()->comment('Ej: Caja x100, Rollo 100 m');
            $table->decimal('factor', 14, 4)->comment('Cuántas unidades base contiene');
            $table->string('codigo_barras', 60)->nullable()->unique();
            $table->decimal('precio_venta', 12, 2)->nullable()
                ->comment('Precio de la presentación ISV incluido; vacío = precio base × factor');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('producto_id');
        });

        DB::statement('ALTER TABLE producto_presentaciones ADD CONSTRAINT producto_presentaciones_factor_positivo CHECK (factor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_presentaciones');
    }
};
