<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Exceptions\GrupoOlympoException;
use App\Enums\ListaPrecio;
use App\Enums\TipoDescuento;
use App\Enums\TipoDocumentoVenta;
use App\Enums\TipoIsv;
use App\Enums\TipoMovimientoCaja;
use App\Filament\Schemas\Components\MontoField;
use App\Filament\Schemas\Components\RTNField;
use App\Filament\Schemas\Components\TelefonoHondurasField;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\Ventas\Datos\DatosVenta;
use App\Services\Ventas\Datos\LineaVenta;
use App\Services\Ventas\TurnoCajaService;
use App\Services\Ventas\VentaService;
use App\Support\Ventas\CalculadoraVenta;
use App\Support\Ventas\DescuentoPermitido;
use App\Support\Ventas\PrecioProducto;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * Punto de venta de mostrador.
 *
 * Dos zonas: a la izquierda el catálogo (buscador, categoría y la lista de
 * productos con su botón Agregar), a la derecha el carrito (productos,
 * documento, cliente, total, pagos y Cobrar, siempre a la vista). El
 * carrito vive en el componente (Livewire); los precios y totales salen de
 * las mismas clases que usa VentaService al guardar, así lo que ve el
 * cajero es lo que queda en el documento.
 *
 * Pagos por líneas: efectivo, tarjeta o transferencia, combinables. La
 * primera línea sin monto toma lo que falta para el total.
 *
 * Descuentos en % o en lempiras, por línea y a toda la venta (el global se
 * reparte entre las líneas). El tope del rol se avisa en pantalla y lo
 * hace cumplir VentaService al cobrar.
 *
 * Teclado: F2 buscar, flechas para elegir en la lista, Enter agrega (o
 * agrega por código de barras), Esc limpia la búsqueda, F9 cobra.
 *
 * En el teléfono o la tablet, el botón de cámara del buscador lee códigos
 * de barras uno tras otro (escanear()) y el catálogo y el carrito se
 * muestran por turnos.
 *
 * @property Schema $form
 * @property-read list<array{clave: string, producto: Producto, presentacion: ProductoPresentacion|null, opciones: array<int, string>, unidad_base: string, cantidad: float, cantidad_base: float, descuento_tipo: TipoDescuento, descuento_pct: float, excede_descuento: bool, precio: float, existencia: float|null, excede: bool, tipo_isv: TipoIsv, bruto: float, total_linea: float, descuento_linea: float, descuento_global: float, descuento: float, total: float, base: float, isv: float}> $carrito
 * @property-read array{descuento: float, importe_exento: float, importe_gravado_15: float, importe_gravado_18: float, isv_15: float, isv_18: float, subtotal: float, total: float} $totales
 */
class PuntoDeVenta extends Page
{
    use HasPageShield;

    /** Formas de pago de cada línea de pago. */
    public const METODOS = [
        'efectivo'      => 'Efectivo',
        'tarjeta'       => 'Tarjeta',
        'transferencia' => 'Transferencia',
    ];

    /** Billetes para sugerir el efectivo recibido. */
    private const BILLETES = [50, 100, 500, 1000];

    /** Productos que se muestran en el catálogo por tanda. */
    private const POR_PAGINA = 40;

    protected string $view = 'filament.pages.punto-de-venta';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Punto de venta';

    protected static ?string $title = 'Punto de venta';

    protected static ?string $slug = 'punto-de-venta';

    protected static ?int $navigationSort = 1;

    protected Width|string|null $maxContentWidth = Width::Full;

    public ?int $turnoId = null;

    public string $busqueda = '';

    public ?int $categoriaId = null;

    public int $limite = self::POR_PAGINA;

    /** @var array<string, array{producto_id: int, presentacion_id: int|string|null, cantidad: float|int|string, descuento: float|int|string|null, descuento_tipo?: string}> */
    public array $lineas = [];

    /**
     * Resumen del turno memorizado durante el request (el modal de cierre lo lee varias veces).
     *
     * @var array{cantidad_ventas: int, anuladas: int, recibos: int, facturas: int, total_ventas: float, total_efectivo: float, total_tarjeta: float, total_transferencia: float, total_ingresos: float, total_egresos: float, efectivo_esperado: float}|null
     */
    private ?array $resumen = null;

    /**
     * Estado del carrito: lo del formulario (documento, cliente, precio) y
     * las líneas de pago (`pagos`: método, monto y referencia).
     *
     * @var array<string, mixed>|null
     */
    public ?array $venta = [];

    /** El bloque del descuento a toda la venta se muestra al pedirlo. */
    public bool $descuentoGlobalAbierto = false;

    /**
     * Documento, precio y cliente: abiertos, o en una línea de resumen para
     * que la lista de productos tenga espacio. Se cierran con el primer
     * producto y se abren solos si algo de ahí no pasa al cobrar.
     */
    public bool $datosAbiertos = true;

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    /**
     * Sin encabezado de página: el turno y sus acciones van en la franja
     * superior del mostrador, para dejarle la pantalla al carrito.
     */
    public function getHeading(): string
    {
        return '';
    }

    public function mount(): void
    {
        $this->turnoId = app(TurnoCajaService::class)->turnoAbierto($this->usuario())?->id;
        $this->reiniciarVenta();
    }

