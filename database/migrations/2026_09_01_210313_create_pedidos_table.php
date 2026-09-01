<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cliente_nombre');
            $table->string('cliente_telefono');
            $table->string('cliente_correo')->nullable();
            $table->string('cliente_tipo');
            $table->string('cliente_direccion')->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('envio', 8, 2);
            $table->decimal('total', 10, 2);
            $table->string('estado');
            $table->timestamp('entregado_at')->nullable();
            $table->timestamps();

            // El tablero de seguimiento agrupa por estado y ordena por fecha.
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
