<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Lista de precio de una venta. El cliente trae la suya y el cajero la puede cambiar.
 */
enum ListaPrecio: string implements HasColor, HasIcon, HasLabel
{
    case Publico = 'publico';
    case Mayoreo = 'mayoreo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Publico => 'Público',
            self::Mayoreo => 'Mayoreo / contratista',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Publico => 'gray',
            self::Mayoreo => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Publico => Heroicon::OutlinedUserGroup,
            self::Mayoreo => Heroicon::OutlinedBuildingOffice2,
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
