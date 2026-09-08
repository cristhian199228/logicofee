<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Promoción del producto (HU03): destacado en el catálogo, con un
            // descuento opcional y una vigencia con fechas de inicio y fin.
            $table->boolean('destacado')->default(false)->after('acento');
            $table->string('promocion_titulo')->nullable()->after('destacado');
            $table->unsignedTinyInteger('descuento')->default(0)->after('promocion_titulo');
            $table->date('promocion_inicia_at')->nullable()->after('descuento');
            $table->date('promocion_termina_at')->nullable()->after('promocion_inicia_at');

            // El banner del catálogo consulta los destacados en cada visita.
            $table->index('destacado');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['destacado']);
            $table->dropColumn([
                'destacado', 'promocion_titulo', 'descuento',
                'promocion_inicia_at', 'promocion_termina_at',
            ]);
        });
    }
};
