<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_medida', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->string('abreviatura', 10)->unique();
            $table->boolean('permite_decimales')->default(false)
                ->comment('true para metro, libra, galón… (se puede vender 1.5)');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_medida');
    }
};
