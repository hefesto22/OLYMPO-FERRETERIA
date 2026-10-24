@php
    $turno = $this->turno();
@endphp

<x-filament-panels::page>
    <style>
        /* ── Punto de venta ──────────────────────────────────────────────
           Colores del panel (variables de Filament): sirve en claro y oscuro
           y no necesita recompilar los estilos.
           Computadora: catálogo y carrito lado a lado.
           Teléfono y tablet: uno a la vez, con una barra abajo para ir al
           carrito. */
        .pos {
            --pos-superficie: #fff;
            --pos-fondo: var(--gray-50);
            --pos-linea: var(--gray-200);
            --pos-linea-suave: var(--gray-100);
            --pos-texto: var(--gray-950);
            --pos-tenue: var(--gray-500);
            --pos-resalte: color-mix(in oklab, var(--primary-500) 12%, transparent);
            --pos-alto: max(34rem, calc(100dvh - 11rem));
            color: var(--pos-texto);
        }

        .dark .pos {
            --pos-superficie: var(--gray-900);
            --pos-fondo: var(--gray-950);
            --pos-linea: color-mix(in oklab, white 10%, transparent);
            --pos-linea-suave: color-mix(in oklab, white 5%, transparent);
            --pos-texto: var(--gray-50);
            --pos-tenue: var(--gray-400);
        }

        .pos-cifra { font-variant-numeric: tabular-nums; }

        .pos-tecla {
            display: inline-block;
            min-width: 1.6rem;
            padding: 0.15rem 0.4rem;
            border: 1px solid var(--pos-linea);
            border-bottom-width: 2px;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1.2;
            text-align: center;
            color: var(--pos-tenue);
            background: var(--pos-superficie);
        }

        /* Franja del turno */
        .pos-franja {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.875rem;
        }

        .pos-franja__turno { display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--pos-tenue); }
        .pos-franja__turno strong { color: var(--pos-texto); font-weight: 600; }
        .pos-franja__punto { flex: none; width: 0.5rem; height: 0.5rem; border-radius: 999px; background: var(--success-500); box-shadow: 0 0 0 3px color-mix(in oklab, var(--success-500) 25%, transparent); }
        .pos-franja__acciones { display: flex; flex-wrap: wrap; gap: 0.5rem; }

        /* Dos zonas */
        .pos-zonas {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(23rem, 28rem);
            gap: 1rem;
            height: var(--pos-alto);
        }

        .pos-panel {
            display: flex;
            flex-direction: column;
            min-height: 0;
            background: var(--pos-superficie);
            border: 1px solid var(--pos-linea);
            border-radius: 1rem;
            overflow: hidden;
        }

        /* Catálogo */
        .pos-catalogo__cabeza { display: grid; grid-template-columns: minmax(0, 1fr) 14rem; gap: 0.625rem; padding: 1rem; border-bottom: 1px solid var(--pos-linea); }

        .pos-buscador {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            height: 3rem;
            padding: 0 0.375rem 0 0.875rem;
            border: 1px solid var(--pos-linea);
            border-radius: 0.75rem;
            background: var(--pos-fondo);
            cursor: text;
        }

        .pos-buscador:focus-within { border-color: var(--primary-500); box-shadow: 0 0 0 3px var(--pos-resalte); }
        .pos-buscador__icono { width: 1.25rem; height: 1.25rem; flex: none; color: var(--pos-tenue); }
        .pos-buscador input { flex: 1; min-width: 0; height: 100%; border: 0; outline: 0; background: transparent; font-size: 1rem; color: var(--pos-texto); box-shadow: none; padding: 0; }
        .pos-buscador input::placeholder { color: var(--pos-tenue); }
        .pos-buscador input::-webkit-search-cancel-button { display: none; }

        .pos-buscador__camara {
            display: grid;
            place-items: center;
            flex: none;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.5rem;
            color: var(--pos-tenue);
        }

        .pos-buscador__camara:hover { color: var(--pos-texto); background: var(--pos-linea-suave); }
        .pos-buscador__camara:focus-visible { outline: 2px solid var(--primary-500); outline-offset: 1px; }
        .pos-buscador__camara svg { width: 1.375rem; height: 1.375rem; }

        .pos-lista { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }

        .pos-fila {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--pos-linea-suave);
        }

        .pos-fila:hover, .pos-fila.es-activa { background: var(--pos-resalte); }
        .pos-fila.es-activa::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 3px; background: var(--primary-500); }

        .pos-fila__nombre { font-weight: 600; line-height: 1.3; }
        .pos-fila__meta { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.75rem; margin-top: 0.2rem; font-size: 0.8125rem; color: var(--pos-tenue); }
        .pos-fila__presentaciones { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-top: 0.5rem; }
        .pos-fila__precio { text-align: right; font-weight: 600; white-space: nowrap; }
        .pos-fila__precio small { display: block; font-size: 0.75rem; font-weight: 400; color: var(--pos-tenue); }

        .pos-existencia { font-weight: 500; }
        .pos-existencia--bajo { color: var(--warning-600); }
        .pos-existencia--agotado { color: var(--danger-600); }
        .dark .pos-existencia--bajo { color: var(--warning-400); }
        .dark .pos-existencia--agotado { color: var(--danger-400); }

        .pos-chip {
            display: inline-flex;
            align-items: baseline;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            border: 1px solid var(--pos-linea);
            border-radius: 999px;
            font-size: 0.8125rem;
            color: var(--pos-tenue);
            background: var(--pos-superficie);
        }

        .pos-chip strong { color: var(--pos-texto); font-weight: 600; }
        .pos-chip:hover { border-color: var(--primary-500); color: var(--pos-texto); }

        .pos-agregar {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 0.875rem;
            border: 1px solid var(--pos-linea);
            border-radius: 0.625rem;
            font-size: 0.875rem;
            font-weight: 600;
            background: var(--pos-superficie);
            white-space: nowrap;
        }

        .pos-agregar:hover { border-color: var(--primary-500); background: var(--pos-resalte); }
        .pos-agregar svg { width: 1rem; height: 1rem; }

        .pos-lista__vacio { display: grid; place-items: center; gap: 0.5rem; padding: 3rem 1.5rem; text-align: center; color: var(--pos-tenue); }
        .pos-lista__vacio svg { width: 2rem; height: 2rem; }
        .pos-mas { display: block; width: 100%; padding: 0.875rem; font-size: 0.875rem; font-weight: 600; color: var(--primary-600); }
        .dark .pos-mas { color: var(--primary-400); }
        .pos-mas:hover { background: var(--pos-resalte); }

        .pos-atajos { display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; padding: 0.625rem 1rem; border-top: 1px solid var(--pos-linea); font-size: 0.8125rem; color: var(--pos-tenue); }
        .pos-atajos span { display: inline-flex; align-items: center; gap: 0.375rem; }

        /* Carrito */
        .pos-carrito__cabeza { display: flex; align-items: center; gap: 0.5rem; padding: 0.875rem 1.125rem; border-bottom: 1px solid var(--pos-linea); }
        .pos-carrito__cabeza h2 { font-size: 1rem; font-weight: 700; }
        .pos-volver { display: none; align-items: center; gap: 0.25rem; margin: -0.375rem 0.25rem -0.375rem -0.5rem; padding: 0.375rem 0.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; color: var(--pos-tenue); }
        .pos-volver svg { width: 1.125rem; height: 1.125rem; }
        .pos-cuenta { min-width: 1.5rem; padding: 0.05rem 0.45rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-align: center; background: var(--pos-linea-suave); color: var(--pos-tenue); }
        .pos-vaciar { margin-left: auto; font-size: 0.8125rem; font-weight: 500; color: var(--pos-tenue); }
        .pos-vaciar:hover { color: var(--danger-600); }

        .pos-datos { padding: 0.875rem 1.125rem; border-bottom: 1px solid var(--pos-linea); }

        /* Datos de la venta cerrados: una sola línea con el documento, el
           precio y el cliente. Al tocarla se abren. */
        .pos-datos.es-cerrado { padding-block: 0.5rem; }
        .pos-datos.es-cerrado .pos-datos__campos,
        .pos-datos:not(.es-cerrado) .pos-resumen { display: none; }
        .pos-resumen { display: flex; align-items: center; gap: 0.625rem; width: calc(100% + 1rem); margin-inline: -0.5rem; padding: 0.375rem 0.5rem; border-radius: 0.625rem; text-align: left; }
        .pos-resumen:hover { background: var(--pos-linea-suave); }
        .pos-resumen:focus-visible { outline: 2px solid var(--primary-500); outline-offset: 0; }
        .pos-resumen__documento { flex: none; display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.3125rem 0.625rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; background: var(--pos-texto); color: var(--pos-superficie); }
        .pos-resumen__documento.es-factura { background: var(--primary-500); color: var(--gray-950); }
        .pos-resumen__documento svg { width: 1rem; height: 1rem; }
        .pos-resumen__texto { flex: 1; display: grid; min-width: 0; font-size: 0.875rem; line-height: 1.3; color: var(--pos-tenue); }
        .pos-resumen__texto > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pos-resumen__texto strong { font-weight: 600; color: var(--pos-texto); }
        .pos-resumen__rtn { font-size: 0.75rem; }
        .pos-resumen__mayoreo { font-weight: 600; color: var(--warning-700); }
        .dark .pos-resumen__mayoreo { color: var(--warning-400); }
        .pos-resumen__cambiar { flex: none; display: inline-flex; align-items: center; gap: 0.125rem; font-size: 0.8125rem; font-weight: 600; color: var(--primary-600); }
        .dark .pos-resumen__cambiar { color: var(--primary-400); }
        .pos-resumen__cambiar svg { width: 1rem; height: 1rem; }
        .pos-datos__listo { display: flex; align-items: center; gap: 0.125rem; margin: 0.625rem 0 0 auto; padding: 0.25rem 0.5rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; color: var(--pos-tenue); }
        .pos-datos__listo:hover { color: var(--pos-texto); background: var(--pos-linea-suave); }
        .pos-datos__listo svg { width: 1rem; height: 1rem; }

        .pos-lineas { flex: 1; min-height: 6rem; overflow-y: auto; overscroll-behavior: contain; }
        .pos-carrito__vacio { display: grid; place-items: center; height: 100%; min-height: 6rem; padding: 1.5rem; text-align: center; font-size: 0.875rem; color: var(--pos-tenue); }

        .pos-linea { padding: 0.625rem 1.125rem; border-bottom: 1px solid var(--pos-linea-suave); }

        /* La línea que se acaba de agregar (o que sumó uno) se ilumina un momento. */
        .pos-linea.es-nueva { animation: pos-nueva 1.2s ease-out; }
        @keyframes pos-nueva { from { background: var(--pos-resalte); } to { background: transparent; } }
        .pos-linea__arriba { display: flex; align-items: flex-start; gap: 0.5rem; }
        .pos-linea__nombre { flex: 1; font-weight: 600; line-height: 1.3; }
        .pos-linea__quitar { flex: none; padding: 0.125rem; border-radius: 0.375rem; color: var(--pos-tenue); }
        .pos-linea__quitar:hover { color: var(--danger-600); background: color-mix(in oklab, var(--danger-500) 12%, transparent); }
        .pos-linea__quitar svg { width: 1.125rem; height: 1.125rem; }
        .pos-linea__controles { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 0.625rem; margin-top: 0.375rem; }
        .pos-linea__unidad { font-size: 0.875rem; color: var(--pos-tenue); }
        .pos-linea__total { min-width: 5.5rem; text-align: right; font-weight: 700; }
        .pos-linea__abajo { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.875rem; margin-top: 0.375rem; font-size: 0.8125rem; color: var(--pos-tenue); }
        .pos-linea__aviso { color: var(--danger-600); font-weight: 500; }
        .dark .pos-linea__aviso { color: var(--danger-400); }

        .pos-cantidad { display: inline-flex; justify-self: start; align-items: stretch; height: 2.25rem; border: 1px solid var(--pos-linea); border-radius: 0.625rem; overflow: hidden; background: var(--pos-superficie); }
        .pos-cantidad button { display: grid; place-items: center; width: 2.125rem; color: var(--pos-tenue); }
        .pos-cantidad button:hover { color: var(--pos-texto); background: var(--pos-linea-suave); }
        .pos-cantidad button svg { width: 1rem; height: 1rem; }
        .pos-cantidad input { width: 3.25rem; border: 0; border-inline: 1px solid var(--pos-linea); background: transparent; text-align: center; font-weight: 600; color: var(--pos-texto); padding: 0; box-shadow: none; -moz-appearance: textfield; }
        .pos-cantidad input:focus { outline: 2px solid var(--primary-500); outline-offset: -2px; }
        .pos-cantidad input::-webkit-inner-spin-button, .pos-cantidad input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

        .pos-descuento { display: inline-flex; align-items: center; gap: 0.375rem; }
        .pos-descuento input { width: 4.25rem; height: 1.75rem; padding: 0 0.375rem; border: 1px solid var(--pos-linea); border-radius: 0.375rem; background: var(--pos-superficie); text-align: right; color: var(--pos-texto); font-size: 0.8125rem; box-shadow: none; -moz-appearance: textfield; }
        .pos-descuento input:focus { outline: 2px solid var(--primary-500); outline-offset: -1px; }
        .pos-descuento input::-webkit-inner-spin-button, .pos-descuento input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

        /* % o L: dos botones pegados; el activo, relleno. */
        .pos-unidad { display: inline-flex; border: 1px solid var(--pos-linea); border-radius: 0.375rem; overflow: hidden; }
        .pos-unidad button { min-width: 1.75rem; height: 1.625rem; padding: 0 0.375rem; font-size: 0.8125rem; font-weight: 700; color: var(--pos-tenue); background: var(--pos-superficie); }
        .pos-unidad button + button { border-left: 1px solid var(--pos-linea); }
        .pos-unidad button:hover { color: var(--pos-texto); }
        .pos-unidad button[aria-pressed="true"] { background: var(--pos-texto); color: var(--pos-superficie); }
        .pos-unidad button:focus-visible { outline: 2px solid var(--primary-500); outline-offset: -2px; }

        .pos-global { display: grid; gap: 0.375rem; margin-bottom: 0.875rem; padding-bottom: 0.875rem; border-bottom: 1px dashed var(--pos-linea); font-size: 0.875rem; }
        .pos-global__cabeza, .pos-global__fila { display: flex; align-items: center; gap: 0.75rem; }
        .pos-global__cabeza { justify-content: space-between; color: var(--pos-tenue); }
        .pos-global__monto { margin-left: auto; font-weight: 600; }
        .pos-global__quitar { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 0.375rem; color: var(--pos-tenue); }
        .pos-global__quitar:hover { color: var(--danger-600); }
        .pos-global__quitar svg { width: 1rem; height: 1rem; }
        .pos-global__aviso { flex-basis: 100%; color: var(--danger-600); font-size: 0.8125rem; font-weight: 500; }
        .dark .pos-global__aviso { color: var(--danger-400); }

        .pos-carrito__pie { padding: 1rem 1.125rem 1.125rem; border-top: 1px solid var(--pos-linea); background: var(--pos-fondo); }

        .pos-total { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; }
        .pos-total > div { min-width: 0; }
        .pos-total__accion { display: block; margin-top: 0.25rem; font-size: 0.8125rem; font-weight: 600; color: var(--primary-600); }
        .dark .pos-total__accion { color: var(--primary-400); }
        .pos-total__etiqueta { font-size: 0.875rem; color: var(--pos-tenue); }
        .pos-total__detalle { display: block; margin-top: 0.125rem; font-size: 0.8125rem; color: var(--pos-tenue); }
        .pos-total__monto { font-size: 2.5rem; font-weight: 800; line-height: 1; letter-spacing: -0.03em; white-space: nowrap; }

        .pos-pagos { margin-top: 0.875rem; }
        .pos-pagos__cabeza { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; }
        .pos-pagos__otro { font-size: 0.8125rem; font-weight: 600; color: var(--primary-600); }
        .dark .pos-pagos__otro { color: var(--primary-400); }

        /* Forma de pago y monto en una fila; la referencia, debajo. */
        .pos-pago { display: grid; grid-template-columns: 8.5rem minmax(0, 1fr) auto; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; }
        .pos-pago__referencia { grid-column: 1 / -1; grid-row: 2; }
        .pos-pago__quitar { grid-column: 3; grid-row: 1; display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 0.375rem; color: var(--pos-tenue); }
        .pos-pago__quitar:hover { color: var(--danger-600); }
        .pos-pago__quitar svg { width: 1rem; height: 1rem; }

        .pos-billetes { display: flex; flex-wrap: wrap; align-items: center; gap: 0.375rem; margin-bottom: 0.625rem; font-size: 0.8125rem; color: var(--pos-tenue); }
        .pos-billete { padding: 0.25rem 0.625rem; border: 1px solid var(--pos-linea); border-radius: 0.5rem; font-weight: 600; color: var(--pos-texto); background: var(--pos-superficie); }
        .pos-billete:hover { border-color: var(--primary-500); }

        .pos-saldo { display: flex; justify-content: space-between; gap: 0.75rem; font-size: 0.875rem; color: var(--pos-tenue); }
        .pos-saldo strong { color: var(--pos-texto); font-weight: 600; }
        .pos-saldo .es-falta strong { color: var(--danger-600); }
        .pos-saldo .es-cambio strong { color: var(--success-600); font-size: 1.125rem; }
        .dark .pos-saldo .es-falta strong { color: var(--danger-400); }
        .dark .pos-saldo .es-cambio strong { color: var(--success-400); }

        /* La tecla de cobrar: lo único que grita en la pantalla */
        .pos-cobrar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
            margin-top: 0.875rem;
            padding: 0.875rem 1.125rem;
            border-radius: 0.875rem;
            background: var(--primary-500);
            color: var(--gray-950);
            font-size: 1.125rem;
            font-weight: 700;
            box-shadow: inset 0 -3px 0 color-mix(in oklab, black 22%, transparent);
            transition: background-color 120ms, transform 60ms;
        }

        .pos-cobrar:hover { background: var(--primary-400); }
        .pos-cobrar:active { transform: translateY(1px); box-shadow: inset 0 -1px 0 color-mix(in oklab, black 22%, transparent); }
        .pos-cobrar:focus-visible { outline: 3px solid var(--primary-300); outline-offset: 2px; }
        .pos-cobrar:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }
        .pos-cobrar__monto { margin-left: auto; }
        .pos-cobrar .pos-tecla { color: var(--gray-900); border-color: color-mix(in oklab, black 25%, transparent); background: color-mix(in oklab, white 35%, transparent); }

        /* Barra de abajo (solo teléfono y tablet) */
        .pos-barra {
            display: none;
            position: fixed;
            inset: auto 0 0 0;
            z-index: 20;
            padding: 0.625rem 1rem calc(0.625rem + env(safe-area-inset-bottom));
            border-top: 1px solid var(--pos-linea);
            background: color-mix(in oklab, var(--pos-superficie) 94%, transparent);
            backdrop-filter: blur(8px);
        }

        .pos-barra__boton {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
            padding: 0.875rem 1rem;
            border-radius: 0.875rem;
            background: var(--primary-500);
            color: var(--gray-950);
            font-size: 1rem;
            font-weight: 700;
            box-shadow: inset 0 -3px 0 color-mix(in oklab, black 22%, transparent);
        }

        .pos-barra__boton:disabled { opacity: 0.45; cursor: not-allowed; }
        .pos-barra__cuenta { min-width: 1.75rem; padding: 0.1rem 0.5rem; border-radius: 999px; text-align: center; background: color-mix(in oklab, black 14%, transparent); }
        .pos-barra__total { margin-left: auto; }

        /* Sin turno */
        .pos-sin-turno { display: grid; place-items: center; gap: 0.75rem; max-width: 28rem; margin: 6vh auto 0; padding: 2.5rem 2rem; text-align: center; background: var(--pos-superficie); border: 1px solid var(--pos-linea); border-radius: 1rem; }
        .pos-sin-turno svg { width: 2.75rem; height: 2.75rem; color: var(--pos-tenue); }
        .pos-sin-turno h2 { font-size: 1.25rem; font-weight: 700; }
        .pos-sin-turno p { color: var(--pos-tenue); }

        /* Teléfono y tablet: una zona a la vez, la página corre completa y
           la barra de abajo lleva al carrito o cobra. En el carrito, los
           productos primero y los datos del documento después. */
        @media (max-width: 1100px) {
            .pos { padding-bottom: 5.5rem; }
            .pos-zonas { display: block; height: auto; }
            .pos[data-vista="catalogo"] .pos-carrito,
            .pos[data-vista="carrito"] .pos-catalogo { display: none; }
            .pos-panel { overflow: visible; }
            .pos-lista, .pos-lineas { flex: none; min-height: 0; overflow: visible; }
            .pos-catalogo__cabeza { position: sticky; top: 4rem; z-index: 10; border-radius: 1rem 1rem 0 0; background: var(--pos-superficie); }
            .pos-carrito__pie { border-radius: 0 0 1rem 1rem; }
            .pos-carrito__vacio { min-height: 8rem; }
            .pos-atajos { display: none; }
            .pos-volver { display: inline-flex; }
            .pos-barra { display: block; }
            .pos-carrito .pos-cobrar { display: none; }
            .pos-lineas { order: 1; }
            .pos-datos { order: 2; border-bottom: 0; }
            .pos-carrito__pie { order: 3; }

            .pos-buscador__camara { width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: var(--pos-texto); color: var(--pos-superficie); }
            .pos-buscador__camara:hover { background: var(--pos-texto); color: var(--pos-superficie); }

            /* 16 px o más: si no, el iPhone hace zoom al escribir. */
            .pos-buscador input, .pos-cantidad input, .pos-descuento input { font-size: 16px; }
            .pos-cantidad { height: 2.75rem; }
            .pos-cantidad button { width: 2.75rem; }
            .pos-descuento input { height: 2.25rem; width: 5rem; }
            .pos-unidad button { min-width: 2.25rem; height: 2.125rem; }
        }

        @media (max-width: 640px) {
            .pos-catalogo__cabeza { grid-template-columns: 1fr; padding: 0.75rem; }
            .pos-fila { grid-template-columns: minmax(0, 1fr) auto; gap: 0.375rem 0.75rem; padding: 0.75rem 0.875rem; }
            .pos-fila > :first-child { grid-row: span 2; }
            .pos-fila__precio { grid-column: 2; }
            .pos-agregar { grid-column: 2; justify-self: end; }
            .pos-linea, .pos-carrito__cabeza, .pos-datos { padding-inline: 0.875rem; }
            .pos-carrito__pie { padding-inline: 0.875rem; }
            .pos-linea__controles { grid-template-columns: minmax(0, 1fr) auto; }
            .pos-linea__controles > :first-child { grid-column: 1 / -1; }
            .pos-total__monto { font-size: 2.125rem; }
            .pos-franja__turno { font-size: 0.8125rem; }
        }

        /* Pantallas táctiles: sin atajos de teclado a la vista. */
        @media (pointer: coarse) {
            .pos-tecla, .pos-atajos { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .pos-cobrar { transition: none; }
            .pos-linea.es-nueva { animation: none; }
        }
    </style>

    <div
        class="pos"
        x-data="{
            activo: -1,
            vista: 'catalogo',
            tactil: window.matchMedia('(pointer: coarse)').matches,
            filas() {
                return this.$refs.lista ? [...this.$refs.lista.querySelectorAll('[data-fila]')] : [];
            },
            mover(paso) {
                const filas = this.filas();

                if (filas.length === 0) {
                    return;
                }

                this.activo = Math.max(0, Math.min(filas.length - 1, this.activo + paso));
                filas[this.activo].scrollIntoView({ block: 'nearest' });
            },
            enter() {
                const fila = this.filas()[this.activo];

                if (this.activo >= 0 && fila) {
                    fila.querySelector('[data-agregar]')?.click();

                    return;
                }

                this.$wire.agregarPorCodigo();
            },
            // En pantallas táctiles no: abriría el teclado en cada toque.
            enfocar() {
                this.activo = -1;

                if (! this.tactil) {
                    setTimeout(() => this.$refs.busqueda?.focus(), 0);
                }
            },
            mostrar(vista) {
                this.vista = vista;
                window.scrollTo({ top: 0 });
            },
            escanear() {
                this.$dispatch('abrir-escaner', { origen: 'pos', continuo: true, titulo: 'Escanear productos' });
            },
            async leido(detalle) {
                if (detalle.origen !== 'pos') {
                    return;
                }

                const resultado = await this.$wire.escanear(detalle.codigo);

                window.dispatchEvent(new CustomEvent('escaner-aviso', { detail: resultado }));
            },
            // La línea agregada queda a la vista en la lista del carrito.
            resaltar(clave) {
                requestAnimationFrame(() => {
                    const linea = this.$root.querySelector(`[data-linea='${clave}']`);

                    if (! linea || linea.offsetParent === null) {
                        return;
                    }

                    linea.scrollIntoView({ block: 'nearest' });
                    linea.classList.remove('es-nueva');
                    void linea.offsetWidth;
                    linea.classList.add('es-nueva');
                });
            },
        }"
        x-bind:data-vista="vista"
        x-init="enfocar()"
        x-on:keydown.f2.window.prevent="mostrar('catalogo'); enfocar()"
        x-on:keydown.f9.window.prevent="$wire.cobrar()"
        x-on:enfocar-busqueda.window="enfocar()"
        x-on:codigo-escaneado.window="leido($event.detail)"
        x-on:linea-agregada.window="resaltar($event.detail.clave)"
        x-on:escaner-cerrado.window="$event.detail.origen === 'pos' && enfocar()"
        x-on:imprimir-ticket.window="
            vista = 'catalogo';
            document.getElementById('ticket-frame')?.remove();
            const marco = document.createElement('iframe');
            marco.id = 'ticket-frame';
            marco.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
            marco.src = $event.detail.url;
            document.body.appendChild(marco);
        "
    >
        @if ($turno === null)
            <div class="pos-sin-turno">
                <x-filament::icon icon="heroicon-o-lock-closed" />
                <h2>No hay un turno de caja abierto</h2>
                <p>Abre un turno con el efectivo que hay en la caja para empezar a vender.</p>
                {{ $this->abrirTurnoAction }}
            </div>
        @else
            @php
                $catalogo = $this->catalogo;
                $categorias = $this->categorias;
                $carrito = $this->carrito;
                $totales = $this->totales;
                $cobro = $this->cobro();
                $pagos = $this->pagosResueltos();
                $sugerencias = $this->sugerenciasEfectivo();
                $descuentoMaximo = $this->descuentoMaximo();
                $descuentos = $this->resumenDescuentos();
                $articulos = count($carrito);
                $puedeDescontar = $descuentoMaximo > 0 && $articulos > 0;
                $mostrarGlobal = $puedeDescontar && ($descuentoGlobalAbierto || $descuentos['global'] > 0 || filled($this->venta['descuento_global'] ?? null));
                $soloEfectivo = count($pagos) === 1 && $pagos[0]['metodo'] === 'efectivo';
                // Pagado y cambio solo dicen algo si se tecleó un monto o hay varios pagos.
                $mostrarSaldo = $totales['total'] > 0 && (count($pagos) > 1 || collect($pagos)->contains('tecleado', true));
                $resumenDatos = $this->resumenDatos();
            @endphp

            {{-- ── Turno ─────────────────────────────────────────────── --}}
            <div class="pos-franja">
                <div class="pos-franja__turno">
                    <span class="pos-franja__punto" aria-hidden="true"></span>
                    <span><strong>{{ $turno->caja->nombre }}</strong>, abierta desde las {{ $turno->abierto_at->format('H:i') }} por {{ $turno->cajero->name }}</span>
                </div>
                <div class="pos-franja__acciones">
                    {{ $this->movimientoCajaAction }}
                    {{ $this->cerrarTurnoAction }}
                </div>
            </div>

            <div class="pos-zonas">
                {{-- ── Catálogo ──────────────────────────────────────── --}}
                <section class="pos-panel pos-catalogo" aria-label="Catálogo de productos">
                    <div class="pos-catalogo__cabeza">
                        <div class="pos-buscador" x-on:click.self="$refs.busqueda.focus()">
                            <x-filament::icon icon="heroicon-o-magnifying-glass" class="pos-buscador__icono" />
                            <input
                                type="search"
                                x-ref="busqueda"
                                wire:model.live.debounce.250ms="busqueda"
                                x-on:input="activo = -1"
                                x-on:keydown.arrow-down.prevent="mover(1)"
                                x-on:keydown.arrow-up.prevent="mover(-1)"
                                x-on:keydown.enter.prevent="enter()"
                                x-on:keydown.escape.prevent="$wire.set('busqueda', ''); activo = -1"
                                placeholder="Nombre o código del producto"
                                aria-label="Buscar producto"
                                autocomplete="off"
                                enterkeyhint="search"
                            />
                            <span class="pos-tecla">F2</span>
                            <button
                                type="button"
                                class="pos-buscador__camara"
                                x-on:click="escanear()"
                                aria-label="Escanear con la cámara"
                                title="Escanear con la cámara"
                            >
                                <x-filament::icon icon="heroicon-o-camera" />
                            </button>
                        </div>

                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="categoriaId" aria-label="Categoría">
                                <option value="">Todas las categorías</option>
                                @foreach ($categorias as $id => $nombre)
                                    <option value="{{ $id }}">{{ $nombre }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    <div class="pos-lista" x-ref="lista">
                        @forelse ($catalogo['productos'] as $producto)
                            <div
                                class="pos-fila"
                                data-fila
                                wire:key="catalogo-{{ $producto['producto_id'] }}"
                                x-bind:class="{ 'es-activa': activo === {{ $loop->index }} }"
                            >
                                <div>
                                    <div class="pos-fila__nombre">{{ $producto['nombre'] }}</div>
                                    <div class="pos-fila__meta">
                                        @if ($producto['codigo'])
                                            <span>{{ $producto['codigo'] }}</span>
                                        @endif
                                        @if ($producto['marca'])
                                            <span>{{ $producto['marca'] }}</span>
                                        @endif
                                        <span @class(['pos-existencia', 'pos-existencia--' . $producto['estado']])>
                                            @switch($producto['estado'])
                                                @case('servicio') Servicio @break
                                                @case('agotado') Sin existencia @break
                                                @case('bajo') Quedan {{ $producto['existencia'] }} @break
                                                @default {{ $producto['existencia'] }} en existencia
                                            @endswitch
                                        </span>
                                    </div>
                                    @if (count($producto['presentaciones']) > 0)
                                        <div class="pos-fila__presentaciones">
                                            @foreach ($producto['presentaciones'] as $presentacion)
                                                <button
                                                    type="button"
                                                    class="pos-chip"
                                                    x-on:mousedown.prevent
                                                    wire:click="agregar({{ $producto['producto_id'] }}, {{ $presentacion['id'] }})"
                                                    title="Agregar {{ $presentacion['etiqueta'] }}"
                                                >
                                                    {{ $presentacion['etiqueta'] }}
                                                    <strong class="pos-cifra">{{ lempiras($presentacion['precio']) }}</strong>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="pos-fila__precio pos-cifra">
                                    {{ lempiras($producto['precio']) }}
                                    <small>por {{ mb_strtolower($producto['unidad']) }}</small>
                                </div>

                                <button
                                    type="button"
                                    class="pos-agregar"
                                    data-agregar
                                    x-on:mousedown.prevent
                                    wire:click="agregar({{ $producto['producto_id'] }})"
                                >
                                    <x-filament::icon icon="heroicon-m-plus" />
                                    Agregar
                                </button>
                            </div>
                        @empty
                            <div class="pos-lista__vacio">
                                <x-filament::icon icon="heroicon-o-magnifying-glass" />
                                @if (trim($busqueda) !== '')
                                    <p>Ningún producto coincide con «{{ trim($busqueda) }}». Prueba con otra palabra o con el código.</p>
                                @else
                                    <p>No hay productos activos en esta categoría.</p>
                                @endif
                            </div>
                        @endforelse

                        @if ($catalogo['hay_mas'])
                            <button type="button" class="pos-mas" wire:click="mostrarMas">Mostrar más productos</button>
                        @endif
                    </div>

                    <div class="pos-atajos" aria-label="Atajos de teclado">
                        <span><span class="pos-tecla">F2</span> Buscar</span>
                        <span><span class="pos-tecla">↑</span><span class="pos-tecla">↓</span> Elegir</span>
                        <span><span class="pos-tecla">Enter</span> Agregar</span>
                        <span><span class="pos-tecla">Esc</span> Limpiar búsqueda</span>
                        <span><span class="pos-tecla">F9</span> Cobrar</span>
                    </div>
                </section>

                {{-- ── Carrito ───────────────────────────────────────── --}}
                <aside class="pos-panel pos-carrito" aria-label="Carrito">
                    <div class="pos-carrito__cabeza">
                        <button type="button" class="pos-volver" x-on:click="mostrar('catalogo')">
                            <x-filament::icon icon="heroicon-m-arrow-left" />
                            Productos
                        </button>
                        <h2>Carrito</h2>
                        <span class="pos-cuenta pos-cifra">{{ $articulos }}</span>
                        @if ($articulos > 0)
                            <button type="button" class="pos-vaciar" wire:click="vaciar">Vaciar</button>
                        @endif
                    </div>

                    <div @class(['pos-datos', 'es-cerrado' => ! $datosAbiertos])>
                        <button
                            type="button"
                            class="pos-resumen"
                            x-on:click="$el.parentElement.classList.remove('es-cerrado'); $wire.$set('datosAbiertos', true)"
                            title="Cambiar documento, precio o cliente"
                        >
                            <span @class(['pos-resumen__documento', 'es-factura' => $resumenDatos['factura']])>
                                <x-filament::icon :icon="$resumenDatos['factura'] ? 'heroicon-m-document-check' : 'heroicon-m-document-text'" />
                                {{ $resumenDatos['factura'] ? 'Factura' : 'Recibo' }}
                            </span>
                            <span class="pos-resumen__texto">
                                <span>
                                    @if ($resumenDatos['mayoreo'])
                                        <span class="pos-resumen__mayoreo">Mayoreo</span>,
                                    @endif
                                    <strong>{{ $resumenDatos['cliente'] }}</strong>
                                </span>
                                @if ($resumenDatos['factura'] && $resumenDatos['rtn'] !== null)
                                    <span class="pos-resumen__rtn pos-cifra">RTN {{ $resumenDatos['rtn'] }}</span>
                                @endif
                            </span>
                            <span class="pos-resumen__cambiar">
                                Cambiar
                                <x-filament::icon icon="heroicon-m-chevron-down" />
                            </span>
                        </button>

                        <div class="pos-datos__campos">
                            {{ $this->form }}

                            @if ($articulos > 0)
                                <button
                                    type="button"
                                    class="pos-datos__listo"
                                    x-on:click="$el.closest('.pos-datos').classList.add('es-cerrado'); $wire.$set('datosAbiertos', false)"
                                >
                                    Listo
                                    <x-filament::icon icon="heroicon-m-chevron-up" />
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="pos-lineas">
                        @forelse ($carrito as $linea)
                            <div class="pos-linea" wire:key="linea-{{ $linea['clave'] }}" data-linea="{{ $linea['clave'] }}">
                                <div class="pos-linea__arriba">
                                    <div class="pos-linea__nombre">{{ $linea['producto']->nombre }}</div>
                                    <button
                                        type="button"
                                        class="pos-linea__quitar"
                                        wire:click="quitar('{{ $linea['clave'] }}')"
                                        aria-label="Quitar {{ $linea['producto']->nombre }}"
                                    >
                                        <x-filament::icon icon="heroicon-m-x-mark" />
                                    </button>
                                </div>

                                <div class="pos-linea__controles">
                                    @if (count($linea['opciones']) > 0)
                                        <x-filament::input.wrapper>
                                            <x-filament::input.select wire:model.live="lineas.{{ $linea['clave'] }}.presentacion_id" aria-label="Presentación">
                                                <option value="">{{ $linea['unidad_base'] }}</option>
                                                @foreach ($linea['opciones'] as $presentacionId => $etiqueta)
                                                    <option value="{{ $presentacionId }}">{{ $etiqueta }}</option>
                                                @endforeach
                                            </x-filament::input.select>
                                        </x-filament::input.wrapper>
                                    @else
                                        <span class="pos-linea__unidad">{{ $linea['unidad_base'] }}</span>
                                    @endif

                                    <div class="pos-cantidad">
                                        <button type="button" wire:click="sumar('{{ $linea['clave'] }}', -1)" aria-label="Uno menos">
                                            <x-filament::icon icon="heroicon-m-minus" />
                                        </button>
                                        <input
                                            type="number"
                                            step="any"
                                            min="0"
                                            inputmode="decimal"
                                            class="pos-cifra"
                                            aria-label="Cantidad"
                                            wire:model.live.debounce.500ms="lineas.{{ $linea['clave'] }}.cantidad"
                                        />
                                        <button type="button" wire:click="sumar('{{ $linea['clave'] }}', 1)" aria-label="Uno más">
                                            <x-filament::icon icon="heroicon-m-plus" />
                                        </button>
                                    </div>

                                    <div class="pos-linea__total pos-cifra">{{ lempiras($linea['total_linea']) }}</div>
                                </div>

                                <div class="pos-linea__abajo">
                                    <span class="pos-cifra">{{ lempiras($linea['precio']) }} c/u</span>
                                    @if ($linea['excede'])
                                        <span class="pos-linea__aviso">Solo hay {{ cantidadFormato($linea['existencia']) }} {{ $linea['producto']->unidadMedida->abreviatura }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="pos-carrito__vacio">
                                <p>Agrega productos desde el catálogo o escanea su código.</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="pos-carrito__pie">
                        @if ($mostrarGlobal)
                            <div class="pos-global">
                                <div class="pos-global__cabeza">
                                    <span>Descuento a toda la venta</span>
                                    <button type="button" class="pos-global__quitar" wire:click="quitarDescuentoGlobal" aria-label="Quitar el descuento a toda la venta">
                                        <x-filament::icon icon="heroicon-m-x-mark" />
                                    </button>
                                </div>
                                <div class="pos-global__fila">
                                    <span class="pos-descuento">
                                        <input
                                            type="number"
                                            step="any"
                                            min="0"
                                            inputmode="decimal"
                                            placeholder="0"
                                            class="pos-cifra"
                                            aria-label="Descuento a toda la venta"
                                            wire:model.live.debounce.500ms="venta.descuento_global"
                                            x-on:enfocar-descuento-global.window="$nextTick(() => $el.focus())"
                                        />
                                        <span class="pos-unidad" role="group" aria-label="Descuento en">
                                            <button
                                                type="button"
                                                aria-pressed="{{ $descuentos['tipo'] === \App\Enums\TipoDescuento::Porcentaje ? 'true' : 'false' }}"
                                                wire:click="tipoDescuentoGlobal('porcentaje')"
                                                title="Descuento en porcentaje"
                                            >%</button>
                                            <button
                                                type="button"
                                                aria-pressed="{{ $descuentos['tipo'] === \App\Enums\TipoDescuento::Monto ? 'true' : 'false' }}"
                                                wire:click="tipoDescuentoGlobal('monto')"
                                                title="Descuento en lempiras"
                                            >L</button>
                                        </span>
                                    </span>
                                    <span class="pos-global__monto pos-cifra">
                                        @if ($descuentos['global'] > 0)
                                            − {{ lempiras($descuentos['global']) }}
                                        @endif
                                    </span>
                                </div>
                                @if ($descuentos['excede'])
                                    <span class="pos-global__aviso">El descuento es de {{ cantidadFormato($descuentos['porcentaje'], 2) }} % y tu máximo es {{ cantidadFormato($descuentoMaximo, 2) }} %.</span>
                                @endif
                            </div>
                        @endif

                        <div class="pos-total">
                            <div>
                                <span class="pos-total__etiqueta">Total</span>
                                <span class="pos-total__detalle pos-cifra">
                                    ISV incluido {{ lempiras($totales['isv_15'] + $totales['isv_18']) }}@if ($totales['descuento'] > 0), descuento {{ lempiras($totales['descuento']) }}@endif
                                </span>
                                @if ($puedeDescontar && ! $mostrarGlobal)
                                    <button type="button" class="pos-total__accion" wire:click="abrirDescuentoGlobal">+ Descuento</button>
                                @endif
                            </div>
                            <span class="pos-total__monto pos-cifra">{{ lempiras($totales['total']) }}</span>
                        </div>

                        <div class="pos-pagos">
                            <div class="pos-pagos__cabeza">
                                <span>Pago</span>
                                <button type="button" class="pos-pagos__otro" wire:click="agregarPago">+ Otro pago</button>
                            </div>

                            @foreach ($pagos as $indice => $pago)
                                <div class="pos-pago" wire:key="pago-{{ $indice }}">
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select wire:model.live="venta.pagos.{{ $indice }}.metodo" aria-label="Forma de pago">
                                            @foreach (\App\Filament\Pages\PuntoDeVenta::METODOS as $valor => $etiqueta)
                                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>

                                    <x-filament::input.wrapper prefix="L.">
                                        <x-filament::input
                                            type="number"
                                            step="any"
                                            min="0"
                                            inputmode="decimal"
                                            class="pos-cifra"
                                            aria-label="Monto"
                                            :placeholder="$pago['tecleado'] ? null : ($soloEfectivo ? 'Exacto' : number_format($pago['monto'], 2))"
                                            wire:model.live.debounce.500ms="venta.pagos.{{ $indice }}.monto"
                                        />
                                    </x-filament::input.wrapper>

                                    @if (count($pagos) > 1)
                                        <button type="button" class="pos-pago__quitar" wire:click="quitarPago({{ $indice }})" aria-label="Quitar este pago">
                                            <x-filament::icon icon="heroicon-m-x-mark" />
                                        </button>
                                    @endif

                                    @if ($pago['metodo'] !== 'efectivo')
                                        <x-filament::input.wrapper class="pos-pago__referencia">
                                            <x-filament::input
                                                type="text"
                                                :placeholder="$pago['metodo'] === 'tarjeta' ? 'N.º de voucher (opcional)' : 'N.º de transferencia (opcional)'"
                                                wire:model.blur="venta.pagos.{{ $indice }}.referencia"
                                            />
                                        </x-filament::input.wrapper>
                                    @endif
                                </div>
                            @endforeach

                            @if (count($sugerencias) > 0)
                                <div class="pos-billetes">
                                    <span>Paga con</span>
                                    @foreach ($sugerencias as $billete)
                                        <button type="button" class="pos-billete pos-cifra" wire:click="fijarBillete({{ $billete }})">
                                            {{ lempiras($billete, 0) }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if ($mostrarSaldo)
                                <div class="pos-saldo">
                                    <span>Pagado <strong class="pos-cifra">{{ lempiras($cobro['pagado']) }}</strong></span>
                                    @if ($cobro['restante'] > 0)
                                        <span class="es-falta">Falta <strong class="pos-cifra">{{ lempiras($cobro['restante']) }}</strong></span>
                                    @else
                                        <span class="es-cambio">Cambio <strong class="pos-cifra">{{ lempiras($cobro['cambio']) }}</strong></span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <button
                            type="button"
                            class="pos-cobrar"
                            wire:click="cobrar"
                            wire:loading.attr="disabled"
                            wire:target="cobrar"
                            @disabled($articulos === 0)
                        >
                            <span wire:loading.remove wire:target="cobrar">Cobrar</span>
                            <span wire:loading wire:target="cobrar">Registrando…</span>
                            <span class="pos-cobrar__monto pos-cifra">{{ lempiras($totales['total']) }}</span>
                            <span class="pos-tecla">F9</span>
                        </button>
                    </div>
                </aside>
            </div>

            {{-- ── Barra de abajo (teléfono y tablet): ver carrito o cobrar ── --}}
            <div class="pos-barra">
                <button type="button" class="pos-barra__boton" x-show="vista === 'catalogo'" x-on:click="mostrar('carrito')">
                    <span class="pos-barra__cuenta pos-cifra">{{ $articulos }}</span>
                    Ver carrito
                    <span class="pos-barra__total pos-cifra">{{ lempiras($totales['total']) }}</span>
                </button>
                <button
                    type="button"
                    class="pos-barra__boton"
                    x-show="vista === 'carrito'"
                    x-cloak
                    wire:click="cobrar"
                    wire:loading.attr="disabled"
                    wire:target="cobrar"
                    @disabled($articulos === 0)
                >
                    <span wire:loading.remove wire:target="cobrar">Cobrar</span>
                    <span wire:loading wire:target="cobrar">Registrando…</span>
                    <span class="pos-barra__total pos-cifra">{{ lempiras($totales['total']) }}</span>
                </button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
