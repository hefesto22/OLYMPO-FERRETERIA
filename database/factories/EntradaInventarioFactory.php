<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EntradaInventario;
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
            'fecha' => now()->toDateString(),
        ];
    }
}
