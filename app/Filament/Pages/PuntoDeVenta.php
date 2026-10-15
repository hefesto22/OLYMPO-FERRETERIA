<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Exceptions\GrupoOlympoException;
use App\Enums\ListaPrecio;
use App\Enums\TipoDocumentoVenta;
use App\Enums\TipoIsv;
use App\Enums\TipoMovimientoCaja;
use App\Filament\Schemas\Components\MontoField;
use App\Filament\Schemas\Components\RTNField;
use App\Filament\Schemas\Components\TelefonoHondurasField;
use App\Models\Caja;
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
use Livewire\Attributes\Computed;

/**
 * Punto de venta de mostrador.
 *
 * El carrito vive en el componente (Livewire); los precios y totales que
 * se muestran salen de las mismas clases que usa VentaService al
 * guardar, así lo que ve el cajero es lo que queda en el documento.
 *
 * Atajos: F2 buscar · F9 cobrar · Enter en el buscador agrega por código.
 *
 * @property Schema $form
 * @property-read list<array{clave: string, producto: Producto, presentacion: ProductoPresentacion|null, opciones: array<int, string>, unidad_base: string, cantidad: float, descuento_pct: float, precio: float, existencia: float|null, tipo_isv: TipoIsv, bruto: float, descuento: float, total: float, base: float, isv: float}> $carrito
 * @property-read array{descuento: float, importe_exento: float, importe_gravado_15: float, importe_gravado_18: float, isv_15: float, isv_18: float, subtotal: float, total: float} $totales
 */
