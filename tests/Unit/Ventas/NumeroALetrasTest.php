<?php

declare(strict_types=1);

use App\Support\NumeroALetras;

it('convierte montos a letras para la factura', function (float $monto, string $letras): void {
    expect(NumeroALetras::convertir($monto))->toBe($letras);
})->with([
    [0, 'CERO LEMPIRAS CON 00/100'],
    [1, 'UN LEMPIRA CON 00/100'],
    [16, 'DIECISÉIS LEMPIRAS CON 00/100'],
    [21, 'VEINTIÚN LEMPIRAS CON 00/100'],
    [31.01, 'TREINTA Y UN LEMPIRAS CON 01/100'],
    [100, 'CIEN LEMPIRAS CON 00/100'],
    [101, 'CIENTO UN LEMPIRAS CON 00/100'],
    [115.5, 'CIENTO QUINCE LEMPIRAS CON 50/100'],
    [1000, 'MIL LEMPIRAS CON 00/100'],
    [1001, 'MIL UN LEMPIRAS CON 00/100'],
    [21000, 'VEINTIÚN MIL LEMPIRAS CON 00/100'],
    [1234.56, 'MIL DOSCIENTOS TREINTA Y CUATRO LEMPIRAS CON 56/100'],
    [999999.99, 'NOVECIENTOS NOVENTA Y NUEVE MIL NOVECIENTOS NOVENTA Y NUEVE LEMPIRAS CON 99/100'],
    [1000000, 'UN MILLÓN DE LEMPIRAS CON 00/100'],
    [2500000, 'DOS MILLONES QUINIENTOS MIL LEMPIRAS CON 00/100'],
]);
