<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
use App\Enums\TipoIsv;
use App\Models\AjusteInventario;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Services\Inventario\AjusteInventarioService;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración para desarrollo local: productos típicos de
 * ferretería con presentaciones y su inventario inicial aplicado por
 * el mismo servicio que usa el sistema (deja kardex).
 *
 * DatabaseSeeder solo lo llama en APP_ENV=local. No corre si ya hay
 * productos, para no duplicar nada.
 */
class DemoFerreteriaSeeder extends Seeder
{
    /**
     * cat, marca, und, precio (ISV incl.), mayoreo, costo (sin ISV, por unidad base),
     * stock inicial, mínimo, presentaciones [und, factor, nombre, precio].
     *
     * @var array<string, array{cat: string, marca: string|null, und: string, precio: float|int, mayoreo: float|int|null, costo: float|int, stock: float|int, min: float|int, pres?: list<array{0: string, 1: float|int, 2: string, 3: float|int}>, servicio?: bool}>
     */
    private const PRODUCTOS = [
        'Cemento gris uso general 42.5 kg' => ['cat' => 'Materiales de construcción', 'marca' => null, 'und' => 'BLS', 'precio' => 245.00, 'mayoreo' => 235.00, 'costo' => 185.00, 'stock' => 120, 'min' => 30],
        'Bloque de concreto 15 cm (6")'    => ['cat' => 'Materiales de construcción', 'marca' => null, 'und' => 'UND', 'precio' => 19.00, 'mayoreo' => 17.50, 'costo' => 13.50, 'stock' => 800, 'min' => 200],
        'Varilla corrugada 3/8" x 6 m'     => ['cat' => 'Hierro y acero', 'marca' => null, 'und' => 'VAR', 'precio' => 165.00, 'mayoreo' => 158.00, 'costo' => 122.00, 'stock' => 150, 'min' => 40],
        'Varilla corrugada 1/2" x 6 m'     => ['cat' => 'Hierro y acero', 'marca' => null, 'und' => 'VAR', 'precio' => 290.00, 'mayoreo' => 279.00, 'costo' => 215.00, 'stock' => 80, 'min' => 20],
        'Alambre de amarre #16'            => ['cat' => 'Hierro y acero', 'marca' => null, 'und' => 'LB', 'precio' => 32.00, 'mayoreo' => 29.00, 'costo' => 22.50, 'stock' => 220, 'min' => 50, 'pres' => [['RLL', 50, 'Rollo 50 lb', 1450.00]]],
        'Clavo con cabeza 2 1/2"'          => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'LB', 'precio' => 28.00, 'mayoreo' => 25.50, 'costo' => 18.80, 'stock' => 150, 'min' => 40, 'pres' => [['CJ', 50, 'Caja 50 lb', 1250.00]]],
        'Clavo con cabeza 3"'              => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'LB', 'precio' => 27.50, 'mayoreo' => 25.00, 'costo' => 18.50, 'stock' => 140, 'min' => 40, 'pres' => [['CJ', 50, 'Caja 50 lb', 1225.00]]],
        'Tornillo para tablayeso 6 x 1"'   => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'UND', 'precio' => 0.75, 'mayoreo' => null, 'costo' => 0.42, 'stock' => 5000, 'min' => 1000, 'pres' => [['BLS', 100, 'Bolsa x100', 65.00], ['CJ', 1000, 'Caja x1000', 550.00]]],
        'Tornillo goloso 8 x 1 1/2"'       => ['cat' => 'Tornillería y fijaciones', 'marca' => null, 'und' => 'UND', 'precio' => 1.25, 'mayoreo' => null, 'costo' => 0.70, 'stock' => 3000, 'min' => 500, 'pres' => [['CJ', 100, 'Caja x100', 110.00]]],
        'Cable THHN #12'                   => ['cat' => 'Electricidad', 'marca' => null, 'und' => 'M', 'precio' => 18.50, 'mayoreo' => 17.00, 'costo' => 12.80, 'stock' => 600, 'min' => 100, 'pres' => [['RLL', 100, 'Rollo 100 m', 1650.00]]],
        'Cable THHN #10'                   => ['cat' => 'Electricidad', 'marca' => null, 'und' => 'M', 'precio' => 27.00, 'mayoreo' => 25.00, 'costo' => 18.90, 'stock' => 400, 'min' => 100, 'pres' => [['RLL', 100, 'Rollo 100 m', 2450.00]]],
        'Tomacorriente doble polarizado'   => ['cat' => 'Electricidad', 'marca' => 'Bticino', 'und' => 'UND', 'precio' => 48.00, 'mayoreo' => null, 'costo' => 29.00, 'stock' => 60, 'min' => 15],
        'Apagador sencillo'                => ['cat' => 'Electricidad', 'marca' => 'Bticino', 'und' => 'UND', 'precio' => 42.00, 'mayoreo' => null, 'costo' => 25.00, 'stock' => 60, 'min' => 15],
        'Foco LED 9 W luz blanca'          => ['cat' => 'Iluminación', 'marca' => 'Sylvania', 'und' => 'UND', 'precio' => 55.00, 'mayoreo' => 50.00, 'costo' => 32.00, 'stock' => 100, 'min' => 24],
        'Tubo PVC 1/2" SDR 13.5 x 6 m'     => ['cat' => 'Plomería y PVC', 'marca' => 'Amanco', 'und' => 'UND', 'precio' => 95.00, 'mayoreo' => 89.00, 'costo' => 64.00, 'stock' => 120, 'min' => 30],
        'Tubo PVC 4" drenaje x 6 m'        => ['cat' => 'Plomería y PVC', 'marca' => 'Amanco', 'und' => 'UND', 'precio' => 520.00, 'mayoreo' => 495.00, 'costo' => 360.00, 'stock' => 25, 'min' => 6],
        'Codo PVC 1/2" x 90°'              => ['cat' => 'Plomería y PVC', 'marca' => 'Amanco', 'und' => 'UND', 'precio' => 6.00, 'mayoreo' => null, 'costo' => 3.40, 'stock' => 400, 'min' => 100],
        'Cinta teflón 1/2"'                => ['cat' => 'Plomería y PVC', 'marca' => null, 'und' => 'UND', 'precio' => 12.00, 'mayoreo' => null, 'costo' => 6.50, 'stock' => 200, 'min' => 50],
        'Pegamento para PVC 1/32 gal'      => ['cat' => 'Adhesivos y selladores', 'marca' => 'Amanco', 'und' => 'UND', 'precio' => 85.00, 'mayoreo' => null, 'costo' => 55.00, 'stock' => 40, 'min' => 10],
        'Pintura látex blanco mate'        => ['cat' => 'Pinturas y acabados', 'marca' => 'Sherwin-Williams', 'und' => 'GAL', 'precio' => 420.00, 'mayoreo' => 399.00, 'costo' => 280.00, 'stock' => 60, 'min' => 12, 'pres' => [['CUB', 5, 'Cubeta 5 galones', 1950.00], ['QT', 0.25, 'Cuarto de galón', 125.00]]],
        'Thinner'                          => ['cat' => 'Pinturas y acabados', 'marca' => null, 'und' => 'GAL', 'precio' => 150.00, 'mayoreo' => 140.00, 'costo' => 98.00, 'stock' => 40, 'min' => 10],
        'Brocha 3"'                        => ['cat' => 'Pinturas y acabados', 'marca' => 'Truper', 'und' => 'UND', 'precio' => 45.00, 'mayoreo' => null, 'costo' => 26.00, 'stock' => 50, 'min' => 12],
        'Martillo de uña 16 oz'            => ['cat' => 'Herramientas manuales', 'marca' => 'Truper', 'und' => 'UND', 'precio' => 210.00, 'mayoreo' => null, 'costo' => 135.00, 'stock' => 15, 'min' => 4],
        'Cinta métrica 5 m'                => ['cat' => 'Herramientas manuales', 'marca' => 'Stanley', 'und' => 'UND', 'precio' => 165.00, 'mayoreo' => null, 'costo' => 102.00, 'stock' => 20, 'min' => 5],
        'Taladro percutor 1/2" 600 W'      => ['cat' => 'Herramientas eléctricas', 'marca' => 'Truper', 'und' => 'UND', 'precio' => 1450.00, 'mayoreo' => null, 'costo' => 980.00, 'stock' => 5, 'min' => 2],
        'Disco de corte metal 4 1/2"'      => ['cat' => 'Abrasivos y discos', 'marca' => 'Truper', 'und' => 'UND', 'precio' => 38.00, 'mayoreo' => 34.00, 'costo' => 22.00, 'stock' => 150, 'min' => 30, 'pres' => [['CJ', 25, 'Caja x25', 850.00]]],
        'Lija de agua #100'                => ['cat' => 'Abrasivos y discos', 'marca' => null, 'und' => 'PLG', 'precio' => 18.00, 'mayoreo' => null, 'costo' => 9.50, 'stock' => 200, 'min' => 50],
        'Candado de acero 40 mm'           => ['cat' => 'Cerrajería y herrajes', 'marca' => 'Pretul', 'und' => 'UND', 'precio' => 160.00, 'mayoreo' => null, 'costo' => 98.00, 'stock' => 20, 'min' => 5],
        'Manguera para jardín 1/2"'        => ['cat' => 'Jardinería y agro', 'marca' => 'Truper', 'und' => 'M', 'precio' => 22.00, 'mayoreo' => null, 'costo' => 13.00, 'stock' => 150, 'min' => 30, 'pres' => [['RLL', 30, 'Rollo 30 m', 600.00]]],
        'Guantes de carnaza'               => ['cat' => 'Seguridad industrial', 'marca' => 'Truper', 'und' => 'PAR', 'precio' => 75.00, 'mayoreo' => null, 'costo' => 44.00, 'stock' => 40, 'min' => 10],
        'Corte de tubo o varilla'          => ['cat' => 'Servicios', 'marca' => null, 'und' => 'UND', 'precio' => 25.00, 'mayoreo' => null, 'costo' => 0, 'stock' => 0, 'min' => 0, 'servicio' => true],
        'Copia de llave'                   => ['cat' => 'Servicios', 'marca' => null, 'und' => 'UND', 'precio' => 40.00, 'mayoreo' => null, 'costo' => 0, 'stock' => 0, 'min' => 0, 'servicio' => true],
    ];

    /** @var list<array{nombre: string, contacto: string, telefono: string, dias_credito: int}> */
    private const PROVEEDORES = [
        ['nombre' => 'Distribuidora Ferretera del Occidente', 'contacto' => 'Carlos Mejía', 'telefono' => '26620000', 'dias_credito' => 30],
        ['nombre' => 'Materiales y Aceros de Copán', 'contacto' => 'Ana Paz', 'telefono' => '26621111', 'dias_credito' => 15],
        ['nombre' => 'Electro Suministros Hondureños', 'contacto' => 'José Rivera', 'telefono' => '99001122', 'dias_credito' => 0],
    ];

    public function run(): void
    {
        if (Producto::query()->exists()) {
            $this->command?->warn('Ya hay productos: se omite la demo de ferretería.');

            return;
        }

        foreach (self::PROVEEDORES as $proveedor) {
            Proveedor::query()->firstOrCreate(['nombre' => $proveedor['nombre']], $proveedor);
        }

        $unidades = UnidadMedida::query()->pluck('id', 'abreviatura');
        $categorias = Categoria::query()->whereNull('parent_id')->pluck('id', 'nombre');

        $ajuste = AjusteInventario::query()->create([
            'fecha'  => now()->toDateString(),
            'tipo'   => TipoAjuste::Entrada,
            'motivo' => MotivoAjuste::InventarioInicial,
            'notas'  => 'Inventario inicial de demostración',
        ]);

        foreach (self::PRODUCTOS as $nombre => $datos) {
            $marcaId = $datos['marca'] !== null
                ? Marca::query()->firstOrCreate(['nombre' => $datos['marca']])->id
                : null;

            $esServicio = $datos['servicio'] ?? false;

            $producto = Producto::query()->create([
                'nombre'           => $nombre,
                'categoria_id'     => $categorias[$datos['cat']],
                'marca_id'         => $marcaId,
                'unidad_medida_id' => $unidades[$datos['und']],
                'tipo_isv'         => TipoIsv::Gravado15,
                'precio_venta'     => $datos['precio'],
                'precio_mayoreo'   => $datos['mayoreo'],
                'stock_minimo'     => $datos['min'],
                'controla_stock'   => ! $esServicio,
            ]);

            foreach ($datos['pres'] ?? [] as [$und, $factor, $nombrePresentacion, $precio]) {
                $producto->presentaciones()->create([
                    'unidad_medida_id' => $unidades[$und],
                    'nombre'           => $nombrePresentacion,
                    'factor'           => $factor,
                    'precio_venta'     => $precio,
                ]);
            }

            if (! $esServicio && $datos['stock'] > 0) {
                $ajuste->detalles()->create([
                    'producto_id'    => $producto->id,
                    'cantidad'       => $datos['stock'],
                    'costo_unitario' => $datos['costo'],
                ]);
            }
        }

        app(AjusteInventarioService::class)->aplicar($ajuste);

        $this->command?->info('✓ Demo de ferretería: '.count(self::PRODUCTOS).' productos con su inventario inicial.');
    }
}
