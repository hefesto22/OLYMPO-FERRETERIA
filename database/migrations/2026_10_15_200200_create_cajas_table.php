<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cajas registradoras. Cada caja es un punto de emisión ante el SAR:
 * tiene su propio rango de facturas (CAI) y su propio turno abierto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->string('punto_emision', 3)->unique()
                ->comment('Segundo bloque del número de factura: 001, 002…');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
