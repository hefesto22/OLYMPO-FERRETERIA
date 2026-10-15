<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kardex: cada entrada o salida de stock deja una fila inmutable con el
 * saldo anterior, el nuevo y el costo. Permite reconstruir la historia
 * de cualquier producto y auditar diferencias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->decimal('cantidad', 14, 3)->comment('Siempre positiva; el tipo indica si entra o sale');
            $table->decimal('stock_anterior', 14, 3);
            $table->decimal('stock_nuevo', 14, 3);
            $table->decimal('costo_unitario', 14, 4)->comment('Costo del movimiento por unidad base, sin ISV');
            $table->decimal('costo_promedio', 14, 4)->comment('Costo promedio del producto después del movimiento');
            $table->nullableMorphs('documento');
            $table->string('descripcion', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['producto_id', 'created_at']);
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
