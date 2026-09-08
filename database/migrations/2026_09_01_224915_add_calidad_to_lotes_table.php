<?php

use App\Enums\ResultadoCalidad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            // Control de calidad del lote (HU07). Un lote rechazado queda
            // bloqueado para la venta y deja de sumar al stock del producto.
            $table->string('calidad')->default(ResultadoCalidad::Pendiente->value)->after('cantidad_disponible');
            $table->string('calidad_nota')->nullable()->after('calidad');
            $table->timestamp('evaluado_at')->nullable()->after('calidad_nota');
            $table->foreignId('evaluado_por')->nullable()->after('evaluado_at')->constrained('users')->nullOnDelete();

            // El almacén descuenta solo de los lotes que pasaron el control.
            $table->index(['calidad', 'cantidad_disponible']);
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropIndex(['calidad', 'cantidad_disponible']);
            $table->dropConstrainedForeignId('evaluado_por');
            $table->dropColumn(['calidad', 'calidad_nota', 'evaluado_at']);
        });
    }
};
