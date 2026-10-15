<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del emisor (van impresos en facturas y recibos) y reglas de caja.
 * Singleton: una sola fila, editable desde "Datos de la empresa".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_empresa', function (Blueprint $table): void {
            $table->id();
            $table->string('razon_social', 150)->default('');
            $table->string('nombre_comercial', 150)->default('');
            $table->string('rtn', 14)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('establecimiento', 3)->default('000')
                ->comment('Código de establecimiento SAR (primer bloque del número de factura)');
            $table->string('mensaje_ticket', 255)->nullable()->comment('Pie del ticket: "Gracias por su compra"');
            $table->decimal('descuento_max_cajero', 5, 2)->default(5)->comment('% máximo de descuento por línea');
            $table->decimal('descuento_max_supervisor', 5, 2)->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_empresa');
    }
};
