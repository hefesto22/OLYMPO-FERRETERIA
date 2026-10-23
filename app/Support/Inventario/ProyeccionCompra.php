<?php

declare(strict_types=1);

namespace App\Support\Inventario;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Services\Inventario\InventarioService;

/**
 * Cómo queda cada producto si se confirma la compra: lo que hay en
 * existencia y a qué costo, lo que entra, el costo ponderado nuevo y el
 * precio de venta que va a quedar.
 *
 * Usa las mismas reglas que al confirmar: el promedio ponderado de
 * InventarioService y la regla de PrecioService (si el costo sube, el
 * precio sube solo; si baja, el precio se queda y se avisa al dueño). Las
 * líneas del mismo producto se acumulan en orden, como entran al
 * confirmar. Solo calcula: no guarda nada.
 *
 * @phpstan-type LineaProyectada array{unidad: string, controla_stock: bool, existencia: float, costo_antes: float, entra: float, costo_entrada: float, costo_nuevo: float, precio_antes: float, precio_nuevo: float, precio_propuesto: float|null, estado: string}
 */
final class ProyeccionCompra
{
    /** Precio que sube solo con el costo. */
    public const SUBE = 'sube';

    /** El precio no cambia (o el redondeo lo deja igual). */
    public const IGUAL = 'igual';

    /** Bajaría: el precio se queda hasta que el dueño decida. */
    public const BAJA = 'baja';

    /** Primer costo del producto: el precio se fija con el margen. */
    public const NUEVO = 'nuevo';

    /** Precio a mano: el costo no lo mueve. */
    public const MANUAL = 'manual';

    /** Servicio: no lleva existencia ni costo. */
    public const SERVICIO = 'servicio';

    /** Los costos van a 4 decimales: diferencias menores se ignoran. */
    private const EPSILON = 0.00005;

    /**
     * @param array<array-key, mixed>|null $lineas estado del repeater "detalles"
     *
     * @return array<array-key, LineaProyectada> por clave de línea
     */
    public static function desdeFormulario(?array $lineas): array
    {
        $filas = [];

        foreach ($lineas ?? [] as $clave => $linea) {
            if (! is_array($linea) || ! is_numeric($linea['producto_id'] ?? null)) {
                continue;
            }

            $filas[$clave] = [
                'producto_id'     => (int) $linea['producto_id'],
                'presentacion_id' => is_numeric($linea['producto_presentacion_id'] ?? null) ? (int) $linea['producto_presentacion_id'] : null,
                'cantidad'        => self::numero($linea['cantidad'] ?? null),
                'costo'           => self::numero($linea['costo_unitario'] ?? null),
            ];
        }

        if ($filas === []) {
            return [];
        }

        $productos = Producto::withTrashed()
            ->with(['unidadMedida', 'presentaciones'])
            ->whereIn('id', array_values(array_unique(array_column($filas, 'producto_id'))))
            ->get()
            ->keyBy('id');

        $inventario = app(InventarioService::class);

        /** @var array<int, array{stock: float, costo: float}> $acumulado */
        $acumulado = [];
        $resultado = [];

        foreach ($filas as $clave => $fila) {
            $producto = $productos->get($fila['producto_id']);

            if (! $producto instanceof Producto) {
                continue;
            }

            $presentacion = $fila['presentacion_id'] !== null
                ? $producto->presentaciones->firstWhere('id', $fila['presentacion_id'])
                : null;
            $factor = $presentacion instanceof ProductoPresentacion && (float) $presentacion->factor > 0
                ? (float) $presentacion->factor
                : 1.0;

            $antes = $acumulado[$producto->id] ?? ['stock' => (float) $producto->stock, 'costo' => (float) $producto->costo_promedio];
            $entra = round($fila['cantidad'] * $factor, 6);
            $costoEntrada = round($fila['costo'] / $factor, 4);
            $costoNuevo = $producto->controla_stock && $entra > 0
                ? $inventario->costoPromedioTrasEntrada($antes['stock'], $antes['costo'], $entra, $costoEntrada)
                : round($antes['costo'], 4);

            if ($producto->controla_stock && $entra > 0) {
                $acumulado[$producto->id] = ['stock' => round($antes['stock'] + $entra, 6), 'costo' => $costoNuevo];
            }

            $resultado[$clave] = [
                'unidad'         => $producto->unidadMedida->abreviatura,
                'controla_stock' => $producto->controla_stock,
                'existencia'     => $antes['stock'],
                'costo_antes'    => round($antes['costo'], 4),
                'entra'          => $entra,
                'costo_entrada'  => $costoEntrada,
                'costo_nuevo'    => $costoNuevo,
                ...self::precio($producto, $costoNuevo),
            ];
        }

        return $resultado;
    }

    /**
     * Precio público que va a quedar con el costo nuevo.
     *
     * @return array{precio_antes: float, precio_nuevo: float, precio_propuesto: float|null, estado: string}
     */
    private static function precio(Producto $producto, float $costoNuevo): array
    {
        $vigente = round((float) $producto->precio_venta, 2);
        $igual = ['precio_antes' => $vigente, 'precio_nuevo' => $vigente, 'precio_propuesto' => null];

        if (! $producto->controla_stock) {
            return [...$igual, 'estado' => self::SERVICIO];
        }

        if (! $producto->usaMargen()) {
            return [...$igual, 'estado' => self::MANUAL];
        }

        $costoPrecio = round((float) $producto->costo_precio, 4);
        $calculado = $producto->precioConMargen($costoNuevo) ?? 0.0;

        if ($costoPrecio <= 0) {
            return ['precio_antes' => $vigente, 'precio_nuevo' => $calculado, 'precio_propuesto' => null, 'estado' => self::NUEVO];
        }

        if (abs($calculado - $vigente) < 0.005) {
            return [...$igual, 'estado' => self::IGUAL];
        }

        if ($costoNuevo > $costoPrecio + self::EPSILON) {
            return ['precio_antes' => $vigente, 'precio_nuevo' => $calculado, 'precio_propuesto' => null, 'estado' => self::SUBE];
        }

        return [...$igual, 'precio_propuesto' => $calculado, 'estado' => self::BAJA];
    }

    private static function numero(mixed $valor): float
    {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }
}
