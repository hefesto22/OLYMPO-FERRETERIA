<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Convierte un monto a letras para el "total en letras" de la factura:
 *
 *   1234.56  → "MIL DOSCIENTOS TREINTA Y CUATRO LEMPIRAS CON 56/100"
 *   21       → "VEINTIÚN LEMPIRAS CON 00/100"
 *   1000000  → "UN MILLÓN DE LEMPIRAS CON 00/100"
 *
 * Soporta hasta 999,999,999.99.
 */
final class NumeroALetras
{
    private const UNIDADES = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
        'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
    ];

    private const DECENAS = [
        3 => 'TREINTA',
        4 => 'CUARENTA',
        5 => 'CINCUENTA',
        6 => 'SESENTA',
        7 => 'SETENTA',
        8 => 'OCHENTA',
        9 => 'NOVENTA',
    ];

    private const CENTENAS = [
        1 => 'CIENTO',
        2 => 'DOSCIENTOS',
        3 => 'TRESCIENTOS',
        4 => 'CUATROCIENTOS',
        5 => 'QUINIENTOS',
        6 => 'SEISCIENTOS',
        7 => 'SETECIENTOS',
        8 => 'OCHOCIENTOS',
        9 => 'NOVECIENTOS',
    ];

    public static function convertir(float $monto, string $monedaSingular = 'LEMPIRA', string $monedaPlural = 'LEMPIRAS'): string
    {
        $centavosTotales = (int) round(abs($monto) * 100);
        $entero = intdiv($centavosTotales, 100);
        $centavos = $centavosTotales % 100;

        $moneda = $entero === 1 ? $monedaSingular : $monedaPlural;

        return sprintf('%s %s CON %02d/100', self::entero($entero), $moneda, $centavos);
    }

    /**
     * Número entero en letras, con "UNO" apocopado a "UN" al final porque
     * siempre va seguido de la moneda ("VEINTIÚN LEMPIRAS").
     */
    public static function entero(int $numero): string
    {
        if ($numero === 0) {
            return 'CERO';
        }

        $millones = intdiv($numero, 1_000_000);
        $miles = intdiv($numero % 1_000_000, 1000);
        $resto = $numero % 1000;

        $partes = [];

        if ($millones > 0) {
            $partes[] = $millones === 1 ? 'UN MILLÓN' : self::apocopar(self::centenas($millones)).' MILLONES';
        }

        if ($miles > 0) {
            $partes[] = $miles === 1 ? 'MIL' : self::apocopar(self::centenas($miles)).' MIL';
        }

        if ($resto > 0) {
            $partes[] = self::centenas($resto);
        }

        $texto = self::apocopar(implode(' ', $partes));

        // "UN MILLÓN DE LEMPIRAS", "DOS MILLONES DE LEMPIRAS".
        if ($numero % 1_000_000 === 0) {
            $texto .= ' DE';
        }

        return $texto;
    }

    private static function centenas(int $numero): string
    {
        if ($numero === 100) {
            return 'CIEN';
        }

        $centena = intdiv($numero, 100);
        $resto = $numero % 100;

        $partes = [];

        if ($centena > 0) {
            $partes[] = self::CENTENAS[$centena];
        }

        if ($resto > 0) {
            $partes[] = self::decenas($resto);
        }

        return implode(' ', $partes);
    }

    private static function decenas(int $numero): string
    {
        if ($numero < 30) {
            return self::UNIDADES[$numero];
        }

        $decena = intdiv($numero, 10);
        $unidad = $numero % 10;

        return $unidad > 0
            ? self::DECENAS[$decena].' Y '.self::UNIDADES[$unidad]
            : self::DECENAS[$decena];
    }

    /**
     * "UNO" → "UN" y "VEINTIUNO" → "VEINTIÚN" cuando van antes de un sustantivo.
     */
    private static function apocopar(string $texto): string
    {
        if (str_ends_with($texto, 'VEINTIUNO')) {
            return substr($texto, 0, -strlen('VEINTIUNO')).'VEINTIÚN';
        }

        if ($texto === 'UNO' || str_ends_with($texto, ' UNO')) {
            return substr($texto, 0, -strlen('UNO')).'UN';
        }

        return $texto;
    }
}
