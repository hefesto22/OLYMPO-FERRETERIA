<?php

declare(strict_types=1);

namespace App\Services\Ventas\Datos;

use App\Enums\ListaPrecio;
use App\Enums\TipoDescuento;
use App\Enums\TipoDocumentoVenta;

/**
 * Datos de una venta tal como salen del punto de venta.
 */
final readonly class DatosVenta
{
    /**
     * @param list<LineaVenta> $lineas
     * @param float|null $efectivoRecibido null = el cliente pagó el efectivo exacto
     * @param float $descuentoGlobal descuento a toda la venta (% o lempiras), aparte del de cada línea
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
        public float $descuentoGlobal = 0.0,
        public TipoDescuento $tipoDescuentoGlobal = TipoDescuento::Porcentaje,
    ) {}
}
