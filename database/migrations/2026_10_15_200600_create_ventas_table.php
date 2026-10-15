<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas. Los precios se manejan con ISV incluido; por cada línea se
 * separa la base imponible y el ISV, y los totales siguen el desglose
 * que pide el SAR (exento, gravado 15 %, gravado 18 %, ISV 15 %, ISV 18 %).
 *
 * Los datos del emisor y del CAI se copian a la venta al emitirla: si
 * mañana cambian, la factura ya emitida se reimprime igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 30)->unique()->comment('REC-000123 o 000-001-01-00000123');
            $table->string('tipo_documento', 10)->comment('recibo | factura');
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->foreignId('turno_caja_id')->constrained('turnos_caja')->restrictOnDelete();
            $table->foreignId('user_id')->comment('Cajero')->constrained('users')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre', 150)->default('Consumidor final');
            $table->string('cliente_rtn', 14)->nullable();
            $table->string('lista_precio', 10)->default('publico');
            $table->string('condicion', 10)->default('contado')->comment('contado | credito');
            $table->string('estado', 15)->default('completada');

            // Copia del CAI con que se emitió (solo facturas).
            $table->foreignId('rango_cai_id')->nullable()->constrained('rangos_cai')->nullOnDelete();
            $table->string('cai', 40)->nullable();
            $table->string('cai_rango_desde', 30)->nullable();
            $table->string('cai_rango_hasta', 30)->nullable();
            $table->date('cai_fecha_limite')->nullable();
            $table->jsonb('datos_emisor')->nullable();

            $table->decimal('descuento', 14, 2)->default(0)->comment('Descuentos otorgados, ISV incluido');
            $table->decimal('importe_exento', 14, 2)->default(0);
            $table->decimal('importe_gravado_15', 14, 2)->default(0);
            $table->decimal('importe_gravado_18', 14, 2)->default(0);
            $table->decimal('isv_15', 14, 2)->default(0);
            $table->decimal('isv_18', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0)->comment('Suma de bases imponibles (sin ISV)');
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('efectivo_recibido', 14, 2)->default(0);
            $table->decimal('cambio', 14, 2)->default(0);

            $table->text('notas')->nullable();
            $table->timestamp('anulada_at')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();

            $table->index('turno_caja_id');
            $table->index(['estado', 'created_at']);
            $table->index('cliente_id');
            $table->index(['caja_id', 'created_at']);
        });

        Schema::create('venta_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('producto_presentacion_id')->nullable()
                ->constrained('producto_presentaciones')->nullOnDelete();
            $table->string('descripcion', 255)->comment('Nombre del producto al momento de la venta');
            $table->string('unidad', 80)->comment('Unidad o presentación vendida');

            $table->decimal('cantidad', 14, 3);
            $table->decimal('factor', 14, 4)->default(1);
            $table->decimal('cantidad_base', 14, 3);
            $table->decimal('precio_unitario', 12, 2)->comment('Por unidad vendida, ISV incluido');
            $table->decimal('descuento_pct', 5, 2)->default(0);
            $table->decimal('descuento', 14, 2)->default(0)->comment('ISV incluido');
            $table->string('tipo_isv', 20);
            $table->decimal('base_imponible', 14, 2);
            $table->decimal('isv', 14, 2);
            $table->decimal('total', 14, 2);
            $table->decimal('costo_unitario_base', 14, 4)->default(0)->comment('Costo promedio al vender, para márgenes');
            $table->timestamps();

            $table->index('venta_id');
            $table->index('producto_id');
        });

        Schema::create('venta_pagos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->string('metodo', 20);
            $table->decimal('monto', 14, 2)->comment('Monto aplicado a la venta (el efectivo sin el cambio)');
            $table->string('referencia', 60)->nullable()->comment('Voucher de tarjeta o n.º de transferencia');
            $table->timestamps();

            $table->index('venta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_pagos');
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
    }
};
