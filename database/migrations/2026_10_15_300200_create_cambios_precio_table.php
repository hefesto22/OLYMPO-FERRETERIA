<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de precios por costo y bajas que esperan al dueño.
 *
 * Si el costo sube, el precio sube solo y queda aquí como "automatico".
 * Si baja, queda "pendiente" hasta que alguien con permiso lo aprueba
 * (baja el precio) o lo rechaza (lo mantiene).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cambios_precio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('estado', 20)
                ->comment('pendiente, automatico, aplicado, rechazado, descartado');

            $table->decimal('costo_anterior', 14, 4)->comment('Costo con que estaban calculados los precios');
            $table->decimal('costo_nuevo', 14, 4)->comment('Costo promedio que provocó el cambio');
            $table->decimal('precio_anterior', 12, 2);
            $table->decimal('precio_nuevo', 12, 2);
            $table->decimal('mayoreo_anterior', 12, 2)->nullable();
            $table->decimal('mayoreo_nuevo', 12, 2)->nullable();
            $table->jsonb('detalle')->nullable()
                ->comment('Presentaciones con precio automático: etiqueta, antes, después');
            $table->string('origen', 150)->nullable()->comment('Qué movió el costo: "Compra C-000012"');

            $table->foreignId('revisado_por')->nullable()
                ->comment('Quién aprobó o rechazó la baja')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->timestamps();

            $table->index('producto_id');
            $table->index(['estado', 'created_at']);
        });

        // Un solo cambio por revisar por producto: una baja nueva actualiza el que ya hay.
        DB::statement("CREATE UNIQUE INDEX cambios_precio_un_pendiente_por_producto ON cambios_precio (producto_id) WHERE estado = 'pendiente'");
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_precio');
    }
};
