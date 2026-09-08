<?php

use App\Enums\EstadoPago;
use App\Enums\MetodoPago;
use App\Enums\TipoEntrega;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Cómo se paga el pedido y en qué estado quedó el cobro.
            $table->string('metodo_pago')->default(MetodoPago::Efectivo->value)->after('total');
            $table->string('estado_pago')->default(EstadoPago::Pendiente->value)->after('metodo_pago');
            // Código de la operación y medio enmascarado; nunca se guarda la
            // tarjeta completa, solo sus cuatro últimos dígitos.
            $table->string('referencia_pago')->nullable()->after('estado_pago');
            $table->string('pago_detalle')->nullable()->after('referencia_pago');
            $table->timestamp('pagado_at')->nullable()->after('pago_detalle');

            // Cómo se entrega y quién lo recibió.
            $table->string('tipo_entrega')->default(TipoEntrega::Delivery->value)->after('pagado_at');
            $table->string('entrega_recibido_por')->nullable()->after('tipo_entrega');

            // El panel comercial agrupa las ventas por método y estado de cobro.
            $table->index(['metodo_pago', 'estado_pago']);
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['metodo_pago', 'estado_pago']);
            $table->dropColumn([
                'metodo_pago', 'estado_pago', 'referencia_pago', 'pago_detalle',
                'pagado_at', 'tipo_entrega', 'entrega_recibido_por',
            ]);
        });
    }
};
