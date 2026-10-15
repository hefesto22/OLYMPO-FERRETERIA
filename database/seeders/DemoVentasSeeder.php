<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ListaPrecio;
use App\Enums\RolFerreteria;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\ConfiguracionEmpresa;
use App\Models\RangoCai;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo de ventas para desarrollo local: datos de empresa de ejemplo,
 * un CAI FICTICIO en la Caja 1, una segunda caja, tres clientes y dos
 * usuarios de prueba (cajero y supervisor, contraseña 12345678).
 * El CAI no es real: en producción se registra el que entrega el SAR.
 */
class DemoVentasSeeder extends Seeder
{
    public function run(): void
    {
        $configuracion = ConfiguracionEmpresa::actual();

        if ($configuracion->rtn === null) {
            $configuracion->update([
                'razon_social'     => 'Ferretería Demo S. de R.L.',
                'nombre_comercial' => 'Ferretería Demo',
                'rtn'              => '05019999000001',
                'direccion'        => 'Barrio El Centro, Santa Rosa de Copán',
                'telefono'         => '2662-0000',
                'correo'           => 'ventas@ferreteria-demo.hn',
            ]);
        }

        $caja = Caja::query()->firstOrCreate(['punto_emision' => '001'], ['nombre' => 'Caja 1']);

        if (! $caja->rangosCai()->exists()) {
            RangoCai::query()->create([
                'caja_id'              => $caja->id,
                'cai'                  => '000000-000000-000000-000000-000000-00',
                'establecimiento'      => '000',
                'punto_emision'        => $caja->punto_emision,
                'tipo_documento'       => '01',
                'rango_inicial'        => 1,
                'rango_final'          => 5000,
                'siguiente'            => 1,
                'fecha_limite_emision' => now()->addYear()->toDateString(),
                'activo'               => true,
                'notas'                => 'CAI ficticio de demostración',
            ]);
        }

        $clientes = [
            ['nombre' => 'Constructora El Progreso', 'rtn' => '05011999000123', 'tipo_precio' => ListaPrecio::Mayoreo, 'telefono' => '99887766'],
            ['nombre' => 'Juan Pérez (maestro de obra)', 'rtn' => null, 'tipo_precio' => ListaPrecio::Mayoreo, 'telefono' => '98765432'],
            ['nombre' => 'María López', 'rtn' => null, 'tipo_precio' => ListaPrecio::Publico, 'telefono' => '33445566'],
        ];

        foreach ($clientes as $cliente) {
            Cliente::query()->firstOrCreate(['nombre' => $cliente['nombre']], $cliente);
        }

        Caja::query()->firstOrCreate(['punto_emision' => '002'], ['nombre' => 'Caja 2']);

        $usuarios = [
            ['email' => 'cajero@gmail.com', 'name' => 'Cajero de prueba', 'rol' => RolFerreteria::Cajero],
            ['email' => 'supervisor@gmail.com', 'name' => 'Supervisor de prueba', 'rol' => RolFerreteria::Supervisor],
        ];

        foreach ($usuarios as $datos) {
            $usuario = User::query()->firstOrCreate(
                ['email' => $datos['email']],
                ['name' => $datos['name'], 'password' => '12345678', 'is_active' => true, 'email_verified_at' => now()],
            );

            $usuario->syncRoles([$datos['rol']->value]);
        }

        $this->command?->info('✓ Demo de ventas: CAI ficticio en la Caja 1, Caja 2, 3 clientes y usuarios cajero@gmail.com / supervisor@gmail.com (12345678).');
    }
}
