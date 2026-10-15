<?php

declare(strict_types=1);

namespace App\Services\Ventas\Datos;

use App\Enums\ListaPrecio;
use App\Enums\TipoDocumentoVenta;

/**
 * Datos de una venta tal como salen del punto de venta.
 */
final readonly class DatosVenta
{
    /**
     * @param list<LineaVenta> $lineas
     * @param float|null $efectivoRecibido null = el cliente pagó el efectivo exacto
     */
    public function __construct(
        public array $lineas,
        public TipoDocumentoVenta $tipoDocumento = TipoDocumentoVenta::Recibo,
        public ListaPrecio $listaPrecio = ListaPrecio::Publico,
        public ?int $clienteId = null,
        public ?string $clienteNombre = null,
        public ?string $clienteRtn = null,
        public ?float $efectivoRecibido = null,
        public float $montoTarjeta = 0.0,
        public ?string $referenciaTarjeta = null,
        public float $montoTransferencia = 0.0,
        public ?string $referenciaTransferencia = null,
        public ?string $notas = null,
    ) {}
}
