<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Compras y entradas de inventario, cada una en su lugar.
 *
 * Lo que antes era "compra" (productos que entran al stock) pasa a ser
 * una entrada de inventario: se renombran sus tablas con todo y datos,
 * su numeración pasa de C- a E-, el kardex apunta a la entrada y quien
 * podía manejar compras ahora también maneja entradas.
 *
 * La tabla "compras" nueva guarda solo facturas y recibos con sus
 * totales (gastos del negocio). No toca el inventario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('compras', 'entradas_inventario');
        Schema::rename('compra_detalles', 'entrada_inventario_detalles');

        Schema::table('entrada_inventario_detalles', function (Blueprint $table): void {
            $table->renameColumn('compra_id', 'entrada_inventario_id');
        });

        Schema::table('entradas_inventario', function (Blueprint $table): void {
            $table->renameColumn('numero_factura_proveedor', 'referencia');
        });

        // El proveedor es opcional: también entra mercadería de otra bodega o de un regalo.
        Schema::table('entradas_inventario', function (Blueprint $table): void {
            $table->unsignedBigInteger('proveedor_id')->nullable()->change();
        });

        $this->renombrarObjetos('entradas_inventario', 'compras_', 'entradas_inventario_');
        $this->renombrarObjetos('entrada_inventario_detalles', 'compra_detalles_', 'entrada_inventario_detalles_', ['_compra_id_' => '_entrada_inventario_id_']);

        $this->renumerar('C', 'E', ['Compra C-' => 'Entrada E-', 'de la compra C-' => 'de la entrada E-']);

        DB::table('movimientos_inventario')->where('documento_type', 'compra')->update(['documento_type' => 'entrada_inventario']);
        DB::table('movimientos_inventario')->where('tipo', 'entrada_compra')->update(['tipo' => 'entrada']);
        DB::table('movimientos_inventario')->where('tipo', 'anulacion_compra')->update(['tipo' => 'anulacion_entrada']);

        $this->moverPermisos();

        Schema::create('compras', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 20)->nullable()->unique()->comment('Numeración interna C-000001');
            $table->string('tipo_documento', 20)->default('factura')->comment('factura o recibo');
            $table->date('fecha');
            $table->string('numero_factura', 40)->nullable()->comment('Correlativo del proveedor: 000-001-01-00001234');
            $table->string('categoria', 30)->comment('En qué se gastó');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->restrictOnDelete();
            $table->string('rtn_proveedor', 14)->nullable()->comment('Copia del RTN al registrar: lo pide el Libro de Compras');

            $table->decimal('gravado_15', 14, 2)->default(0);
            $table->decimal('isv_15', 14, 2)->default(0);
            $table->decimal('gravado_18', 14, 2)->default(0);
            $table->decimal('isv_18', 14, 2)->default(0);
            $table->decimal('exento', 14, 2)->default(0)->comment('En un recibo, todo lo pagado');
            $table->decimal('isv', 14, 2)->default(0)->comment('isv_15 + isv_18');
            $table->decimal('total', 14, 2)->default(0)->comment('Gravados + ISV + exento');
            $table->text('notas')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('fecha');
            $table->index(['tipo_documento', 'fecha']);
            $table->index('proveedor_id');
            $table->index('categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');

        $this->moverPermisos(deshacer: true);

        DB::table('movimientos_inventario')->where('tipo', 'anulacion_entrada')->update(['tipo' => 'anulacion_compra']);
        DB::table('movimientos_inventario')->where('tipo', 'entrada')->update(['tipo' => 'entrada_compra']);
        DB::table('movimientos_inventario')->where('documento_type', 'entrada_inventario')->update(['documento_type' => 'compra']);

        $this->renumerar('E', 'C', ['Entrada E-' => 'Compra C-', 'de la entrada E-' => 'de la compra C-']);

        $this->renombrarObjetos('entrada_inventario_detalles', 'entrada_inventario_detalles_', 'compra_detalles_', ['_entrada_inventario_id_' => '_compra_id_']);
        $this->renombrarObjetos('entradas_inventario', 'entradas_inventario_', 'compras_');

        Schema::table('entradas_inventario', function (Blueprint $table): void {
            $table->renameColumn('referencia', 'numero_factura_proveedor');
        });

        Schema::table('entrada_inventario_detalles', function (Blueprint $table): void {
            $table->renameColumn('entrada_inventario_id', 'compra_id');
        });

        Schema::rename('entrada_inventario_detalles', 'compra_detalles');
        Schema::rename('entradas_inventario', 'compras');
    }

    /**
     * PostgreSQL no renombra índices, llaves ni la secuencia del id al
     * renombrar la tabla: sin esto, la tabla "compras" nueva chocaría
     * con "compras_pkey" o "compras_id_seq".
     *
     * @param array<string, string> $reemplazos además del prefijo: columnas renombradas
     */
    private function renombrarObjetos(string $tabla, string $de, string $a, array $reemplazos = []): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $nuevo = static fn (string $nombre): string => strtr($a.substr($nombre, strlen($de)), $reemplazos);

        // Índices, incluidos el de la llave primaria y los únicos (renombra también su restricción).
        foreach (DB::select('SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ?', [$tabla]) as $indice) {
            if (str_starts_with($indice->indexname, $de)) {
                DB::statement(sprintf('ALTER INDEX "%s" RENAME TO "%s"', $indice->indexname, $nuevo($indice->indexname)));
            }
        }

        // Llaves foráneas.
        foreach (DB::select("SELECT conname FROM pg_constraint WHERE conrelid = ?::regclass AND contype = 'f'", [$tabla]) as $llave) {
            if (str_starts_with($llave->conname, $de)) {
                DB::statement(sprintf('ALTER TABLE "%s" RENAME CONSTRAINT "%s" TO "%s"', $tabla, $llave->conname, $nuevo($llave->conname)));
            }
        }

        $secuencia = DB::selectOne("SELECT pg_get_serial_sequence(?, 'id') AS nombre", [$tabla])?->nombre;

        if (is_string($secuencia) && $secuencia !== '') {
            DB::statement(sprintf('ALTER SEQUENCE %s RENAME TO "%s_id_seq"', $secuencia, $tabla));
        }
    }

    /**
     * Numeración de las entradas (C-000001 ↔ E-000001) y los textos del
     * kardex y del historial de precios que la mencionan.
     *
     * @param array<string, string> $textos
     */
    private function renumerar(string $letraDe, string $letraA, array $textos): void
    {
        DB::table('entradas_inventario')
            ->where('numero', 'like', $letraDe.'-%')
            ->update(['numero' => DB::raw("'{$letraA}' || substr(numero, 2)")]);

        foreach (['movimientos_inventario' => 'descripcion', 'cambios_precio' => 'origen'] as $tabla => $columna) {
            $expresion = $columna;

            foreach ($textos as $texto => $reemplazo) {
                $expresion = "REPLACE({$expresion}, '{$texto}', '{$reemplazo}')";
            }

            DB::table($tabla)
                ->where($columna, 'like', '%'.$letraDe.'-%')
                ->update([$columna => DB::raw($expresion)]);
        }
    }

    /**
     * Quien podía ver, crear o editar compras puede hacer lo mismo con las
     * entradas (y lo sigue pudiendo con las compras nuevas). Confirmar y
     * anular son de inventario: pasan a las entradas.
     */
    private function moverPermisos(bool $deshacer = false): void
    {
        $permisos = (string) config('permission.table_names.permissions', 'permissions');

        if (! Schema::hasTable($permisos)) {
            return;
        }

        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'RestoreAny', 'ForceDelete', 'ForceDeleteAny', 'Replicate', 'Reorder'] as $accion) {
            if ($deshacer) {
                DB::table($permisos)->where('name', "{$accion}:EntradaInventario")->delete();
            } else {
                $this->copiarPermiso("{$accion}:Compra", "{$accion}:EntradaInventario");
            }
        }

        [$de, $a] = $deshacer ? ['EntradaInventario', 'Compra'] : ['Compra', 'EntradaInventario'];

        foreach (['Confirmar', 'Anular'] as $accion) {
            $this->copiarPermiso("{$accion}:{$de}", "{$accion}:{$a}");
            DB::table($permisos)->where('name', "{$accion}:{$de}")->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Crea el permiso destino (mismo guard) y se lo da a los roles y
     * usuarios que tienen el de origen.
     */
    private function copiarPermiso(string $origen, string $destino): void
    {
        $permisos = (string) config('permission.table_names.permissions', 'permissions');
        $pivotes = [
            (string) config('permission.table_names.role_has_permissions', 'role_has_permissions'),
            (string) config('permission.table_names.model_has_permissions', 'model_has_permissions'),
        ];
        $llave = (string) config('permission.column_names.permission_pivot_key', 'permission_id');

        foreach (DB::table($permisos)->where('name', $origen)->get() as $permiso) {
            $destinoId = DB::table($permisos)
                ->where('name', $destino)
                ->where('guard_name', $permiso->guard_name)
                ->value('id')
                ?? DB::table($permisos)->insertGetId([
                    'name'       => $destino,
                    'guard_name' => $permiso->guard_name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            foreach ($pivotes as $pivote) {
                $filas = DB::table($pivote)
                    ->where($llave, $permiso->id)
                    ->get()
                    ->map(fn (object $fila): array => [...(array) $fila, $llave => $destinoId])
                    ->all();

                if ($filas !== []) {
                    DB::table($pivote)->insertOrIgnore($filas);
                }
            }
        }
    }
};
