<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnidadMedida>
 */
class UnidadMedidaFactory extends Factory
{
    protected $model = UnidadMedida::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre'            => fake()->unique()->bothify('Unidad ####'),
            'abreviatura'       => fake()->unique()->bothify('U###'),
            'permite_decimales' => false,
            'activo'            => true,
        ];
    }

    /**
     * Unidad fraccionable (metro, libra, galón).
     */
    public function fraccionable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'permite_decimales' => true,
        ]);
    }
}
