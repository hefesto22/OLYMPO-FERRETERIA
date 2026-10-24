<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una entrada de inventario solo carga stock: no lleva proveedor ni
 * referencia (eso es de Compras). Guarda quién la registró y la fecha,
 * los dos solos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entradas_inventario', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proveedor_id');
            $table->dropColumn('referencia');
        });
    }

    public function down(): void
    {
        Schema::table('entradas_inventario', function (Blueprint $table): void {
            $table->foreignId('proveedor_id')->nullable()->after('numero')->constrained('proveedores')->restrictOnDelete();
            $table->string('referencia', 40)->nullable()->after('proveedor_id');
        });
    }
};
