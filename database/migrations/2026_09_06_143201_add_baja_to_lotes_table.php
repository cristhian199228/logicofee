<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            // Merma del lote: unidades que logística retira del almacén por
            // vencimiento o daño y que dejan de sumar al stock del producto.
            $table->unsignedInteger('cantidad_baja')->default(0)->after('cantidad_disponible');
            $table->string('baja_nota')->nullable()->after('cantidad_baja');
            $table->timestamp('dado_de_baja_at')->nullable()->after('baja_nota');
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn(['cantidad_baja', 'baja_nota', 'dado_de_baja_at']);
        });
    }
};
