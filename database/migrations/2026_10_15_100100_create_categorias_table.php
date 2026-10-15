<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                ->comment('Categoría padre; null = categoría principal')
                ->constrained('categorias')->nullOnDelete();
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
