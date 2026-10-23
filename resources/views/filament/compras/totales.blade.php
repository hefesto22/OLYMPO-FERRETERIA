{{-- Totales de la compra, a la derecha, como en la factura del proveedor. --}}
<dl class="ol-totales">
    <div>
        <dt>Subtotal sin ISV</dt>
        <dd>{{ lempiras($totales['subtotal']) }}</dd>
    </div>
    <div>
        <dt>ISV</dt>
        <dd>{{ lempiras($totales['isv']) }}</dd>
    </div>
    <div class="es-total">
        <dt>Total</dt>
        <dd>{{ lempiras($totales['total']) }}</dd>
    </div>
</dl>
