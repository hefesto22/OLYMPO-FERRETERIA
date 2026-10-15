<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Reglas del negocio de la ferretería
|--------------------------------------------------------------------------
|
| Parámetros propios de este sistema (inventario, numeración de
| documentos). Lo fiscal de Honduras vive en config/honduras.php.
|
*/

return [

    'inventario' => [
        // Si es false, ninguna salida (venta, ajuste, anulación de compra)
        // puede dejar una existencia en negativo: se lanza
        // StockInsuficienteException y la operación completa se revierte.
        'permitir_stock_negativo' => (bool) env('FERRETERIA_PERMITIR_STOCK_NEGATIVO', false),
    ],

    'documentos' => [
        // Prefijos de la numeración interna: P-000123, C-000045, AJ-000007.
        'prefijo_producto' => 'P',
        'prefijo_compra'   => 'C',
        'prefijo_ajuste'   => 'AJ',
        'digitos'          => 6,
    ],
];
