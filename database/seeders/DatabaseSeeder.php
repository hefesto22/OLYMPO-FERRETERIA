<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
            BrandingSettingSeeder::class,
            CatalogoBaseSeeder::class,
            FerreteriaRolesSeeder::class,
            ConfiguracionVentasSeeder::class,
        ]);

        // Productos, inventario, clientes y un CAI ficticio, solo en desarrollo local.
        if (app()->environment('local')) {
            $this->call([
                DemoFerreteriaSeeder::class,
                DemoVentasSeeder::class,
            ]);
        }
    }
}
