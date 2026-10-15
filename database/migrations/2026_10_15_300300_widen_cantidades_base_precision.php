<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Existencias y cantidades en unidad base a 6 decimales.
 *
 * Con presentaciones más chicas que la unidad base, un clavo suelto de
 * "80 por LB" pesa 0.0125 lb: con 3 decimales se descontaban 0.013 lb
 * por clavo (4 % de más). Con 6, el descuento es exacto.
 *
 * Las cantidades que teclea el usuario (en la presentación, en un
 * ajuste) siguen con 3 decimales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->decimal('stock', 18, 6)->default(0)
                ->comment('Existencia en unidad base. Solo la modifica InventarioService')
                ->change();
        });

        Schema::table('movimientos_inventario', function (Blueprint $table): void {
            $table->decimal('cantidad', 18, 6)
                ->comment('Siempre positiva; el tipo indica si entra o sale')
                ->change();
            $table->decimal('stock_anterior', 18, 6)->change();
            $table->decimal('stock_nuevo', 18, 6)->change();
        });

        Schema::table('compra_detalles', function (Blueprint $table): void {
            $table->decimal('cantidad_base', 18, 6)->comment('cantidad × factor')->change();
        });

        Schema::table('venta_detalles', function (Blueprint $table): void {
            $table->decimal('cantidad_base', 18, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table): void {
            $table->decimal('cantidad_base', 14, 3)->change();
        });

        Schema::table('compra_detalles', function (Blueprint $table): void {
            $table->decimal('cantidad_base', 14, 3)->comment('cantidad × factor')->change();
        });

        Schema::table('movimientos_inventario', function (Blueprint $table): void {
            $table->decimal('cantidad', 14, 3)
                ->comment('Siempre positiva; el tipo indica si entra o sale')
                ->change();
            $table->decimal('stock_anterior', 14, 3)->change();
            $table->decimal('stock_nuevo', 14, 3)->change();
        });

        Schema::table('productos', function (Blueprint $table): void {
            $table->decimal('stock', 14, 3)->default(0)
                ->comment('Existencia en unidad base. Solo la modifica InventarioService')
                ->change();
        });
    }
};
