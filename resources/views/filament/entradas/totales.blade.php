{{-- Totales de la entrada, a la derecha, como en una factura. --}}
<dl class="ol-totales">
    <div>
        <dt>Costo neto (sin ISV)</dt>
        <dd>{{ lempiras($totales['subtotal']) }}</dd>
    </div>
    <div>
        <dt>ISV</dt>
        <dd>{{ lempiras($totales['isv']) }}</dd>
    </div>
    <div class="es-total">
        <dt>Total con ISV</dt>
        <dd>{{ lempiras($totales['total']) }}</dd>
    </div>
</dl>
