<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategoriaGasto;
use App\Enums\TipoDocumentoCompra;
use App\Models\Compra;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factura de un proveedor con RTN: L 1,000 gravado al 15 %.
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
            'tipo_documento' => TipoDocumentoCompra::Factura,
            'fecha'          => now()->toDateString(),
            'numero_factura' => fake()->numerify('000-001-01-########'),
            'categoria'      => CategoriaGasto::Mercaderia,
            'proveedor_id'   => Proveedor::factory(),
            'rtn_proveedor'  => fake()->numerify('0801##########'),
            'gravado_15'     => 1000,
            'isv_15'         => 150,
        ];
    }

    /**
     * Recibo sin ISV: solo lo pagado.
     */
    public function recibo(float $total = 500): static
    {
        return $this->state(fn (): array => [
            'tipo_documento' => TipoDocumentoCompra::Recibo,
            'numero_factura' => null,
            'rtn_proveedor'  => null,
            'gravado_15'     => 0,
            'isv_15'         => 0,
            'exento'         => $total,
        ]);
    }
}