    // ─── Caja: documento, precio y cliente ───────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('venta')
            ->components([
                // En el teléfono, uno debajo del otro: lado a lado no caben.
                Grid::make(['default' => 1, 'sm' => 2])
                    ->schema([
                        ToggleButtons::make('tipo_documento')
                            ->label('Documento')
                            ->options(['recibo' => 'Recibo', 'factura' => 'Factura'])
                            ->icons(['recibo' => Heroicon::OutlinedDocumentText, 'factura' => Heroicon::OutlinedDocumentCheck])
                            ->colors(['recibo' => 'gray', 'factura' => 'primary'])
                            ->grouped()
                            ->required()
                            ->live(),
                        ToggleButtons::make('lista_precio')
                            ->label('Precio')
                            ->options(['publico' => 'Público', 'mayoreo' => 'Mayoreo'])
                            ->colors(['publico' => 'gray', 'mayoreo' => 'warning'])
                            ->grouped()
                            ->required()
                            ->live(),
                    ]),
                // El nombre y el RTN de la factura salen del cliente: sin cliente
                // es "Consumidor final", sin RTN.
                Select::make('cliente_id')
                    ->label('Cliente')
                    ->placeholder('Consumidor final')
                    ->helperText(fn (Get $get): ?string => $get('tipo_documento') === 'factura' && blank($get('cliente_id')) ? 'Para factura con RTN, busca al cliente o regístralo con +.' : null)
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::buscarClientes($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? Cliente::query()->find((int) $value)?->etiqueta() : null)
                    ->createOptionForm([
                        TextInput::make('nombre')
                            ->label('Nombre o razón social')
                            ->required()
                            ->maxLength(150)
                            // Se guarda en mayúsculas (Cliente); así se ve mientras se escribe.
                            ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                        RTNField::make()
                            ->unique(table: 'clientes', column: 'rtn'),
                        TelefonoHondurasField::make('telefono', 'Teléfono'),
                        ToggleButtons::make('tipo_precio')
                            ->label('Precio')
                            ->options(ListaPrecio::class)
                            ->default(ListaPrecio::Publico)
                            ->inline(),
                    ])
                    ->createOptionUsing(fn (array $data): int => Cliente::query()->create($data)->id)
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $cliente = is_numeric($state) ? Cliente::query()->find((int) $state) : null;

                        $set('lista_precio', $cliente !== null ? $cliente->tipo_precio->value : ListaPrecio::Publico->value);
                    }),
            ]);
    }

    /**
     * Lo que se ve de los datos de la venta cuando están cerrados.
     *
     * @return array{factura: bool, mayoreo: bool, cliente: string, rtn: string|null}
     */
    public function resumenDatos(): array
    {
        $venta = $this->venta ?? [];
        $clienteId = self::idOpcional($venta['cliente_id'] ?? null);
        $cliente = $clienteId !== null ? Cliente::query()->find($clienteId) : null;

        return [
            'factura' => ($venta['tipo_documento'] ?? null) === TipoDocumentoVenta::Factura->value,
            'mayoreo' => ($venta['lista_precio'] ?? null) === ListaPrecio::Mayoreo->value,
            'cliente' => $cliente !== null ? $cliente->nombre : 'Consumidor final',
            'rtn'     => self::texto($cliente?->rtn),
        ];
    }

    // ─── Catálogo ─────────────────────────────────────────────────────────

    public function updatedBusqueda(): void
    {
        $this->limite = self::POR_PAGINA;
    }

    public function updatedCategoriaId(): void
    {
        $this->limite = self::POR_PAGINA;
        $this->dispatch('enfocar-busqueda');
    }

    public function mostrarMas(): void
    {
        $this->limite += self::POR_PAGINA;
    }

    /**
     * Productos del catálogo según la búsqueda y la categoría.
     *
     * @return array{productos: list<array{producto_id: int, nombre: string, codigo: string|null, marca: string|null, unidad: string, existencia: string|null, estado: string, precio: float, presentaciones: list<array{id: int, etiqueta: string, precio: float}>}>, hay_mas: bool}
     */
    #[Computed]
    public function catalogo(): array
    {
        return $this->consultarCatalogo(trim($this->busqueda), $this->limite);
    }

    /**
     * Categorías que tienen productos activos, para el filtro.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function categorias(): array
    {
        return Categoria::query()
            ->where('activo', true)
            ->whereIn('id', Producto::query()->where('activo', true)->select('categoria_id'))
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (Categoria $categoria): array => [$categoria->id => $categoria->nombre])
            ->all();
    }

    /**
     * Enter en el buscador: código de barras de una presentación, código de
     * barras o código interno del producto, o el único producto que
     * coincide por nombre. Lo que se agrega así limpia la búsqueda (el
     * escáner manda el código y Enter).
     */
    public function agregarPorCodigo(): void
    {
        $termino = trim($this->busqueda);

        if ($termino === '') {
            return;
        }

        $encontrado = $this->buscarPorCodigo($termino);

        if ($encontrado !== null) {
            $this->agregar($encontrado['producto']->id, $encontrado['presentacion']?->id, limpiar: true);

            return;
        }

        $encontrados = $this->consultarCatalogo($termino, 2)['productos'];

        if (count($encontrados) === 1) {
            $this->agregar($encontrados[0]['producto_id'], limpiar: true);

            return;
        }

        if ($encontrados === []) {
            Notification::make()
                ->warning()
                ->title("No se encontró \"{$termino}\"")
                ->body('Revisa el código o busca por nombre.')
                ->send();
        }
    }

    /**
     * Código leído con la cámara del teléfono o la tablet. Solo agrega por
     * código exacto (sin buscar por nombre) y responde qué pasó, para
     * mostrarlo sobre la cámara sin cerrarla: así se leen varios productos
     * seguidos.
     *
     * @return array{ok: bool, texto: string}
     */
    public function escanear(string $codigo): array
    {
        $codigo = trim($codigo);
        $encontrado = $codigo !== '' ? $this->buscarPorCodigo($codigo) : null;

        if ($encontrado === null) {
            return ['ok' => false, 'texto' => "Ningún producto tiene el código {$codigo}."];
        }

        $producto = $encontrado['producto'];
        $presentacion = $encontrado['presentacion'];
        $clave = $this->sumarAlCarrito($producto->id, $presentacion?->id);
        $nombre = $presentacion !== null ? $producto->nombre.', '.$presentacion->etiqueta() : $producto->nombre;

        return [
            'ok'    => true,
            'texto' => $nombre.': '.cantidadFormato(self::numero($this->lineas[$clave]['cantidad'])).' en el carrito',
        ];
    }

    // ─── Carrito ──────────────────────────────────────────────────────────

    /**
     * Desde la lista se agrega sin borrar la búsqueda (para agregar varios
     * del mismo filtro); con Enter o el escáner, la búsqueda se limpia.
     */
    public function agregar(int $productoId, ?int $presentacionId = null, bool $limpiar = false): void
    {
        $this->sumarAlCarrito($productoId, $presentacionId);

        if ($limpiar) {
            $this->busqueda = '';
            $this->limite = self::POR_PAGINA;
        }

        $this->dispatch('enfocar-busqueda');
    }

    public function sumar(string $clave, int $cuanto): void
    {
        if (! isset($this->lineas[$clave])) {
            return;
        }

        $nueva = self::numero($this->lineas[$clave]['cantidad']) + $cuanto;

        if ($nueva <= 0) {
            $this->quitar($clave);

            return;
        }

        $this->lineas[$clave]['cantidad'] = $nueva;
    }

    public function quitar(string $clave): void
    {
        unset($this->lineas[$clave]);

        // Carrito vacío: los datos de la venta vuelven a la vista.
        if ($this->lineas === []) {
            $this->datosAbiertos = true;
        }

        $this->dispatch('enfocar-busqueda');
    }

    public function vaciar(): void
    {
        $this->reiniciarVenta();
    }

    /**
     * Líneas del carrito con precio, existencia e importes calculados.
     *
     * @return list<array{clave: string, producto: Producto, presentacion: ProductoPresentacion|null, opciones: array<int, string>, unidad_base: string, cantidad: float, cantidad_base: float, descuento_tipo: TipoDescuento, descuento_pct: float, excede_descuento: bool, precio: float, existencia: float|null, excede: bool, tipo_isv: TipoIsv, bruto: float, total_linea: float, descuento_linea: float, descuento_global: float, descuento: float, total: float, base: float, isv: float}>
     */
    #[Computed]
    public function carrito(): array
    {
        return $this->calcularCarrito();
    }

    /**
     * Lo mismo que `carrito`, sin la caché del request: lo usa cobrar(),
     * que después vacía el carrito.
     *
     * @return list<array{clave: string, producto: Producto, presentacion: ProductoPresentacion|null, opciones: array<int, string>, unidad_base: string, cantidad: float, cantidad_base: float, descuento_tipo: TipoDescuento, descuento_pct: float, excede_descuento: bool, precio: float, existencia: float|null, excede: bool, tipo_isv: TipoIsv, bruto: float, total_linea: float, descuento_linea: float, descuento_global: float, descuento: float, total: float, base: float, isv: float}>
     */
    private function calcularCarrito(): array
    {
        if ($this->lineas === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map(
            static fn (array $linea): int => (int) $linea['producto_id'],
            $this->lineas,
        )));

        $productos = Producto::query()
            ->with(['unidadMedida', 'presentaciones.unidadMedida'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $lista = $this->listaPrecio();
        $maximo = $this->descuentoMaximo();
        $previas = [];

        foreach ($this->lineas as $clave => $linea) {
            $producto = $productos->get((int) $linea['producto_id']);

            if (! $producto instanceof Producto) {
                continue;
            }

            $presentacionId = self::idOpcional($linea['presentacion_id']);
            $presentacion = $presentacionId !== null ? $producto->presentaciones->firstWhere('id', $presentacionId) : null;
            $presentacion = $presentacion instanceof ProductoPresentacion ? $presentacion : null;
            $presentacion?->setRelation('producto', $producto);

            $cantidad = max(0.0, self::numero($linea['cantidad']));
            $cantidadBase = $cantidad * ($presentacion !== null ? (float) $presentacion->factor : 1.0);
            $existencia = $producto->controla_stock ? (float) $producto->stock : null;
            $tipoDescuento = TipoDescuento::desdeEstado($linea['descuento_tipo'] ?? null);
            $descuento = max(0.0, self::numero($linea['descuento']));
            $precio = PrecioProducto::unitario($producto, $presentacion, $lista);
            $calculo = CalculadoraVenta::linea($precio, $cantidad, $descuento, $producto->tipo_isv->tasa(), $tipoDescuento);
            $porcentaje = CalculadoraVenta::porcentaje($calculo['bruto'], $descuento, $tipoDescuento);

            $opciones = [];

            foreach ($producto->presentaciones as $opcion) {
                if ($opcion->activo) {
                    $opcion->setRelation('producto', $producto);
                    $opciones[$opcion->id] = $opcion->etiqueta();
                }
            }

            $previas[] = [
                'clave'            => (string) $clave,
                'producto'         => $producto,
                'presentacion'     => $presentacion,
                'opciones'         => $opciones,
                'unidad_base'      => $producto->unidadMedida->nombre,
                'cantidad'         => $cantidad,
                'cantidad_base'    => $cantidadBase,
                'descuento_tipo'   => $tipoDescuento,
                'descuento_pct'    => $porcentaje,
                'excede_descuento' => $porcentaje - $maximo > 0.001,
                'precio'           => $precio,
                'existencia'       => $existencia,
                'excede'           => $existencia !== null && $cantidadBase - $existencia > 0.000001,
                'tipo_isv'         => $producto->tipo_isv,
                'bruto'            => $calculo['bruto'],
                'total_linea'      => $calculo['total'],
                'descuento_linea'  => $calculo['descuento'],
            ];
        }

        // El descuento a toda la venta se reparte entre las líneas.
        $partes = CalculadoraVenta::repartirGlobal(
            array_column($previas, 'total_linea'),
            max(0.0, self::numero($this->venta['descuento_global'] ?? null)),
            TipoDescuento::desdeEstado($this->venta['descuento_global_tipo'] ?? null),
        );
        $carrito = [];

        foreach ($previas as $indice => $previa) {
            $parte = $partes[$indice] ?? 0.0;
            $calculo = CalculadoraVenta::conParteGlobal($previa['bruto'], $previa['total_linea'], $parte, $previa['tipo_isv']->tasa());

            $carrito[] = [
                'clave'            => $previa['clave'],
                'producto'         => $previa['producto'],
                'presentacion'     => $previa['presentacion'],
                'opciones'         => $previa['opciones'],
                'unidad_base'      => $previa['unidad_base'],
                'cantidad'         => $previa['cantidad'],
                'cantidad_base'    => $previa['cantidad_base'],
                'descuento_tipo'   => $previa['descuento_tipo'],
                'descuento_pct'    => $previa['descuento_pct'],
                'excede_descuento' => $previa['excede_descuento'],
                'precio'           => $previa['precio'],
                'existencia'       => $previa['existencia'],
                'excede'           => $previa['excede'],
                'tipo_isv'         => $previa['tipo_isv'],
                'bruto'            => $calculo['bruto'],
                'total_linea'      => $previa['total_linea'],
                'descuento_linea'  => $previa['descuento_linea'],
                'descuento_global' => $parte,
                'descuento'        => $calculo['descuento'],
                'total'            => $calculo['total'],
                'base'             => $calculo['base'],
                'isv'              => $calculo['isv'],
            ];
        }

        return $carrito;
    }

    /**
     * @return array{descuento: float, importe_exento: float, importe_gravado_15: float, importe_gravado_18: float, isv_15: float, isv_18: float, subtotal: float, total: float}
     */
    #[Computed]
    public function totales(): array
    {
        return CalculadoraVenta::totales($this->carrito);
    }

    // ─── Pagos ────────────────────────────────────────────────────────────

    public function agregarPago(): void
    {
        $usados = array_column($this->lineasDePago(), 'metodo');
        $metodo = 'efectivo';

        foreach (array_keys(self::METODOS) as $opcion) {
            if (! in_array($opcion, $usados, true)) {
                $metodo = $opcion;

                break;
            }
        }

        $pagos = $this->lineasDePago();
        $pagos[] = ['metodo' => $metodo, 'monto' => null, 'referencia' => null];
        $this->venta['pagos'] = $pagos;
    }

    public function quitarPago(int $indice): void
    {
        $pagos = $this->lineasDePago();
        unset($pagos[$indice]);

        $this->venta['pagos'] = $pagos !== [] ? array_values($pagos) : [self::pagoInicial()];
    }

    /**
     * Botón de billete: lo pone como monto de la (primera) línea de efectivo.
     */
    public function fijarBillete(float $monto): void
    {
        $pagos = $this->lineasDePago();

        foreach ($pagos as $indice => $pago) {
            if ($pago['metodo'] === 'efectivo') {
                $pagos[$indice]['monto'] = $monto;
                $this->venta['pagos'] = $pagos;

                return;
            }
        }

        $pagos[] = ['metodo' => 'efectivo', 'monto' => $monto, 'referencia' => null];
        $this->venta['pagos'] = $pagos;
    }

    /**
     * Pagos con el monto que cuenta: el tecleado o, en la primera línea sin
     * monto, lo que falta para el total.
     *
     * @return list<array{metodo: string, monto: float, tecleado: bool, referencia: string|null}>
     */
    public function pagosResueltos(?float $total = null): array
    {
        $total ??= $this->totales['total'];
        $lineas = $this->lineasDePago();
        $tecleado = 0.0;

        foreach ($lineas as $linea) {
            $tecleado += $linea['monto'] ?? 0.0;
        }

        $resto = max(0.0, round($total - $tecleado, 2));
        $restoUsado = false;
        $resueltos = [];

        foreach ($lineas as $linea) {
            if ($linea['monto'] !== null) {
                $monto = $linea['monto'];
            } elseif (! $restoUsado) {
                $monto = $resto;
                $restoUsado = true;
            } else {
                $monto = 0.0;
            }

            $resueltos[] = [
                'metodo'     => $linea['metodo'],
                'monto'      => round($monto, 2),
                'tecleado'   => $linea['monto'] !== null,
                'referencia' => $linea['referencia'],
            ];
        }

        return $resueltos;
    }

    /**
     * Pagado, lo que falta, el cambio y el efectivo que hay que cobrar.
     *
     * @return array{pagado: float, restante: float, cambio: float, efectivo_a_cobrar: float, hay_efectivo: bool}
     */
    public function cobro(?float $total = null): array
    {
        $total ??= $this->totales['total'];

        /** @var array<string, float> $porMetodo */
        $porMetodo = ['efectivo' => 0.0, 'tarjeta' => 0.0, 'transferencia' => 0.0];
        $hayEfectivo = false;

        foreach ($this->pagosResueltos($total) as $pago) {
            $porMetodo[$pago['metodo']] = ($porMetodo[$pago['metodo']] ?? 0.0) + $pago['monto'];
            $hayEfectivo = $hayEfectivo || $pago['metodo'] === 'efectivo';
        }

        $efectivoACobrar = max(0.0, round($total - $porMetodo['tarjeta'] - $porMetodo['transferencia'], 2));
        $pagado = round($porMetodo['efectivo'] + $porMetodo['tarjeta'] + $porMetodo['transferencia'], 2);

        return [
            'pagado'            => $pagado,
            'restante'          => max(0.0, round($total - $pagado, 2)),
            'cambio'            => max(0.0, round($porMetodo['efectivo'] - $efectivoACobrar, 2)),
            'efectivo_a_cobrar' => $efectivoACobrar,
            'hay_efectivo'      => $hayEfectivo,
        ];
    }

    /**
     * Billetes con que suele pagar el cliente: lo que se cobra en efectivo
     * redondeado hacia arriba a 50, 100, 500 y 1000 (sin repetir).
     *
     * @return list<float>
     */
    public function sugerenciasEfectivo(): array
    {
        $cobro = $this->cobro();
        $efectivo = $cobro['efectivo_a_cobrar'];

        if ($efectivo <= 0 || ! $cobro['hay_efectivo']) {
            return [];
        }

        $sugerencias = [];

        foreach (self::BILLETES as $billete) {
            $monto = (float) (ceil($efectivo / $billete) * $billete);

            if ($monto > $efectivo && ! in_array($monto, $sugerencias, true)) {
                $sugerencias[] = $monto;
            }
        }

        return $sugerencias;
    }

    public function descuentoMaximo(): float
    {
        return DescuentoPermitido::para($this->usuario());
    }

    /**
     * Descuento de la línea en % o en lempiras (por toda la línea).
     */
    public function tipoDescuento(string $clave, string $tipo): void
    {
        if (isset($this->lineas[$clave])) {
            $this->lineas[$clave]['descuento_tipo'] = TipoDescuento::desdeEstado($tipo)->value;
        }
    }

    public function tipoDescuentoGlobal(string $tipo): void
    {
        $this->venta['descuento_global_tipo'] = TipoDescuento::desdeEstado($tipo)->value;
    }

    public function abrirDescuentoGlobal(): void
    {
        $this->descuentoGlobalAbierto = true;
        $this->dispatch('enfocar-descuento-global');
    }

    public function quitarDescuentoGlobal(): void
    {
        $this->descuentoGlobalAbierto = false;
        $this->venta['descuento_global'] = null;
    }

    /**
     * El descuento a toda la venta: cómo se teclea, cuánto rebaja y si,
     * junto con los de las líneas, pasa el tope del cajero.
     *
     * @return array{tipo: TipoDescuento, global: float, porcentaje: float, excede: bool}
     */
    public function resumenDescuentos(): array
    {
        $bruto = 0.0;
        $rebajado = 0.0;
        $global = 0.0;

        foreach ($this->carrito as $linea) {
            $bruto += $linea['bruto'];
            $rebajado += $linea['descuento'];
            $global += $linea['descuento_global'];
        }

        $porcentaje = $bruto > 0 ? $rebajado / $bruto * 100 : 0.0;

        return [
            'tipo'       => TipoDescuento::desdeEstado($this->venta['descuento_global_tipo'] ?? null),
            'global'     => round($global, 2),
            'porcentaje' => round($porcentaje, 2),
            'excede'     => $porcentaje - $this->descuentoMaximo() > 0.001,
        ];
    }

    public function turno(): ?TurnoCaja
    {
        return $this->turnoId !== null
            ? TurnoCaja::query()->with(['caja', 'cajero'])->find($this->turnoId)
            : null;
    }

    // ─── Cobrar ───────────────────────────────────────────────────────────

    public function cobrar(): void
    {
        if ($this->turnoId === null) {
            Notification::make()->warning()->title('Abre un turno de caja para vender.')->send();

            return;
        }

        if ($this->lineas === []) {
            Notification::make()->warning()->title('Agrega al menos un producto.')->send();

            return;
        }

        try {
            $estado = $this->form->getState();
        } catch (ValidationException $e) {
            // Lo que no pasa está en los datos de la venta: se abren para verlo.
            $this->datosAbiertos = true;

            throw $e;
        }

        try {
            $venta = app(VentaService::class)->registrar($this->datosVenta($estado), $this->usuario());
        } catch (GrupoOlympoException $e) {
            Notification::make()
                ->danger()
                ->title('No se pudo registrar la venta')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            return;
        }

        $cambio = (float) $venta->cambio;

        Notification::make()
            ->success()
            ->title(($venta->esFactura() ? 'Factura ' : 'Recibo ').$venta->numero)
            ->body('Total '.lempiras((float) $venta->total).($cambio > 0 ? '. Cambio: '.lempiras($cambio) : ''))
            ->send();

        $this->dispatch('imprimir-ticket', url: route('filament.admin.tickets.venta', ['venta' => $venta]));
        $this->reiniciarVenta();
    }

    // ─── Acciones del turno (se muestran en la franja del mostrador) ──────

    public function abrirTurnoAction(): Action
    {
        return Action::make('abrirTurno')
            ->label('Abrir turno')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('primary')
            ->size('lg')
            ->visible(fn (): bool => $this->turnoId === null)
            ->modalWidth(Width::Medium)
            ->schema([
                Select::make('caja_id')
                    ->label('Caja')
                    ->options(fn (): array => Caja::query()
                        ->where('activo', true)
                        ->whereDoesntHave('turnoAbierto')
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all())
                    ->required(),
                MontoField::make('fondo_inicial', 'Fondo inicial (efectivo en caja)'),
            ])
            ->action(function (array $data): void {
                try {
                    $turno = app(TurnoCajaService::class)->abrir(
                        Caja::query()->findOrFail((int) $data['caja_id']),
                        $this->usuario(),
                        (float) $data['fondo_inicial'],
                    );
                } catch (GrupoOlympoException $e) {
                    Notification::make()->danger()->title('No se pudo abrir el turno')->body($e->getMessage())->send();

                    return;
                }

                $this->turnoId = $turno->id;

                Notification::make()->success()->title('Turno abierto. ¡Buenas ventas!')->send();
                $this->dispatch('enfocar-busqueda');
            });
    }

    public function movimientoCajaAction(): Action
    {
        return Action::make('movimientoCaja')
            ->label('Entrada o retiro')
            ->icon(Heroicon::OutlinedArrowsUpDown)
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => $this->turnoId !== null)
            ->modalHeading('Entrada o retiro de efectivo')
            ->modalWidth(Width::Medium)
            ->schema([
                ToggleButtons::make('tipo')
                    ->label('Tipo')
                    ->options(TipoMovimientoCaja::class)
                    ->default(TipoMovimientoCaja::Egreso)
                    ->inline()
                    ->required(),
                MontoField::make('monto', 'Monto'),
                TextInput::make('motivo')
                    ->label('Motivo')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Pago de flete, retiro a caja fuerte, cambio agregado…'),
            ])
            ->action(function (array $data): void {
                $turno = $this->turno();
                $tipo = TipoMovimientoCaja::desdeEstado($data['tipo'] ?? null);

                if ($turno === null || $tipo === null) {
                    return;
                }

                try {
                    app(TurnoCajaService::class)->registrarMovimiento($turno, $tipo, (float) $data['monto'], (string) $data['motivo'], $this->usuario());
                } catch (GrupoOlympoException $e) {
                    Notification::make()->danger()->title('No se pudo registrar')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title($tipo->getLabel().' registrado')->send();
            });
    }

    public function cerrarTurnoAction(): Action
    {
        return Action::make('cerrarTurno')
            ->label('Cerrar turno')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->outlined()
            ->size('sm')
            ->visible(fn (): bool => $this->turnoId !== null)
            ->modalWidth(Width::Large)
            ->modalHeading('Cerrar turno y hacer arqueo')
            ->modalSubmitActionLabel('Cerrar turno')
            ->schema([
                Section::make('Resumen del turno')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('r_ventas')
                            ->label('Ventas')
                            ->state(fn (): string => $this->resumenTurno()['cantidad_ventas'].' ventas, '.lempiras($this->resumenTurno()['total_ventas'])),
                        TextEntry::make('r_fondo')
                            ->label('Fondo inicial')
                            ->state(fn (): string => lempiras((float) $this->turno()?->fondo_inicial)),
                        TextEntry::make('r_efectivo')
                            ->label('Ventas en efectivo')
                            ->state(fn (): string => lempiras($this->resumenTurno()['total_efectivo'])),
                        TextEntry::make('r_tarjeta')
                            ->label('Tarjeta / transferencia')
                            ->state(fn (): string => lempiras($this->resumenTurno()['total_tarjeta']).' / '.lempiras($this->resumenTurno()['total_transferencia'])),
                        TextEntry::make('r_movimientos')
                            ->label('Entradas / retiros')
                            ->state(fn (): string => lempiras($this->resumenTurno()['total_ingresos']).' / '.lempiras($this->resumenTurno()['total_egresos'])),
                        TextEntry::make('r_esperado')
                            ->label('Efectivo que debe haber')
                            ->state(fn (): string => lempiras($this->resumenTurno()['efectivo_esperado']))
                            ->weight('bold'),
                    ]),
                MontoField::make('efectivo_contado', 'Efectivo contado en caja'),
                Textarea::make('notas')
                    ->label('Notas del cierre')
                    ->rows(2),
            ])
            ->action(function (array $data): void {
                $turno = $this->turno();

                if ($turno === null) {
                    return;
                }

                try {
                    $turno = app(TurnoCajaService::class)->cerrar(
                        $turno,
                        (float) $data['efectivo_contado'],
                        isset($data['notas']) ? (string) $data['notas'] : null,
                        $this->usuario(),
                    );
                } catch (GrupoOlympoException $e) {
                    Notification::make()->danger()->title('No se pudo cerrar el turno')->body($e->getMessage())->send();

                    return;
                }

                $diferencia = (float) $turno->diferencia;

                Notification::make()
                    ->title('Turno cerrado')
                    ->body(match (true) {
                        abs($diferencia) < 0.005 => 'La caja cuadra.',
                        $diferencia < 0          => 'Faltante de '.lempiras(abs($diferencia)).'.',
                        default                  => 'Sobrante de '.lempiras($diferencia).'.',
                    })
                    ->color(abs($diferencia) < 0.005 ? 'success' : 'warning')
                    ->persistent()
                    ->send();

                $this->dispatch('imprimir-ticket', url: route('filament.admin.tickets.turno', ['turno' => $turno]));
                $this->turnoId = null;
                $this->reiniciarVenta();
            });
    }

    /**
     * @return array{cantidad_ventas: int, anuladas: int, recibos: int, facturas: int, total_ventas: float, total_efectivo: float, total_tarjeta: float, total_transferencia: float, total_ingresos: float, total_egresos: float, efectivo_esperado: float}
     */
    public function resumenTurno(): array
    {
        if ($this->resumen !== null) {
            return $this->resumen;
        }

        $turno = $this->turno();

        if ($turno === null) {
            return [
                'cantidad_ventas'     => 0,
                'anuladas'            => 0,
                'recibos'             => 0,
                'facturas'            => 0,
                'total_ventas'        => 0.0,
                'total_efectivo'      => 0.0,
                'total_tarjeta'       => 0.0,
                'total_transferencia' => 0.0,
                'total_ingresos'      => 0.0,
                'total_egresos'       => 0.0,
                'efectivo_esperado'   => 0.0,
            ];
        }

        return $this->resumen = app(TurnoCajaService::class)->resumen($turno);
    }

    // ─── Apoyo ───────────────────────────────────────────────────────────

    /**
     * Suma uno a la línea de ese producto y presentación, o la crea, y
     * devuelve la clave de la línea.
     */
    private function sumarAlCarrito(int $productoId, ?int $presentacionId): string
    {
        foreach ($this->lineas as $clave => $linea) {
            if ((int) $linea['producto_id'] === $productoId && self::idOpcional($linea['presentacion_id']) === $presentacionId) {
                $this->lineas[$clave]['cantidad'] = self::numero($linea['cantidad']) + 1;
                $this->dispatch('linea-agregada', clave: (string) $clave);

                return (string) $clave;
            }
        }

        // Con el primer producto, los datos de la venta pasan a una línea de
        // resumen para dejarle el espacio a la lista.
        if ($this->lineas === []) {
            $this->datosAbiertos = false;
        }

        $clave = 'l'.Str::lower(Str::random(10));

        $this->lineas[$clave] = [
            'producto_id'     => $productoId,
            'presentacion_id' => $presentacionId,
            'cantidad'        => 1,
            'descuento'       => null,
            'descuento_tipo'  => TipoDescuento::Porcentaje->value,
        ];

        $this->dispatch('linea-agregada', clave: $clave);

        return $clave;
    }

    /**
     * Producto (y presentación) con ese código exacto: el código de barras
     * de una presentación, o el código de barras o el código interno del
     * producto.
     *
     * @return array{producto: Producto, presentacion: ProductoPresentacion|null}|null
     */
    private function buscarPorCodigo(string $codigo): ?array
    {
        $presentacion = ProductoPresentacion::query()
            ->with(['producto.unidadMedida', 'unidadMedida'])
            ->where('codigo_barras', $codigo)
            ->where('activo', true)
            ->whereHas('producto', fn (Builder $query): Builder => $query->where('activo', true))
            ->first();

        if ($presentacion !== null) {
            return ['producto' => $presentacion->producto, 'presentacion' => $presentacion];
        }

        $producto = Producto::query()
            ->where('activo', true)
            ->where(fn (Builder $query): Builder => $query
                ->where('codigo_barras', $codigo)
                ->orWhereRaw('LOWER(codigo) = ?', [mb_strtolower($codigo)]))
            ->first();

        return $producto !== null ? ['producto' => $producto, 'presentacion' => null] : null;
    }

    private function reiniciarVenta(): void
    {
        $this->lineas = [];
        $this->busqueda = '';
        $this->limite = self::POR_PAGINA;
        $this->descuentoGlobalAbierto = false;
        $this->datosAbiertos = true;

        $this->form->fill([
            'tipo_documento' => TipoDocumentoVenta::Recibo->value,
            'lista_precio'   => ListaPrecio::Publico->value,
            'cliente_id'     => null,
        ]);

        // Pagos y descuento global no son parte del formulario de Filament: se arman en la vista.
        $this->venta = [
            ...($this->venta ?? []),
            'pagos'                 => [self::pagoInicial()],
            'descuento_global'      => null,
            'descuento_global_tipo' => TipoDescuento::Porcentaje->value,
        ];

        $this->dispatch('enfocar-busqueda');
    }

    /**
     * @return array{metodo: string, monto: null, referencia: null}
     */
    private static function pagoInicial(): array
    {
        return ['metodo' => 'efectivo', 'monto' => null, 'referencia' => null];
    }

    /**
     * Líneas de pago normalizadas tal como vienen del estado de Livewire.
     *
     * @return list<array{metodo: string, monto: float|null, referencia: string|null}>
     */
    private function lineasDePago(): array
    {
        $pagos = $this->venta['pagos'] ?? null;
        $lineas = [];

        foreach (is_array($pagos) ? $pagos : [] as $pago) {
            if (! is_array($pago)) {
                continue;
            }

            $metodo = $pago['metodo'] ?? null;
            $monto = self::montoOpcional($pago['monto'] ?? null);

            $lineas[] = [
                'metodo'     => is_string($metodo) && array_key_exists($metodo, self::METODOS) ? $metodo : 'efectivo',
                'monto'      => $monto !== null ? max(0.0, $monto) : null,
                'referencia' => self::texto($pago['referencia'] ?? null),
            ];
        }

        return $lineas !== [] ? $lineas : [self::pagoInicial()];
    }

    private function listaPrecio(): ListaPrecio
    {
        return ListaPrecio::desdeEstado($this->venta['lista_precio'] ?? null) ?? ListaPrecio::Publico;
    }

    /**
     * Productos activos que coinciden con el término y la categoría, con el
     * precio de cada presentación para agregarla directo.
     *
     * @return array{productos: list<array{producto_id: int, nombre: string, codigo: string|null, marca: string|null, unidad: string, existencia: string|null, estado: string, precio: float, presentaciones: list<array{id: int, etiqueta: string, precio: float}>}>, hay_mas: bool}
     */
    private function consultarCatalogo(string $termino, int $limite): array
    {
        $lista = $this->listaPrecio();
        $categoria = $this->categoriaId;

        $consulta = Producto::query()
            ->with(['unidadMedida', 'marca', 'presentaciones.unidadMedida'])
            ->where('activo', true);

        if ($termino !== '') {
            $like = '%'.addcslashes($termino, '%_\\').'%';

            $consulta->where(fn (Builder $query): Builder => $query
                ->where('nombre', 'ilike', $like)
                ->orWhere('codigo', 'ilike', $like)
                ->orWhere('codigo_barras', $termino));
        }

        if ($categoria !== null) {
            $consulta->where(fn (Builder $query): Builder => $query
                ->where('categoria_id', $categoria)
                ->orWhereIn('categoria_id', Categoria::query()->where('parent_id', $categoria)->select('id')));
        }

        $productos = $consulta->orderBy('nombre')->limit($limite + 1)->get();
        $resultados = [];

        foreach ($productos->take($limite) as $producto) {
            $presentaciones = [];

            foreach ($producto->presentaciones as $presentacion) {
                if (! $presentacion->activo) {
                    continue;
                }

                $presentacion->setRelation('producto', $producto);

                $presentaciones[] = [
                    'id'       => $presentacion->id,
                    'etiqueta' => $presentacion->etiqueta(),
                    'precio'   => PrecioProducto::unitario($producto, $presentacion, $lista),
                ];
            }

            $estado = match (true) {
                ! $producto->controla_stock   => 'servicio',
                (float) $producto->stock <= 0 => 'agotado',
                $producto->estaBajoMinimo()   => 'bajo',
                default                       => 'ok',
            };

            $resultados[] = [
                'producto_id' => $producto->id,
                'nombre'      => $producto->nombre,
                'codigo'      => $producto->codigo,
                'marca'       => $producto->marca?->nombre,
                'unidad'      => $producto->unidadMedida->nombre,
                'existencia'  => $producto->controla_stock
                    ? cantidadFormato((float) $producto->stock).' '.$producto->unidadMedida->abreviatura
                    : null,
                'estado'         => $estado,
                'precio'         => PrecioProducto::unitario($producto, null, $lista),
                'presentaciones' => $presentaciones,
            ];
        }

        return ['productos' => $resultados, 'hay_mas' => $productos->count() > $limite];
    }

    /**
     * @return array<int, string>
     */
    private static function buscarClientes(string $busqueda): array
    {
        $like = '%'.addcslashes(trim($busqueda), '%_\\').'%';

        return Cliente::query()
            ->where('activo', true)
            ->where(fn (Builder $query): Builder => $query
                ->where('nombre', 'ilike', $like)
                ->orWhere('rtn', 'like', $like))
            ->orderBy('nombre')
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (Cliente $cliente): array => [$cliente->id => $cliente->etiqueta()])
            ->all();
    }

    /**
     * Arma la venta con el formulario (documento, precio, cliente) y las
     * líneas de pago sumadas por método.
     *
     * @param array<string, mixed> $estado
     */
    private function datosVenta(array $estado): DatosVenta
    {
        $lineas = [];

        foreach ($this->lineas as $linea) {
            $lineas[] = new LineaVenta(
                productoId: (int) $linea['producto_id'],
                cantidad: self::numero($linea['cantidad']),
                presentacionId: self::idOpcional($linea['presentacion_id']),
                descuento: self::numero($linea['descuento']),
                tipoDescuento: TipoDescuento::desdeEstado($linea['descuento_tipo'] ?? null),
            );
        }

        // Sin la caché del request: después de cobrar se vacía el carrito.
        $total = CalculadoraVenta::totales($this->calcularCarrito())['total'];
        $montos = ['efectivo' => 0.0, 'tarjeta' => 0.0, 'transferencia' => 0.0];
        $referencias = ['tarjeta' => null, 'transferencia' => null];
        $efectivoTecleado = false;

        foreach ($this->pagosResueltos($total) as $pago) {
            $montos[$pago['metodo']] = ($montos[$pago['metodo']] ?? 0.0) + $pago['monto'];

            if ($pago['metodo'] === 'efectivo') {
                $efectivoTecleado = $efectivoTecleado || $pago['tecleado'];
            } elseif ($pago['referencia'] !== null && ($referencias[$pago['metodo']] ?? null) === null) {
                $referencias[$pago['metodo']] = $pago['referencia'];
            }
        }

        return new DatosVenta(
            lineas: $lineas,
            tipoDocumento: TipoDocumentoVenta::desdeEstado($estado['tipo_documento'] ?? null) ?? TipoDocumentoVenta::Recibo,
            listaPrecio: ListaPrecio::desdeEstado($estado['lista_precio'] ?? null) ?? ListaPrecio::Publico,
            // Nombre y RTN del documento: los del cliente (VentaService los toma de ahí).
            clienteId: self::idOpcional($estado['cliente_id'] ?? null),
            // Efectivo sin monto tecleado = paga exacto.
            efectivoRecibido: $efectivoTecleado ? round($montos['efectivo'], 2) : null,
            montoTarjeta: round($montos['tarjeta'], 2),
            referenciaTarjeta: $referencias['tarjeta'],
            montoTransferencia: round($montos['transferencia'], 2),
            referenciaTransferencia: $referencias['transferencia'],
            descuentoGlobal: self::numero($this->venta['descuento_global'] ?? null),
            tipoDescuentoGlobal: TipoDescuento::desdeEstado($this->venta['descuento_global_tipo'] ?? null),
        );
    }

    private function usuario(): User
    {
        $usuario = Auth::user();

        if (! $usuario instanceof User) {
            abort(403);
        }

        return $usuario;
    }

    private static function numero(mixed $valor): float
    {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    private static function montoOpcional(mixed $valor): ?float
    {
        return is_numeric($valor) ? (float) $valor : null;
    }

    private static function idOpcional(mixed $valor): ?int
    {
        return is_numeric($valor) && (int) $valor > 0 ? (int) $valor : null;
    }

    private static function texto(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }
}
