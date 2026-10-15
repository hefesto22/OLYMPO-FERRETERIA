<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ListaPrecio;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre'      => fake()->unique()->name(),
            'telefono'    => fake()->numerify('9#######'),
            'tipo_precio' => ListaPrecio::Publico,
            'activo'      => true,
        ];
    }

    /**
     * Contratista: compra a precio de mayoreo.
     */
    public function mayoreo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo_precio' => ListaPrecio::Mayoreo,
        ]);
    }
}
