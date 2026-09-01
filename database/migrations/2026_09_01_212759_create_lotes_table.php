<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained()->cascadeOnDelete();
            $table->string('codigo')->unique();
            $table->unsignedInteger('cantidad_inicial');
            $table->unsignedInteger('cantidad_disponible');
            $table->date('tostado_at');
            $table->date('vence_at');
            $table->timestamps();

            // Los pedidos consumen el lote más antiguo que aún tenga unidades.
            $table->index(['producto_id', 'vence_at']);
        });

        // El lote pasa a ser una entidad propia: un producto tiene varios.
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('lote');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('lote')->default('');
        });

        Schema::dropIfExists('lotes');
    }
};
