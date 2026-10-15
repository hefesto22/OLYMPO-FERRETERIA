<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->nullable()->unique()
                ->comment('Código interno/SKU. Si se deja vacío se genera P-000123');
            $table->string('codigo_barras', 60)->nullable()->unique()
                ->comment('Código de barras de la unidad base');
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();

            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
            $table->foreignId('unidad_medida_id')
                ->comment('Unidad base: el stock siempre se lleva en esta unidad')
                ->constrained('unidades_medida')->restrictOnDelete();

            $table->string('tipo_isv', 20)->default('gravado_15');
            $table->decimal('precio_venta', 12, 2)->default(0)
                ->comment('Precio al público por unidad base, ISV incluido');
            $table->decimal('precio_mayoreo', 12, 2)->nullable()
                ->comment('Precio especial (contratistas/mayoreo), ISV incluido');

            // Costos sin ISV y con 4 decimales: un tornillo de una caja de
            // 100 a L 33.50 cuesta L 0.3350.
            $table->decimal('costo_promedio', 14, 4)->default(0)
                ->comment('Costo promedio ponderado por unidad base, sin ISV');
            $table->decimal('ultimo_costo', 14, 4)->default(0)
                ->comment('Costo de la última compra por unidad base, sin ISV');

            // 3 decimales: 12.5 metros de cable, 3.25 libras de clavo.
            $table->decimal('stock', 14, 3)->default(0)
                ->comment('Existencia en unidad base. Solo la modifica InventarioService');
            $table->decimal('stock_minimo', 14, 3)->default(0);
            $table->string('ubicacion', 80)->nullable()->comment('Pasillo / estante');
            $table->string('imagen_path')->nullable();

            $table->boolean('controla_stock')->default(true)
                ->comment('false para servicios: corte de tubo, copia de llave, flete');
            $table->boolean('activo')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['categoria_id', 'activo']);
            $table->index('marca_id');
            $table->index('unidad_medida_id');
        });

        // Búsqueda rápida por nombre con ILIKE '%texto%' en catálogos de
        // miles de productos. pg_trgm es una extensión "trusted": el dueño
        // de la base la puede crear sin ser superusuario (PostgreSQL 13+).
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX productos_nombre_trgm_idx ON productos USING gin (nombre gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
