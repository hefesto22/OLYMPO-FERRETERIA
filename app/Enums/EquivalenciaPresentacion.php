<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cómo se captura la equivalencia de una presentación con la unidad base.
 *
 *  - Contiene:      cada presentación trae N unidades base.
 *                   Caja de clavos = 50 LB c/u; Rollo = 100 M c/u.
 *  - PorUnidadBase: en una unidad base caben N presentaciones.
 *                   Clavo suelto = 100 por LB; Cuarto = 4 por GAL.
 *
 * Internamente siempre se guarda el factor (unidades base por
 * presentación): 50, o 1/100 = 0.01.
 */
enum EquivalenciaPresentacion: string
{
    case Contiene = 'contiene';
    case PorUnidadBase = 'por_base';

    /**
     * Factor de conversión a unidad base a partir de lo tecleado.
     */
    public function factor(float $cantidad): float
    {
        if ($cantidad <= 0) {
            return 0.0;
        }

        return match ($this) {
            self::Contiene      => round($cantidad, 8),
            self::PorUnidadBase => round(1 / $cantidad, 8),
        };
    }

    /**
     * Texto que acompaña a la cantidad: "LB c/u" o "por LB".
     */
    public function etiqueta(string $abreviaturaBase): string
    {
        $base = $abreviaturaBase !== '' ? $abreviaturaBase : 'unidad base';

        return match ($this) {
            self::Contiene      => "{$base} c/u",
            self::PorUnidadBase => "por {$base}",
        };
    }

    /**
     * Normaliza el estado de un formulario Filament (enum o string).
     */
    public static function desdeEstado(mixed $estado): ?self
    {
        if ($estado instanceof self) {
            return $estado;
        }

        return is_string($estado) ? self::tryFrom($estado) : null;
    }
}
