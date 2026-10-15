<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Domain\Exceptions\CaiException;
use App\Models\Caja;
use App\Models\RangoCai;
use App\Models\Secuencia;
use Illuminate\Support\Facades\DB;

/**
 * Numeración de documentos de venta.
 *
 *  - Factura: siguiente correlativo del rango CAI activo de la caja.
 *    Se bloquea la fila del rango, así dos cajas o dos ventas simultáneas
 *    nunca repiten ni saltan número. Si la venta falla después, la
 *    transacción revierte el avance.
 *  - Recibo: contador interno REC-000123, también con bloqueo.
 */
final class NumeracionService
{
    public const CLAVE_RECIBO = 'recibo';

    public function siguienteRecibo(): string
    {
        return 'REC-'.str_pad((string) $this->tomarSecuencia(self::CLAVE_RECIBO), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{rango: RangoCai, numero: string}
     */
    public function siguienteFactura(Caja $caja): array
    {
        /** @var array{rango: RangoCai, numero: string} $factura */
        $factura = DB::transaction(function () use ($caja): array {
            $rango = RangoCai::query()
                ->where('caja_id', $caja->id)
                ->where('activo', true)
                ->lockForUpdate()
                ->first();

            if ($rango === null) {
                throw CaiException::sinRango($caja->nombre);
            }

            if ($rango->estaVencido()) {
                throw CaiException::vencido($caja->nombre, $rango->fecha_limite_emision->format('d/m/Y'));
            }

            if ($rango->estaAgotado()) {
                throw CaiException::agotado($caja->nombre);
            }

            $correlativo = $rango->siguiente;
            $rango->forceFill(['siguiente' => $correlativo + 1])->save();

            return [
                'rango'  => $rango,
                'numero' => $rango->formatearNumero($correlativo),
            ];
        });

        return $factura;
    }

    private function tomarSecuencia(string $clave): int
    {
        return DB::transaction(function () use ($clave): int {
            // Crea la fila la primera vez sin carrera (ON CONFLICT DO NOTHING).
            Secuencia::query()->insertOrIgnore([
                'clave'      => $clave,
                'siguiente'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $secuencia = Secuencia::query()->where('clave', $clave)->lockForUpdate()->firstOrFail();
            $numero = $secuencia->siguiente;
            $secuencia->forceFill(['siguiente' => $numero + 1])->save();

            return $numero;
        });
    }
}
