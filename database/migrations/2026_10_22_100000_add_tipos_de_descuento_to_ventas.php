<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Descuentos en % o en lempiras, por línea y a toda la venta.
 *
 * El descuento global se reparte entre las líneas (para separar bien el
 * ISV por tasa): `venta_detalles.descuento` sigue siendo todo lo rebajado
 * en la línea y `descuento_global` es la parte que le tocó del global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table): void {
            $table->string('descuento_tipo', 12)->default('porcentaje')
                ->comment('Cómo se tecleó el descuento de la línea: porcentaje o monto');
            $table->decimal('descuento_valor', 14, 2)->default(0)
                ->comment('Lo tecleado: % o lempiras por toda la línea');
            $table->decimal('descuento_global', 14, 2)->default(0)
                ->comment('Parte del descuento a toda la venta; ya incluida en descuento');
        });

        // Las ventas de antes solo tenían descuento en %.
        DB::table('venta_detalles')->update(['descuento_valor' => DB::raw('descuento_pct')]);

        Schema::table('ventas', function (Blueprint $table): void {
            $table->string('descuento_global_tipo', 12)->nullable()
                ->comment('Descuento a toda la venta: porcentaje o monto; vacío = sin descuento global');
            $table->decimal('descuento_global_valor', 14, 2)->default(0)
                ->comment('Lo tecleado: % o lempiras');
            $table->decimal('descuento_global', 14, 2)->default(0)
                ->comment('Lo rebajado por el descuento global; ya incluido en descuento');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropColumn(['descuento_global_tipo', 'descuento_global_valor', 'descuento_global']);
        });

        Schema::table('venta_detalles', function (Blueprint $table): void {
            $table->dropColumn(['descuento_tipo', 'descuento_valor', 'descuento_global']);
        });
    }
};
