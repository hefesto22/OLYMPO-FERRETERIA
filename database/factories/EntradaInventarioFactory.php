<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EntradaInventario;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea la entrada en borrador, sin líneas.
 *
 * @extends Factory<EntradaInventario>
 */
class EntradaInventarioFactory extends Factory
{
    protected $model = EntradaInventario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proveedor_id' => Proveedor::factory(),
            'referencia'   => fake()->numerify('000-001-01-########'),
            'fecha'        => now()->toDateString(),
        ];
    }
}
