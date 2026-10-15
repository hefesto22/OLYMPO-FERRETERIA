<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contadores con bloqueo de fila para numeraciones que no deben repetirse
 * (recibos). Las facturas usan su rango CAI, no esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secuencias', function (Blueprint $table): void {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->unsignedBigInteger('siguiente')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secuencias');
    }
};
