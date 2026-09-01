<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->string('presentacion');
            $table->string('categoria');
            $table->text('descripcion');
            $table->decimal('precio', 8, 2);
            $table->unsignedInteger('stock');
            $table->unsignedInteger('stock_minimo');
            $table->string('lote');
            $table->string('acento', 7);
            $table->timestamps();

            // El catálogo se filtra por categoría en cada visita (HU01).
            $table->index('categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
