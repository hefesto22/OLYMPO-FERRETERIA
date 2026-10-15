<?php

declare(strict_types=1);

namespace App\Support\Inventario;

use App\Models\Producto;
use App\Models\ProductoPresentacion;

/**
 * Datos de productos que necesitan los formularios de compras, ajustes
 * y (en la fase de ventas) el punto de venta: etiquetas, presentaciones,
 * factores de conversión y costos sugeridos.
 *
 * Recibe ids tal como llegan del estado de Filament (int, string o null).
 */
final class OpcionesProducto
{
    /**
     * "P-000123 · Tornillo goloso 8 x 1 1/2" (UND)".
     */
    public static function etiqueta(Producto $producto): string
    {
        $nombre = $producto->nombre.' ('.$producto->unidadMedida->abreviatura.')';

        return $producto->codigo !== null && $producto->codigo !== ''
            ? $producto->codigo.' · '.$nombre
            : $nombre;
    }

    /**
     * Presentaciones activas del producto para un Select: [id => "Caja x100"].
     *
     * @return array<int, string>
     */
    public static function presentaciones(mixed $productoId): array
    {
        $id = self::id($productoId);

        if ($id === null) {
            return [];
        }

        return ProductoPresentacion::query()
            ->with(['unidadMedida', 'producto.unidadMedida'])
            ->where('producto_id', $id)
            ->where('activo', true)
            ->orderBy('factor')
            ->get()
            ->mapWithKeys(fn (ProductoPresentacion $presentacion): array => [
                $presentacion->id => $presentacion->etiqueta().' = '.cantidadFormato((float) $presentacion->factor).' '.$presentacion->producto->unidadMedida->abreviatura,
            ])
            ->all();
    }

    /**
     * Texto para la opción "sin presentación": "Unidad base (LB)".
     */
    public static function etiquetaUnidadBase(mixed $productoId): string
    {
        $producto = self::producto($productoId);

        return $producto !== null
            ? 'Unidad base ('.$producto->unidadMedida->nombre.')'
            : 'Unidad base';
    }

    public static function abreviaturaBase(mixed $productoId): string
    {
        return self::producto($productoId)?->unidadMedida->abreviatura ?? '';
    }

    /**
     * Factor de conversión a unidad base (1 si no hay presentación).
     */
    public static function factor(mixed $productoId, mixed $presentacionId): float
    {
        $presentacion = self::presentacion($productoId, $presentacionId);

        return $presentacion !== null ? (float) $presentacion->factor : 1.0;
    }

    /**
     * Si la unidad en que se captura (presentación o base) admite decimales.
     */
    public static function permiteDecimales(mixed $productoId, mixed $presentacionId): bool
    {
        $presentacion = self::presentacion($productoId, $presentacionId);

        if ($presentacion !== null) {
            return $presentacion->unidadMedida->permite_decimales;
        }

        return self::producto($productoId)?->unidadMedida->permite_decimales ?? true;
    }

    /**
     * Costo sugerido para una compra, sin ISV: el último costo (o el
     * promedio si nunca se ha comprado) multiplicado por el factor.
     */
    public static function costoSugerido(mixed $productoId, mixed $presentacionId): float
    {
        $producto = self::producto($productoId);

        if ($producto === null) {
            return 0.0;
        }

        $costoBase = (float) $producto->ultimo_costo > 0
            ? (float) $producto->ultimo_costo
            : (float) $producto->costo_promedio;

        return round($costoBase * self::factor($productoId, $presentacionId), 4);
    }

    public static function existencia(mixed $productoId): ?float
    {
        $producto = self::producto($productoId);

        if ($producto === null || ! $producto->controla_stock) {
            return null;
        }

        return (float) $producto->stock;
    }

    public static function producto(mixed $productoId): ?Producto
    {
        $id = self::id($productoId);

        return $id !== null
            ? Producto::withTrashed()->with('unidadMedida')->find($id)
            : null;
    }

    private static function presentacion(mixed $productoId, mixed $presentacionId): ?ProductoPresentacion
    {
        $productoId = self::id($productoId);
        $presentacionId = self::id($presentacionId);

        if ($productoId === null || $presentacionId === null) {
            return null;
        }

        return ProductoPresentacion::query()
            ->with('unidadMedida')
            ->where('producto_id', $productoId)
            ->find($presentacionId);
    }

    private static function id(mixed $valor): ?int
    {
        if (is_int($valor)) {
            return $valor > 0 ? $valor : null;
        }

        if (is_string($valor) && ctype_digit($valor)) {
            return (int) $valor > 0 ? (int) $valor : null;
        }

        return null;
    }
}
