<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nombre y dirección de los clientes que ya existen, en mayúsculas, como
 * van en la factura. Los nuevos ya se guardan así (Cliente::booted).
 * Se hace en PHP (mb_strtoupper) para que las tildes y la ñ no dependan
 * del idioma configurado en PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('clientes')
            ->select(['id', 'nombre', 'direccion'])
            ->lazyById()
            ->each(function (stdClass $cliente): void {
                $direccion = trim((string) $cliente->direccion);

                DB::table('clientes')->where('id', $cliente->id)->update([
                    'nombre'    => mb_strtoupper(trim((string) $cliente->nombre)),
                    'direccion' => $direccion !== '' ? mb_strtoupper($direccion) : null,
                ]);
            });
    }

    public function down(): void
    {
        // Sin vuelta atrás: no se sabe cómo estaba escrito cada nombre.
    }
};
