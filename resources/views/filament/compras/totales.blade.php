{{--
    Cierre de la factura del proveedor en el modal de compra: lo que no
    lleva ISV, el ISV que se descuenta de las ventas y el total.
    Datos de App\Filament\Resources\Compras\Schemas\CompraForm::totales().
--}}
<div>
    <dl class="ol-totales">
        <div>
            <dt>Sin ISV</dt>
            <dd>{{ lempiras($sin_isv) }}</dd>
        </div>
        <div>
            <dt>ISV que se descuenta</dt>
            <dd>{{ lempiras($sin_rtn ? 0 : $isv) }}</dd>
        </div>
        <div class="es-total">
            <dt>Total</dt>
            <dd>{{ lempiras($total) }}</dd>
        </div>
    </dl>

    @if ($sin_rtn)
        <p class="ol-totales__nota">Sin el RTN del proveedor, el SAR no acepta descontar los {{ lempiras($isv) }} de ISV.</p>
    @endif
</div>
