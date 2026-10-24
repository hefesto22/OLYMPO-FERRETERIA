<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conteo físico de verdad: se escribe lo contado y cada producto queda en
 * ese número (unos suben, otros bajan). Por eso cada línea guarda lo
 * contado, hacia dónde movió y cuánto había al aplicar.
 *
 * La cantidad pasa a 6 decimales: en un conteo es la diferencia contra
 * la existencia, que va a 6 decimales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ajuste_inventario_detalles', function (Blueprint $table): void {
            $table->decimal('cantidad', 18, 6)->comment('En unidad base; en un conteo, la diferencia contra lo que había')->change();
            $table->string('tipo', 10)->nullable()->comment('entrada | salida. Vacío: el del ajuste (en un conteo, sin cambio)');
            $table->decimal('contado', 18, 6)->nullable()->comment('Conteo físico: lo que se contó, en unidad base');
            $table->decimal('existencia_antes', 18, 6)->nullable()->comment('Lo que había en el sistema al aplicar');
        });
    }

    public function down(): void
    {
        Schema::table('ajuste_inventario_detalles', function (Blueprint $table): void {
            $table->dropColumn(['tipo', 'contado', 'existencia_antes']);
            $table->decimal('cantidad', 14, 3)->comment('En unidad base')->change();
        });
    }
};
