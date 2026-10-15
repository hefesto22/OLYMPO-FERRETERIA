<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\ConfiguracionEmpresa;
use Illuminate\Database\Seeder;

/**
 * Deja lista la configuración mínima para vender: los datos de la
 * empresa (se completan desde "Datos de la empresa") y la Caja 1.
 * Idempotente: no toca lo que el cliente ya configuró.
 */
class ConfiguracionVentasSeeder extends Seeder
{
    public function run(): void
    {
        // Primero se descarta lo que haya en cache para que la fila se cree
        // de verdad en la base (después de un migrate:fresh, Redis sigue lleno).
        ConfiguracionEmpresa::olvidarCache();
        ConfiguracionEmpresa::actual();

        Caja::query()->firstOrCreate(
            ['punto_emision' => '001'],
            ['nombre' => 'Caja 1', 'activo' => true],
        );

        $this->command?->info('✓ Ventas: datos de la empresa y Caja 1 listos.');
    }
}
