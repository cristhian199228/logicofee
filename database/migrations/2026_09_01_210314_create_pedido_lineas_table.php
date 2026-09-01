<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained()->nullOnDelete();
            // Copia de los datos del producto al momento del pedido: el catálogo
            // puede cambiar de precio o presentación sin alterar el historial.
            $table->string('nombre');
            $table->string('presentacion');
            $table->string('categoria');
            $table->decimal('precio', 8, 2);
            $table->unsignedInteger('cantidad');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_lineas');
    }
};
