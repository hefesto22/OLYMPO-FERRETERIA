<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre'       => fake()->unique()->company(),
            'telefono'     => fake()->numerify('9#######'),
            'dias_credito' => 0,
            'activo'       => true,
        ];
    }
}
