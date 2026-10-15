<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rangos de facturación autorizados por el SAR (CAI).
 *
 * Número de factura: {establecimiento}-{punto de emisión}-{tipo}-{correlativo}
 *                    000-001-01-00000123
 *
 * Solo puede haber UN rango activo por caja. `siguiente` es el próximo
 * correlativo a usar; se toma con bloqueo de fila para que dos ventas
 * simultáneas nunca repitan ni salten número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rangos_cai', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->string('cai', 40);
            $table->string('establecimiento', 3);
            $table->string('punto_emision', 3);
            $table->string('tipo_documento', 2)->default('01')->comment('01 = factura');
            $table->unsignedBigInteger('rango_inicial');
            $table->unsignedBigInteger('rango_final');
            $table->unsignedBigInteger('siguiente');
            $table->date('fecha_limite_emision');
            $table->boolean('activo')->default(true);
            $table->string('notas', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('caja_id');
        });

        DB::statement('ALTER TABLE rangos_cai ADD CONSTRAINT rangos_cai_rango_valido CHECK (rango_final >= rango_inicial AND siguiente >= rango_inicial AND siguiente <= rango_final + 1)');
        DB::statement('CREATE UNIQUE INDEX rangos_cai_un_activo_por_caja ON rangos_cai (caja_id) WHERE activo');
    }

    public function down(): void
    {
        Schema::dropIfExists('rangos_cai');
    }
};
