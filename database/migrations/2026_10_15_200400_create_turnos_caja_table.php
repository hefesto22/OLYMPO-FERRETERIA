<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turnos de caja: apertura con fondo, ventas, retiros/ingresos y cierre
 * con arqueo (efectivo esperado vs contado). Al cerrar se guarda una
 * foto de los totales para que el cierre no cambie después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos_caja', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->foreignId('user_id')->comment('Cajero')->constrained('users')->restrictOnDelete();
            $table->string('estado', 10)->default('abierto');
            $table->decimal('fondo_inicial', 14, 2)->default(0);
            $table->timestamp('abierto_at');
            $table->timestamp('cerrado_at')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('cantidad_ventas')->default(0);
            $table->decimal('total_ventas', 14, 2)->default(0);
            $table->decimal('total_efectivo', 14, 2)->default(0);
            $table->decimal('total_tarjeta', 14, 2)->default(0);
            $table->decimal('total_transferencia', 14, 2)->default(0);
            $table->decimal('total_ingresos', 14, 2)->default(0);
            $table->decimal('total_egresos', 14, 2)->default(0);
            $table->decimal('efectivo_esperado', 14, 2)->default(0);
            $table->decimal('efectivo_contado', 14, 2)->nullable();
            $table->decimal('diferencia', 14, 2)->nullable()->comment('contado − esperado: negativo = faltante');
            $table->text('notas_cierre')->nullable();
            $table->timestamps();

            $table->index(['caja_id', 'abierto_at']);
            $table->index(['user_id', 'abierto_at']);
        });

        // Una caja no puede tener dos turnos abiertos, ni un cajero dos cajas a la vez.
        DB::statement("CREATE UNIQUE INDEX turnos_caja_un_abierto_por_caja ON turnos_caja (caja_id) WHERE estado = 'abierto'");
        DB::statement("CREATE UNIQUE INDEX turnos_caja_un_abierto_por_cajero ON turnos_caja (user_id) WHERE estado = 'abierto'");

        Schema::create('movimientos_caja', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('turno_caja_id')->constrained('turnos_caja')->cascadeOnDelete();
            $table->string('tipo', 10)->comment('ingreso | egreso');
            $table->decimal('monto', 14, 2);
            $table->string('motivo', 255);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('turno_caja_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
        Schema::dropIfExists('turnos_caja');
    }
};
