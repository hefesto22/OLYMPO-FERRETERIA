<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MotivoAjuste: string implements HasLabel
{
    case InventarioInicial = 'inventario_inicial';
    case ConteoFisico = 'conteo_fisico';
    case DanoMerma = 'dano_merma';
    case Robo = 'robo';
    case UsoInterno = 'uso_interno';
    case Otro = 'otro';

    public function getLabel(): string
    {
        return match ($this) {
            self::InventarioInicial => 'Inventario inicial',
            self::ConteoFisico      => 'Conteo físico',
            self::DanoMerma         => 'Daño o merma',
            self::Robo              => 'Robo o pérdida',
            self::UsoInterno        => 'Uso interno',
            self::Otro              => 'Otro',
        };
    }
}
