<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 150);
            $table->string('rtn', 14)->nullable()->unique();
            $table->string('telefono', 8)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('contacto', 120)->nullable()->comment('Persona de contacto / vendedor');
            $table->string('direccion', 255)->nullable();
            $table->unsignedSmallInteger('dias_credito')->default(0);
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
