<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Compra;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea la compra en borrador, sin líneas.
 *
 * @extends Factory<Compra>
 */
class CompraFactory extends Factory
{
    protected $model = Compra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proveedor_id'             => Proveedor::factory(),
            'numero_factura_proveedor' => fake()->numerify('000-001-01-########'),
            'fecha'                    => now()->toDateString(),
        ];
    }
}