class PuntoDeVenta extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.punto-de-venta';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Punto de venta';

    protected static ?string $title = 'Punto de venta';

    protected static ?string $slug = 'punto-de-venta';

    protected static ?int $navigationSort = 1;

    protected Width|string|null $maxContentWidth = Width::Full;

    public ?int $turnoId = null;

    public string $busqueda = '';

    /** @var list<array{producto_id: int, etiqueta: string, detalle: string, precio: float}> */
    public array $resultados = [];

    /** @var array<string, array{producto_id: int, presentacion_id: int|string|null, cantidad: float|int|string, descuento: float|int|string}> */
    public array $lineas = [];

    /**
     * Resumen del turno memorizado durante el request (el modal de cierre lo lee varias veces).
     *
     * @var array{cantidad_ventas: int, anuladas: int, recibos: int, facturas: int, total_ventas: float, total_efectivo: float, total_tarjeta: float, total_transferencia: float, total_ingresos: float, total_egresos: float, efectivo_esperado: float}|null
     */
    private ?array $resumen = null;

    /** @var array<string, mixed>|null */
    public ?array $venta = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    public function mount(): void
    {
        $this->turnoId = app(TurnoCajaService::class)->turnoAbierto($this->usuario())?->id;
        $this->reiniciarVenta();
    }

    // ─── Formulario lateral: documento, cliente, precio y pago ───────────

    public function form(Schema $schema): Schema
    {
        $simbolo = (string) config('honduras.moneda.simbolo', 'L.');

        return $schema
            ->statePath('venta')
            ->components([
                ToggleButtons::make('tipo_documento')
                    ->label('Documento')
                    ->options(['recibo' => 'Recibo', 'factura' => 'Factura'])
                    ->icons(['recibo' => Heroicon::OutlinedDocumentText, 'factura' => Heroicon::OutlinedDocumentCheck])
                    ->colors(['recibo' => 'gray', 'factura' => 'primary'])
                    ->inline()
                    ->required()
                    ->live(),
                Select::make('cliente_id')
                    ->label('Cliente')
                    ->placeholder('Consumidor final')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::buscarClientes($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? Cliente::query()->find((int) $value)?->etiqueta() : null)
                    ->createOptionForm([
                        TextInput::make('nombre')
                            ->label('Nombre o razón social')
                            ->required()
                            ->maxLength(150),
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
                        $set('cliente_nombre', $cliente?->nombre);
                        $set('cliente_rtn', $cliente?->rtn);
                    }),
                ToggleButtons::make('lista_precio')
                    ->label('Precio')
                    ->options(['publico' => 'Público', 'mayoreo' => 'Mayoreo'])
                    ->colors(['publico' => 'gray', 'mayoreo' => 'warning'])
                    ->inline()
                    ->required()
                    ->live(),
                TextInput::make('cliente_nombre')
                    ->label('Nombre en la factura')
                    ->placeholder('Consumidor final')
                    ->maxLength(150)
                    ->visible(fn (Get $get): bool => $get('tipo_documento') === 'factura'),
                RTNField::make('cliente_rtn')
                    ->visible(fn (Get $get): bool => $get('tipo_documento') === 'factura'),
                Grid::make(2)
                    ->schema([
                        TextInput::make('efectivo_recibido')
                            ->label('Efectivo recibido')
                            ->numeric()
                            ->minValue(0)
                            ->prefix($simbolo)
                            ->placeholder('Exacto')
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                        TextInput::make('monto_tarjeta')
                            ->label('Tarjeta')
                            ->numeric()
                            ->minValue(0)
                            ->prefix($simbolo)
                            ->live(onBlur: true),
                        TextInput::make('referencia_tarjeta')
                            ->label('Voucher')
                            ->maxLength(60),
                        TextInput::make('monto_transferencia')
                            ->label('Transferencia')
                            ->numeric()
                            ->minValue(0)
                            ->prefix($simbolo)
                            ->live(onBlur: true),
                        TextInput::make('referencia_transferencia')
                            ->label('N.º de transferencia')
                            ->maxLength(60),
                    ]),
            ]);
    }

    // ─── Buscador y carrito ───────────────────────────────────────────────

    public function updatedBusqueda(): void
    {
        $termino = trim($this->busqueda);

        $this->resultados = mb_strlen($termino) >= 2 ? $this->buscarProductos($termino) : [];
    }

    /**
     * Enter en el buscador: código de barras de una presentación, código de
     * barras o código interno del producto, o el único resultado por nombre.
     */
    public function agregarPorCodigo(): void
    {
        $termino = trim($this->busqueda);

        if ($termino === '') {
            return;
        }

        $presentacion = ProductoPresentacion::query()
            ->where('codigo_barras', $termino)
            ->where('activo', true)
            ->whereHas('producto', fn (Builder $query): Builder => $query->where('activo', true))
            ->first();

        if ($presentacion !== null) {
            $this->agregar($presentacion->producto_id, $presentacion->id);

            return;
        }

        $producto = Producto::query()
            ->where('activo', true)
            ->where(fn (Builder $query): Builder => $query
                ->where('codigo_barras', $termino)
                ->orWhereRaw('LOWER(codigo) = ?', [mb_strtolower($termino)]))
            ->first();

        if ($producto !== null) {
            $this->agregar($producto->id);

            return;
        }

        $this->resultados = $this->buscarProductos($termino);

        if (count($this->resultados) === 1) {
            $this->agregar($this->resultados[0]['producto_id']);

            return;
        }

        if ($this->resultados === []) {
            Notification::make()
                ->warning()
                ->title("No se encontró \"{$termino}\"")
                ->send();
        }
    }

    public function agregar(int $productoId, ?int $presentacionId = null): void
    {
        foreach ($this->lineas as $clave => $linea) {
            if ((int) $linea['producto_id'] === $productoId && self::idOpcional($linea['presentacion_id']) === $presentacionId) {
                $this->lineas[$clave]['cantidad'] = self::numero($linea['cantidad']) + 1;
                $this->limpiarBusqueda();

                return;
            }
        }

        $this->lineas['l'.Str::lower(Str::random(10))] = [
            'producto_id'     => $productoId,
            'presentacion_id' => $presentacionId,
            'cantidad'        => 1,
            'descuento'       => 0,
        ];

        $this->limpiarBusqueda();
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
    }

    public function vaciar(): void
    {
        $this->reiniciarVenta();
    }

    /**
     * Líneas del carrito con precio, existencia e importes calculados.
     *
     * @return list<array{clave: string, producto: Producto, presentacion: ProductoPresentacion|null, opciones: array<int, string>, unidad_base: string, cantidad: float, descuento_pct: float, precio: float, existencia: float|null, tipo_isv: TipoIsv, bruto: float, descuento: float, total: float, base: float, isv: float}>
     */
    #[Computed]
    public function carrito(): array
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
        $carrito = [];

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
            $descuentoPct = max(0.0, min(self::numero($linea['descuento']), 100.0));
            $precio = PrecioProducto::unitario($producto, $presentacion, $lista);
            $calculo = CalculadoraVenta::linea($precio, $cantidad, $descuentoPct, $producto->tipo_isv->tasa());

            $opciones = [];

            foreach ($producto->presentaciones as $opcion) {
                if ($opcion->activo) {
                    $opcion->setRelation('producto', $producto);
                    $opciones[$opcion->id] = $opcion->etiqueta();
                }
            }

            $carrito[] = [
                'clave'         => (string) $clave,
                'producto'      => $producto,
                'presentacion'  => $presentacion,
                'opciones'      => $opciones,
                'unidad_base'   => $producto->unidadMedida->nombre,
                'cantidad'      => $cantidad,
                'descuento_pct' => $descuentoPct,
                'precio'        => $precio,
                'existencia'    => $producto->controla_stock ? (float) $producto->stock : null,
                'tipo_isv'      => $producto->tipo_isv,
                'bruto'         => $calculo['bruto'],
                'descuento'     => $calculo['descuento'],
                'total'         => $calculo['total'],
                'base'          => $calculo['base'],
                'isv'           => $calculo['isv'],
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

    /**
     * Efectivo que falta cubrir, recibido y cambio (para el panel de cobro).
     *
     * @return array{efectivo_a_cobrar: float, recibido: float|null, cambio: float|null}
     */
    public function cobro(): array
    {
        $total = $this->totales['total'];
        $tarjeta = self::numero($this->venta['monto_tarjeta'] ?? null);
        $transferencia = self::numero($this->venta['monto_transferencia'] ?? null);
        $efectivo = max(0.0, round($total - $tarjeta - $transferencia, 2));
        $recibido = self::montoOpcional($this->venta['efectivo_recibido'] ?? null);

        return [
            'efectivo_a_cobrar' => $efectivo,
            'recibido'          => $recibido,
            'cambio'            => $recibido !== null ? round($recibido - $efectivo, 2) : null,
        ];
    }

    public function descuentoMaximo(): float
    {
        return DescuentoPermitido::para($this->usuario());
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

        $estado = $this->form->getState();

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
            ->body('Total '.lempiras((float) $venta->total).($cambio > 0 ? ' · Cambio '.lempiras($cambio) : ''))
            ->send();

        $this->dispatch('imprimir-ticket', url: route('filament.admin.tickets.venta', ['venta' => $venta]));
        $this->reiniciarVenta();
    }

    // ─── Acciones del turno ──────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            $this->accionAbrirTurno(),
            $this->accionMovimientoCaja(),
            $this->accionCerrarTurno(),
        ];
    }

    private function accionAbrirTurno(): Action
    {
        return Action::make('abrirTurno')
            ->label('Abrir turno')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('success')
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
            });
    }

    private function accionMovimientoCaja(): Action
    {
        return Action::make('movimientoCaja')
            ->label('Entrada / retiro de efectivo')
            ->icon(Heroicon::OutlinedArrowsUpDown)
            ->color('gray')
            ->visible(fn (): bool => $this->turnoId !== null)
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

    private function accionCerrarTurno(): Action
    {
        return Action::make('cerrarTurno')
            ->label('Cerrar turno')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
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
                            ->state(fn (): string => $this->resumenTurno()['cantidad_ventas'].' · '.lempiras($this->resumenTurno()['total_ventas'])),
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

    private function reiniciarVenta(): void
    {
        $this->lineas = [];
        $this->busqueda = '';
        $this->resultados = [];

        $this->form->fill([
            'tipo_documento' => TipoDocumentoVenta::Recibo->value,
            'lista_precio'   => ListaPrecio::Publico->value,
            'cliente_id'     => null,
        ]);

        $this->dispatch('enfocar-busqueda');
    }

    private function limpiarBusqueda(): void
    {
        $this->busqueda = '';
        $this->resultados = [];
        $this->dispatch('enfocar-busqueda');
    }

    private function listaPrecio(): ListaPrecio
    {
        return ListaPrecio::desdeEstado($this->venta['lista_precio'] ?? null) ?? ListaPrecio::Publico;
    }

    /**
     * @return list<array{producto_id: int, etiqueta: string, detalle: string, precio: float}>
     */
    private function buscarProductos(string $termino): array
    {
        $like = '%'.addcslashes($termino, '%_\\').'%';
        $lista = $this->listaPrecio();

        $productos = Producto::query()
            ->with('unidadMedida')
            ->where('activo', true)
            ->where(fn (Builder $query): Builder => $query
                ->where('nombre', 'ilike', $like)
                ->orWhere('codigo', 'ilike', $like)
                ->orWhere('codigo_barras', $termino))
            ->orderBy('nombre')
            ->limit(15)
            ->get();

        $resultados = [];

        foreach ($productos as $producto) {
            $existencia = $producto->controla_stock
                ? 'Existencia: '.cantidadFormato((float) $producto->stock).' '.$producto->unidadMedida->abreviatura
                : 'Servicio';

            $resultados[] = [
                'producto_id' => $producto->id,
                'etiqueta'    => $producto->nombre,
                'detalle'     => ($producto->codigo !== null ? $producto->codigo.' · ' : '').$existencia,
                'precio'      => PrecioProducto::unitario($producto, null, $lista),
            ];
        }

        return $resultados;
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
                descuentoPct: self::numero($linea['descuento']),
            );
        }

        return new DatosVenta(
            lineas: $lineas,
            tipoDocumento: TipoDocumentoVenta::desdeEstado($estado['tipo_documento'] ?? null) ?? TipoDocumentoVenta::Recibo,
            listaPrecio: ListaPrecio::desdeEstado($estado['lista_precio'] ?? null) ?? ListaPrecio::Publico,
            clienteId: self::idOpcional($estado['cliente_id'] ?? null),
            clienteNombre: self::texto($estado['cliente_nombre'] ?? null),
            clienteRtn: self::texto($estado['cliente_rtn'] ?? null),
            efectivoRecibido: self::montoOpcional($estado['efectivo_recibido'] ?? null),
            montoTarjeta: self::numero($estado['monto_tarjeta'] ?? null),
            referenciaTarjeta: self::texto($estado['referencia_tarjeta'] ?? null),
            montoTransferencia: self::numero($estado['monto_transferencia'] ?? null),
            referenciaTransferencia: self::texto($estado['referencia_transferencia'] ?? null),
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
