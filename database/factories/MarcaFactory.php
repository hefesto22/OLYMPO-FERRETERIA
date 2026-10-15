<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Marca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Marca>
 */
class MarcaFactory extends Factory
{
    protected $model = Marca::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->bothify('Marca ####'),
            'activo' => true,
        ];
    }
}
