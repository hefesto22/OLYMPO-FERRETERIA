<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * En qué se gastó el dinero de una compra.
 */
enum CategoriaGasto: string implements HasColor, HasLabel
{
    case Mercaderia = 'mercaderia';
    case Servicios = 'servicios';
    case Alquiler = 'alquiler';
    case Transporte = 'transporte';
    case Mantenimiento = 'mantenimiento';
    case Papeleria = 'papeleria';
    case Publicidad = 'publicidad';
    case Otros = 'otros';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mercaderia    => 'Mercadería para vender',
            self::Servicios     => 'Luz, agua, teléfono e internet',
            self::Alquiler      => 'Alquiler',
            self::Transporte    => 'Transporte y combustible',
            self::Mantenimiento => 'Mantenimiento y reparaciones',
            self::Papeleria     => 'Papelería, limpieza y oficina',
            self::Publicidad    => 'Publicidad',
            self::Otros         => 'Otros gastos',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Mercaderia => 'primary',
            self::Servicios  => 'info',
            self::Alquiler   => 'warning',
            default          => 'gray',
        };
    }
}
