<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Presentaciones más chicas que la unidad base: "100 clavos por libra".
 *
 * Se guarda lo que el usuario teclea (cantidad y forma) y el factor que
 * sale de ahí. El factor pasa a 8 decimales porque 1/106 = 0.00943396
 * con 4 decimales ya no regresa a 106.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_presentaciones', function (Blueprint $table): void {
            $table->string('factor_modo', 20)->default('contiene')
                ->comment('contiene: N unidades base c/u; por_base: N caben en 1 unidad base');
            $table->decimal('factor_cantidad', 14, 4)->nullable()
                ->comment('Lo tecleado: 50 (LB c/u) o 100 (por LB)');
            $table->decimal('factor', 18, 8)
                ->comment('Cuántas unidades base contiene (100 por LB = 0.01)')
                ->change();
        });

        DB::statement('UPDATE producto_presentaciones SET factor_cantidad = factor');

        Schema::table('compra_detalles', function (Blueprint $table): void {
            $table->decimal('factor', 18, 8)->default(1)
                ->comment('Copia del factor al momento de la compra')
                ->change();
        });

        Schema::table('venta_detalles', function (Blueprint $table): void {
            $table->decimal('factor', 18, 8)->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table): void {
            $table->decimal('factor', 14, 4)->default(1)->change();
        });

        Schema::table('compra_detalles', function (Blueprint $table): void {
            $table->decimal('factor', 14, 4)->default(1)
                ->comment('Copia del factor al momento de la compra')
                ->change();
        });

        Schema::table('producto_presentaciones', function (Blueprint $table): void {
            $table->decimal('factor', 14, 4)->comment('Cuántas unidades base contiene')->change();
            $table->dropColumn(['factor_modo', 'factor_cantidad']);
        });
    }
};
