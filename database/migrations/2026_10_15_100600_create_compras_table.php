<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compras a proveedores (entradas de mercadería).
 *
 * Una compra en borrador no toca el inventario. Al confirmarla,
 * CompraService suma cada línea al stock (en unidad base) y recalcula
 * el costo promedio. Los documentos no se borran: se anulan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 20)->nullable()->unique()->comment('Numeración interna C-000001');
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->string('numero_factura_proveedor', 40)->nullable();
            $table->date('fecha');
            $table->string('estado', 20)->default('borrador');

            $table->decimal('subtotal', 14, 2)->default(0)->comment('Sin ISV');
            $table->decimal('isv', 14, 2)->default(0)->comment('ISV crédito fiscal');
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notas')->nullable();

            $table->timestamp('confirmada_at')->nullable();
            $table->foreignId('confirmada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulada_at')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'fecha']);
            $table->index('proveedor_id');
        });

        Schema::create('compra_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('producto_presentacion_id')->nullable()
                ->comment('null = se compró en la unidad base')
                ->constrained('producto_presentaciones')->nullOnDelete();

            $table->decimal('cantidad', 14, 3)->comment('En la presentación elegida');
            $table->decimal('factor', 14, 4)->default(1)->comment('Copia del factor al momento de la compra');
            $table->decimal('cantidad_base', 14, 3)->comment('cantidad × factor');
            $table->decimal('costo_unitario', 14, 4)->comment('Costo por presentación, sin ISV');
            $table->decimal('costo_unitario_base', 14, 4)->comment('costo_unitario ÷ factor');

            $table->string('tipo_isv', 20);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('isv', 14, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();

            $table->index('compra_id');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
        Schema::dropIfExists('compras');
    }
};
