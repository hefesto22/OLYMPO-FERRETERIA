<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Por qué se ajusta el inventario. El motivo ya dice si suma, resta o
 * deja lo contado; solo "Otro" pide escogerlo.
 */
enum MotivoAjuste: string implements HasDescription, HasIcon, HasLabel
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

    public function getDescription(): string
    {
        return match ($this) {
            self::InventarioInicial => 'Lo que ya había en la tienda al empezar a usar el sistema. Suma al inventario con su costo.',
            self::ConteoFisico      => 'Contaste lo que hay: escribe lo contado y el sistema deja la existencia en ese número.',
            self::DanoMerma         => 'Producto dañado, vencido o que se perdió al fraccionar. Resta del inventario.',
            self::Robo              => 'Faltante por robo o pérdida. Resta del inventario.',
            self::UsoInterno        => 'Lo que se usó en la tienda y no se vendió. Resta del inventario.',
            self::Otro              => 'Cualquier otro motivo: escoge si suma o resta y explícalo en la nota.',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::InventarioInicial => Heroicon::OutlinedInboxArrowDown,
            self::ConteoFisico      => Heroicon::OutlinedClipboardDocumentCheck,
            self::DanoMerma         => Heroicon::OutlinedExclamationTriangle,
            self::Robo              => Heroicon::OutlinedShieldExclamation,
            self::UsoInterno        => Heroicon::OutlinedWrenchScrewdriver,
            self::Otro              => Heroicon::OutlinedEllipsisHorizontalCircle,
        };
    }

    /**
     * Hacia dónde mueve el inventario. Null: lo escoge el usuario ("Otro").
     */
    public function tipo(): ?TipoAjuste
    {
        return match ($this) {
            self::InventarioInicial => TipoAjuste::Entrada,
            self::ConteoFisico      => TipoAjuste::Conteo,
            self::DanoMerma,
            self::Robo,
            self::UsoInterno => TipoAjuste::Salida,
            self::Otro       => null,
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
