<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Caja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Caja>
 */
class CajaFactory extends Factory
{
    protected $model = Caja::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(1, 999);

        return [
            'nombre'        => 'Caja '.$numero,
            'punto_emision' => str_pad((string) $numero, 3, '0', STR_PAD_LEFT),
            'activo'        => true,
        ];
    }
}
