{{--
    Escáner de códigos de barras con la cámara (teléfono, tablet o webcam).

    Uno solo para todo el panel (render hook BODY_END). Se abre con el evento
    de navegador `abrir-escaner`:

      { campo: 'id-del-input' }            llena ese campo y se cierra
      { origen: 'pos', continuo: true }    lee uno tras otro sin cerrarse

    Cada lectura sale como `codigo-escaneado` ({ origen, codigo }). Quien la
    procesa puede responder con `escaner-aviso` ({ ok, texto }) para mostrarlo
    sobre la cámara. Al cerrarse avisa con `escaner-cerrado` ({ origen }).

    La librería (html5-qrcode 2.3.8, Apache 2.0) se sirve desde
    public/vendor/html5-qrcode y solo se descarga la primera vez que se abre.
    La cámara del navegador solo funciona en https:// (o en localhost).
--}}
@php
    $libreria = asset('vendor/html5-qrcode/html5-qrcode.min.js').'?v=2.3.8';
@endphp

<style>
    .esc[x-cloak] { display: none !important; }

    .esc {
        position: fixed;
        inset: 0;
        z-index: 70;
        display: grid;
        place-items: center;
        padding: 1rem;
        background: rgb(3 7 18 / 0.72);
    }

    .esc__caja {
        display: flex;
        flex-direction: column;
        width: min(100%, 34rem);
        max-height: calc(100dvh - 2rem);
        overflow: hidden;
        border-radius: 1rem;
        background: #fff;
        color: var(--gray-950);
        box-shadow: 0 24px 48px -12px rgb(0 0 0 / 0.5);
    }

    .dark .esc__caja { background: var(--gray-900); color: var(--gray-50); }

    .esc__cabeza { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; }
    .esc__cabeza h2 { font-size: 1rem; font-weight: 700; line-height: 1.3; }
    .esc__cabeza p { margin-top: 0.125rem; font-size: 0.8125rem; color: var(--gray-500); }
    .dark .esc__cabeza p { color: var(--gray-400); }

    .esc__listo {
        flex: none;
        margin-left: auto;
        padding: 0.5rem 1.125rem;
        border-radius: 0.625rem;
        font-weight: 700;
        background: var(--primary-500);
        color: var(--gray-950);
    }

    .esc__listo:focus-visible { outline: 3px solid var(--primary-300); outline-offset: 2px; }

    .esc__visor { position: relative; display: grid; place-items: center; min-height: 16rem; background: #000; overflow: hidden; }
    .esc__lector { width: 100%; }
    .esc__lector video { display: block; }

    .esc__estado {
        position: absolute;
        inset: 0;
        display: grid;
        place-content: center;
        justify-items: center;
        gap: 0.75rem;
        padding: 1.5rem;
        text-align: center;
        color: #fff;
        font-size: 0.9375rem;
    }

    .esc__estado svg { width: 2.25rem; height: 2.25rem; opacity: 0.8; }
    .esc__girando { animation: esc-girar 0.9s linear infinite; }
    .esc__reintentar { padding: 0.5rem 1rem; border: 1px solid rgb(255 255 255 / 0.4); border-radius: 0.625rem; font-weight: 600; color: #fff; }

    .esc__pie { display: flex; align-items: center; gap: 0.75rem; min-height: 3.75rem; padding: 0.75rem 1rem; }
    .esc__aviso { display: flex; flex: 1; align-items: center; gap: 0.5rem; font-size: 0.9375rem; font-weight: 600; line-height: 1.3; }
    .esc__aviso svg { width: 1.375rem; height: 1.375rem; flex: none; }
    .esc__aviso.es-ok { color: var(--success-600); }
    .esc__aviso.es-error { color: var(--danger-600); }
    .dark .esc__aviso.es-ok { color: var(--success-400); }
    .dark .esc__aviso.es-error { color: var(--danger-400); }
    .esc__pista { flex: 1; font-size: 0.875rem; color: var(--gray-500); }

    .esc__linterna {
        display: grid;
        place-items: center;
        flex: none;
        width: 2.75rem;
        height: 2.75rem;
        border: 1px solid var(--gray-200);
        border-radius: 999px;
        color: var(--gray-600);
    }

    .dark .esc__linterna { border-color: rgb(255 255 255 / 0.15); color: var(--gray-300); }
    .esc__linterna svg { width: 1.25rem; height: 1.25rem; }
    .esc__linterna[aria-pressed="true"] { border-color: var(--primary-500); background: var(--primary-500); color: var(--gray-950); }

    /* En el teléfono, la cámara a pantalla completa. */
    @media (max-width: 640px) {
        .esc { padding: 0; place-items: stretch; background: #000; }

        .esc__caja,
        .dark .esc__caja {
            width: 100%;
            max-height: none;
            height: 100dvh;
            border-radius: 0;
            background: #000;
            color: #fff;
            box-shadow: none;
        }

        .esc__cabeza { padding-top: calc(0.875rem + env(safe-area-inset-top)); }
        .esc__cabeza p, .esc__pista { color: rgb(255 255 255 / 0.65); }
        .esc__visor { flex: 1; min-height: 0; }
        .esc__pie { padding-bottom: calc(0.75rem + env(safe-area-inset-bottom)); }
        .esc__aviso.es-ok { color: var(--success-400); }
        .esc__aviso.es-error { color: var(--danger-400); }
        .esc__linterna { border-color: rgb(255 255 255 / 0.25); color: #fff; }
    }

    @keyframes esc-girar { to { transform: rotate(360deg); } }

    @media (prefers-reduced-motion: reduce) {
        .esc__girando { animation-duration: 2.5s; }
    }
</style>

<script>
    window.escanerCodigo = function () {
        // Fuera del estado de Alpine: no necesitan ser reactivos.
        let lector = null;
        let audio = null;
        let ultimo = '';
        let ultimoEn = 0;
        let temporizador = null;
        let regresarA = null;

        const tactil = () => window.matchMedia('(pointer: coarse)').matches;

        return {
            abierto: false,
            estado: 'apagado',
            error: '',
            titulo: '',
            pista: '',
            continuo: false,
            campo: null,
            origen: null,
            aviso: null,
            hayLinterna: false,
            linterna: false,

            abrir(detalle = {}) {
                if (this.abierto) {
                    return;
                }

                this.continuo = detalle.continuo === true;
                this.campo = detalle.campo ?? null;
                this.origen = detalle.origen ?? null;
                this.titulo = detalle.titulo ?? 'Escanear código de barras';
                this.pista = this.continuo
                    ? 'Cada código que leas se agrega. Toca Listo al terminar.'
                    : 'Apunta al código de barras y se llena solo.';
                this.aviso = null;
                this.error = '';
                ultimo = '';
                regresarA = document.activeElement;

                this.abierto = true;
                document.documentElement.style.overflow = 'hidden';
                this.prepararSonido();
                this.$nextTick(() => this.$refs.listo?.focus({ preventScroll: true }));
                this.encender();
            },

            async cerrar() {
                if (! this.abierto) {
                    return;
                }

                this.abierto = false;
                document.documentElement.style.overflow = '';
                clearTimeout(temporizador);
                await this.apagar();

                if (! tactil()) {
                    regresarA?.focus?.({ preventScroll: true });
                }

                window.dispatchEvent(new CustomEvent('escaner-cerrado', { detail: { origen: this.origen } }));
            },

            async encender() {
                this.estado = 'iniciando';
                this.error = '';

                if (! window.isSecureContext || ! navigator.mediaDevices?.getUserMedia) {
                    this.fallar('La cámara solo funciona si el sistema se abre con https:// (conexión segura).');

                    return;
                }

                try {
                    await this.cargarLibreria();
                } catch (e) {
                    this.fallar('No se pudo cargar el lector de códigos. Revisa la conexión e intenta de nuevo.');

                    return;
                }

                await this.$nextTick();

                if (! this.abierto) {
                    return;
                }

                const libreria = window.__Html5QrcodeLibrary__;
                const formato = libreria.Html5QrcodeSupportedFormats;

                lector = new libreria.Html5Qrcode(this.$refs.lector.id, {
                    verbose: false,
                    useBarCodeDetectorIfSupported: true,
                    formatsToSupport: [
                        formato.EAN_13, formato.EAN_8, formato.UPC_A, formato.UPC_E,
                        formato.CODE_128, formato.CODE_39, formato.CODE_93,
                        formato.ITF, formato.CODABAR, formato.QR_CODE,
                    ],
                });

                try {
                    await lector.start(
                        { facingMode: 'environment' },
                        {
                            fps: 12,
                            // Recuadro ancho y bajo: los códigos de barras son largos.
                            qrbox: (ancho, alto) => ({
                                width: Math.max(60, Math.floor(ancho * 0.86)),
                                height: Math.max(60, Math.floor(Math.min(alto * 0.5, ancho * 0.45))),
                            }),
                        },
                        (texto) => this.leido(texto),
                        () => {},
                    );
                } catch (e) {
                    lector = null;
                    const causa = `${e?.name ?? ''} ${e?.message ?? e}`;

                    if (/NotAllowed|Permission/i.test(causa)) {
                        this.fallar('El navegador no tiene permiso para usar la cámara. Actívalo en los permisos del sitio y vuelve a intentar.');
                    } else if (/NotFound|DevicesNotFound|Overconstrained/i.test(causa)) {
                        this.fallar('No se encontró una cámara en este equipo.');
                    } else if (/NotReadable|TrackStart|in use/i.test(causa)) {
                        this.fallar('Otra aplicación está usando la cámara. Ciérrala y vuelve a intentar.');
                    } else {
                        this.fallar('No se pudo encender la cámara.');
                    }

                    return;
                }

                if (! this.abierto) {
                    await this.apagar();

                    return;
                }

                this.estado = 'escaneando';

                try {
                    this.hayLinterna = lector.getRunningTrackCameraCapabilities().torchFeature().isSupported();
                } catch (e) {
                    this.hayLinterna = false;
                }
            },

            async apagar() {
                const actual = lector;

                lector = null;
                this.estado = 'apagado';
                this.hayLinterna = false;
                this.linterna = false;

                if (actual === null) {
                    return;
                }

                try {
                    if (actual.isScanning) {
                        await actual.stop();
                    }
                } catch (e) {}

                try {
                    actual.clear();
                } catch (e) {}
            },

            async reintentar() {
                await this.apagar();
                this.encender();
            },

            leido(texto) {
                const codigo = String(texto ?? '').trim();
                const ahora = Date.now();

                if (codigo === '') {
                    return;
                }

                // El mismo código frente a la cámara cuenta una vez; para
                // sumar otro hay que retirarlo un momento.
                if (codigo === ultimo && ahora - ultimoEn < 2000) {
                    ultimoEn = ahora;

                    return;
                }

                ultimo = codigo;
                ultimoEn = ahora;
                this.pitar(false);
                navigator.vibrate?.(70);

                if (this.campo !== null) {
                    const entrada = document.getElementById(this.campo);

                    if (entrada !== null) {
                        entrada.value = codigo;
                        entrada.dispatchEvent(new Event('input', { bubbles: true }));
                        entrada.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }

                window.dispatchEvent(new CustomEvent('codigo-escaneado', { detail: { origen: this.origen, codigo } }));

                if (! this.continuo) {
                    this.cerrar();
                }
            },

            avisar(detalle = {}) {
                if (! this.abierto) {
                    return;
                }

                const ok = detalle.ok !== false;

                this.aviso = { ok, texto: String(detalle.texto ?? '') };
                clearTimeout(temporizador);
                temporizador = setTimeout(() => { this.aviso = null; }, ok ? 3500 : 5000);

                if (! ok) {
                    this.pitar(true);
                    navigator.vibrate?.([60, 60, 60]);
                }
            },

            async alternarLinterna() {
                if (lector === null) {
                    return;
                }

                try {
                    await lector.getRunningTrackCameraCapabilities().torchFeature().apply(! this.linterna);
                    this.linterna = ! this.linterna;
                } catch (e) {
                    this.hayLinterna = false;
                }
            },

            fallar(texto) {
                this.estado = 'error';
                this.error = texto;
            },

            cargarLibreria() {
                if (window.__Html5QrcodeLibrary__?.Html5Qrcode) {
                    return Promise.resolve();
                }

                window.__cargaEscaner ??= new Promise((resolver, rechazar) => {
                    const script = document.createElement('script');

                    script.src = @js($libreria);
                    script.onload = resolver;
                    script.onerror = () => {
                        window.__cargaEscaner = null;
                        script.remove();
                        rechazar(new Error('No se pudo cargar html5-qrcode'));
                    };

                    document.head.appendChild(script);
                });

                return window.__cargaEscaner;
            },

            // Se crea al tocar el botón (gesto del usuario), si no el
            // teléfono no deja sonar el pitido.
            prepararSonido() {
                try {
                    audio ??= new (window.AudioContext || window.webkitAudioContext)();
                    audio.resume?.();
                } catch (e) {
                    audio = null;
                }
            },

            pitar(error) {
                if (audio === null) {
                    return;
                }

                try {
                    const tono = audio.createOscillator();
                    const volumen = audio.createGain();

                    tono.type = 'square';
                    tono.frequency.value = error ? 220 : 1760;
                    volumen.gain.value = 0.05;
                    tono.connect(volumen).connect(audio.destination);
                    tono.start();
                    tono.stop(audio.currentTime + (error ? 0.28 : 0.08));
                } catch (e) {}
            },
        };
    };
</script>

<div
    x-data="escanerCodigo()"
    x-on:abrir-escaner.window="abrir($event.detail)"
    x-on:escaner-aviso.window="avisar($event.detail)"
    x-on:visibilitychange.document="document.hidden && cerrar()"
>
    <div
        class="esc"
        x-show="abierto"
        x-cloak
        x-transition.opacity.duration.150ms
        x-on:keydown.escape.window="abierto && cerrar()"
        x-on:click.self="cerrar()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="escaner-titulo"
    >
        <div class="esc__caja">
            <div class="esc__cabeza">
                <div>
                    <h2 id="escaner-titulo" x-text="titulo"></h2>
                    <p x-text="pista"></p>
                </div>
                <button type="button" class="esc__listo" x-ref="listo" x-on:click="cerrar()">Listo</button>
            </div>

            <div class="esc__visor">
                <div id="escaner-lector" class="esc__lector" x-ref="lector"></div>

                <div class="esc__estado" x-show="estado === 'iniciando'">
                    <x-filament::icon icon="heroicon-o-arrow-path" class="esc__girando" />
                    <span>Encendiendo la cámara…</span>
                </div>

                <div class="esc__estado" x-show="estado === 'error'" x-cloak>
                    <x-filament::icon icon="heroicon-o-video-camera-slash" />
                    <span x-text="error"></span>
                    <button type="button" class="esc__reintentar" x-on:click="reintentar()">Intentar de nuevo</button>
                </div>
            </div>

            <div class="esc__pie">
                <p
                    class="esc__aviso"
                    x-show="aviso !== null"
                    x-bind:class="aviso?.ok ? 'es-ok' : 'es-error'"
                    aria-live="polite"
                >
                    <x-filament::icon icon="heroicon-s-check-circle" x-show="aviso?.ok" />
                    <x-filament::icon icon="heroicon-s-exclamation-circle" x-show="aviso && ! aviso.ok" />
                    <span x-text="aviso?.texto"></span>
                </p>
                <p class="esc__pista" x-show="aviso === null && estado === 'escaneando'">Acerca o aleja el teléfono hasta que el código se vea nítido.</p>

                <button
                    type="button"
                    class="esc__linterna"
                    x-show="hayLinterna"
                    x-on:click="alternarLinterna()"
                    x-bind:aria-pressed="linterna ? 'true' : 'false'"
                    aria-label="Linterna"
                    title="Linterna"
                >
                    <x-filament::icon icon="heroicon-o-bolt" />
                </button>
            </div>
        </div>
    </div>
</div>
