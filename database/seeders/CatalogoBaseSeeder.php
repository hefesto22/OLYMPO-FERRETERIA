<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

/**
 * Unidades de medida y categorías típicas de una ferretería hondureña.
 *
 * Idempotente: se puede correr en producción las veces que sea; solo
 * crea lo que falta y no toca lo que el cliente ya editó.
 */
class CatalogoBaseSeeder extends Seeder
{
    /**
     * [nombre, abreviatura, permite decimales]
     *
     * @var list<array{0: string, 1: string, 2: bool}>
     */
    private const UNIDADES = [
        ['Unidad', 'UND', false],
        ['Metro', 'M', true],
        ['Pie', 'PIE', true],
        ['Yarda', 'YD', true],
        ['Metro cuadrado', 'M2', true],
        ['Metro cúbico', 'M3', true],
        ['Libra', 'LB', true],
        ['Kilogramo', 'KG', true],
        ['Onza', 'OZ', true],
        ['Quintal', 'QQ', true],
        ['Galón', 'GAL', true],
        ['Litro', 'LT', true],
        ['Cuarto de galón', 'QT', false],
        ['Cubeta', 'CUB', false],
        ['Caja', 'CJ', false],
        ['Bolsa', 'BLS', false],
        ['Saco', 'SCO', false],
        ['Rollo', 'RLL', false],
        ['Paquete', 'PQT', false],
        ['Par', 'PAR', false],
        ['Juego', 'JGO', false],
        ['Docena', 'DOC', false],
        ['Pliego', 'PLG', false],
        ['Lámina', 'LAM', false],
        ['Varilla', 'VAR', false],
        ['Botella', 'BOT', false],
    ];

    /** @var list<string> */
    private const CATEGORIAS = [
        'Materiales de construcción',
        'Hierro y acero',
        'Tornillería y fijaciones',
        'Herramientas manuales',
        'Herramientas eléctricas',
        'Electricidad',
        'Iluminación',
        'Plomería y PVC',
        'Pinturas y acabados',
        'Adhesivos y selladores',
        'Cerrajería y herrajes',
        'Abrasivos y discos',
        'Seguridad industrial',
        'Jardinería y agro',
        'Servicios',
        'General',
    ];

    public function run(): void
    {
        foreach (self::UNIDADES as [$nombre, $abreviatura, $permiteDecimales]) {
            UnidadMedida::query()->firstOrCreate(
                ['abreviatura' => $abreviatura],
                ['nombre' => $nombre, 'permite_decimales' => $permiteDecimales, 'activo' => true],
            );
        }

        foreach (self::CATEGORIAS as $nombre) {
            Categoria::query()->firstOrCreate(
                ['nombre' => $nombre, 'parent_id' => null],
                ['activo' => true],
            );
        }

        $this->command?->info('✓ Catálogo base: '.count(self::UNIDADES).' unidades de medida y '.count(self::CATEGORIAS).' categorías.');
    }
}
