<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes de inventario: inventario inicial, conteos físicos, mermas,
 * robos, uso interno. Cantidades siempre en unidad base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_inventario', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 20)->nullable()->unique()->comment('Numeración interna AJ-000001');
            $table->date('fecha');
            $table->string('tipo', 10)->comment('entrada | salida');
            $table->string('motivo', 30);
            $table->string('estado', 20)->default('borrador');
            $table->text('notas')->nullable();

            $table->timestamp('aplicado_at')->nullable();
            $table->foreignId('aplicado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'fecha']);
        });

        Schema::create('ajuste_inventario_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ajuste_inventario_id')->constrained('ajustes_inventario')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('cantidad', 14, 3)->comment('En unidad base');
            $table->decimal('costo_unitario', 14, 4)->default(0)
                ->comment('Solo entradas: costo por unidad base sin ISV; 0 = usar costo promedio');
            $table->timestamps();

            $table->index('ajuste_inventario_id');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajuste_inventario_detalles');
        Schema::dropIfExists('ajustes_inventario');
    }
};
