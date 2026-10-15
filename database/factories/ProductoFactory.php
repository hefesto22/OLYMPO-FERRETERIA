<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoIsv;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre'           => fake()->unique()->bothify('Producto ####'),
            'categoria_id'     => Categoria::factory(),
            'unidad_medida_id' => UnidadMedida::factory(),
            'tipo_isv'         => TipoIsv::Gravado15,
            'precio_venta'     => fake()->randomFloat(2, 10, 500),
            'stock_minimo'     => 0,
            'controla_stock'   => true,
            'activo'           => true,
        ];
    }

    public function exento(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo_isv' => TipoIsv::Exento,
        ]);
    }

    /**
     * Servicio sin control de existencias (corte de tubo, copia de llave).
     */
    public function servicio(): static
    {
        return $this->state(fn (array $attributes): array => [
            'controla_stock' => false,
        ]);
    }
}
