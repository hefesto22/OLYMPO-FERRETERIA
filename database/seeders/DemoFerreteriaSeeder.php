<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
use App\Enums\TipoIsv;
use App\Enums\TipoMargen;
use App\Models\AjusteInventario;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Services\Inventario\AjusteInventarioService;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración para desarrollo local: lo que se vende en una
 * ferretería (CatalogoDemoFerreteria), con presentaciones y su inventario
 * inicial aplicado por el mismo servicio que usa el sistema (deja kardex
 * y calcula los precios por margen).
 *
 * DatabaseSeeder solo lo llama en APP_ENV=local. Se puede volver a correr:
 * solo agrega los productos del catálogo que todavía no existen (por
 * nombre) y no toca los que ya están.
 */
class DemoFerreteriaSeeder extends Seeder
{
    /** @var list<array{nombre: string, contacto: string, telefono: string, dias_credito: int}> */
    private const PROVEEDORES = [
        ['nombre' => 'Distribuidora Ferretera del Occidente', 'contacto' => 'Carlos Mejía', 'telefono' => '26620000', 'dias_credito' => 30],
        ['nombre' => 'Materiales y Aceros de Copán', 'contacto' => 'Ana Paz', 'telefono' => '26621111', 'dias_credito' => 15],
        ['nombre' => 'Electro Suministros Hondureños', 'contacto' => 'José Rivera', 'telefono' => '99001122', 'dias_credito' => 0],
    ];

    public function run(): void
    {
        // Unidades (pie tablar, ciento, viaje…) y categorías que usa el catálogo.
        $this->call(CatalogoBaseSeeder::class);

        foreach (self::PROVEEDORES as $proveedor) {
            Proveedor::query()->firstOrCreate(['nombre' => $proveedor['nombre']], $proveedor);
        }

        $catalogo = CatalogoDemoFerreteria::productos();
        $existentes = Producto::withTrashed()->pluck('nombre')->flip()->all();

        if (array_diff_key($catalogo, $existentes) === []) {
            $this->command?->warn('Ya están todos los productos de la demo de ferretería.');

            return;
        }

        $unidades = UnidadMedida::query()->pluck('id', 'abreviatura');
        $categorias = Categoria::query()->whereNull('parent_id')->pluck('id', 'nombre');
        $ajuste = null;
        $nuevos = 0;

        foreach ($catalogo as $nombre => $datos) {
            // Los que ya están (aunque se hayan borrado) no se tocan.
            if (isset($existentes[$nombre])) {
                continue;
            }

            $nuevos++;
            $marcaId = $datos['marca'] !== null
                ? Marca::query()->firstOrCreate(['nombre' => $datos['marca']])->id
                : null;

            $esServicio = $datos['servicio'] ?? false;
            $margen = $datos['margen'] ?? null;

            // Con margen, el precio se calcula cuando el ajuste fija el costo.
            $producto = Producto::query()->create([
                'nombre'           => $nombre,
                'categoria_id'     => $categorias[$datos['cat']],
                'marca_id'         => $marcaId,
                'unidad_medida_id' => $unidades[$datos['und']],
                'tipo_isv'         => TipoIsv::from($datos['isv'] ?? TipoIsv::Gravado15->value),
                'margen_tipo'      => $margen !== null ? TipoMargen::Porcentaje : TipoMargen::Manual,
                'margen_publico'   => $margen[0] ?? null,
                'margen_mayoreo'   => $margen[1] ?? null,
                'precio_venta'     => $datos['precio'] ?? 0,
                'precio_mayoreo'   => $datos['mayoreo'] ?? null,
                'stock_minimo'     => $datos['min'],
                'controla_stock'   => ! $esServicio,
            ]);

            foreach ($datos['pres'] ?? [] as $presentacion) {
                $producto->presentaciones()->create([
                    'unidad_medida_id' => $unidades[$presentacion[0]],
                    'nombre'           => $presentacion[2],
                    'factor_cantidad'  => $presentacion[1],
                    'factor_modo'      => $presentacion[4] ?? 'contiene',
                    'precio_venta'     => $presentacion[3] ?? null,
                    'margen'           => $presentacion[5] ?? null,
                ]);
            }

            if (! $esServicio && $datos['stock'] > 0) {
                if ($ajuste === null) {
                    $ajuste = AjusteInventario::query()->create([
                        'fecha'  => now()->toDateString(),
                        'tipo'   => TipoAjuste::Entrada,
                        'motivo' => MotivoAjuste::InventarioInicial,
                        'notas'  => 'Inventario inicial de demostración',
                    ]);
                }

                $ajuste->detalles()->create([
                    'producto_id'    => $producto->id,
                    'cantidad'       => $datos['stock'],
                    'costo_unitario' => $datos['costo'],
                ]);
            }
        }

        if ($ajuste !== null) {
            app(AjusteInventarioService::class)->aplicar($ajuste);
        }

        $this->command?->info('✓ Demo de ferretería: '.$nuevos.' productos nuevos con su inventario inicial.');
    }
}
