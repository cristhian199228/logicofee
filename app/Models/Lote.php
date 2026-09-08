<?php

namespace App\Models;

use App\Enums\ResultadoCalidad;
use Database\Factories\LoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'producto_id', 'codigo', 'cantidad_inicial', 'cantidad_disponible',
    'cantidad_baja', 'baja_nota', 'dado_de_baja_at',
    'tostado_at', 'vence_at', 'calidad', 'calidad_nota', 'evaluado_at', 'evaluado_por',
])]
class Lote extends Model
{
    /** @use HasFactory<LoteFactory> */
    use HasFactory;

    /** Un lote se considera próximo a vencer dentro de estos días. */
    public const DIAS_AVISO_VENCIMIENTO = 30;

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /** Quién registró el resultado del control de calidad (HU07). */
    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluado_por');
    }

    /** Lotes que todavía tienen unidades por despachar. */
    #[Scope]
    protected function disponibles(Builder $query): Builder
    {
        return $query->where('cantidad_disponible', '>', 0);
    }

    /** Lotes con unidades que además pasaron el control de calidad (HU07). */
    #[Scope]
    protected function vendibles(Builder $query): Builder
    {
        return $query->disponibles()->where('calidad', '!=', ResultadoCalidad::Rechazado->value);
    }

    /** Lotes que ya vencieron y siguen ocupando unidades en el almacén. */
    #[Scope]
    protected function vencidos(Builder $query): Builder
    {
        return $query->whereDate('vence_at', '<', now());
    }

    /** Lotes todavía sin resultado de control de calidad. */
    #[Scope]
    protected function sinEvaluar(Builder $query): Builder
    {
        return $query->where('calidad', ResultadoCalidad::Pendiente->value);
    }

    /** Orden de consumo: primero el que vence antes (FIFO por vencimiento). */
    #[Scope]
    protected function porVencimiento(Builder $query): Builder
    {
        return $query->orderBy('vence_at')->orderBy('id');
    }

    public function agotado(): bool
    {
        return $this->cantidad_disponible === 0;
    }

    public function vencido(): bool
    {
        return $this->vence_at->isPast();
    }

    public function porVencer(): bool
    {
        return ! $this->vencido() && $this->diasParaVencer() <= self::DIAS_AVISO_VENCIMIENTO;
    }

    /** El lote rechazado no se despacha ni suma al stock del producto. */
    public function bloqueado(): bool
    {
        return $this->calidad->bloqueaVenta();
    }

    public function evaluado(): bool
    {
        return $this->calidad->evaluable();
    }

    public function tieneMerma(): bool
    {
        return $this->cantidad_baja > 0;
    }

    /**
     * Retira del almacén las unidades mermadas (vencidas o dañadas) y
     * reajusta el stock del producto con lo que quedó vendible.
     */
    public function darDeBaja(int $cantidad, ?string $nota): void
    {
        $this->forceFill([
            'cantidad_disponible' => $this->cantidad_disponible - $cantidad,
            'cantidad_baja' => $this->cantidad_baja + $cantidad,
            'baja_nota' => $nota,
            'dado_de_baja_at' => now(),
        ])->save();

        $this->producto->sincronizarStock();
    }

    /**
     * Registra el resultado del control de calidad y reajusta el stock: al
     * rechazar, las unidades del lote dejan de estar a la venta (HU07).
     */
    public function evaluar(ResultadoCalidad $resultado, ?string $nota, User $responsable): void
    {
        $this->forceFill([
            'calidad' => $resultado,
            'calidad_nota' => $nota,
            'evaluado_at' => now(),
            'evaluado_por' => $responsable->id,
        ])->save();

        $this->producto->sincronizarStock();
    }

    /** Días que faltan para el vencimiento, redondeados hacia arriba. */
    public function diasParaVencer(): int
    {
        return (int) ceil(now()->diffInDays($this->vence_at, absolute: true));
    }

    /** Porcentaje del lote que ya se despachó, para la barra de consumo. */
    public function porcentajeConsumido(): int
    {
        if ($this->cantidad_inicial === 0) {
            return 100;
        }

        return (int) round(
            ($this->cantidad_inicial - $this->cantidad_disponible) / $this->cantidad_inicial * 100
        );
    }

    protected function casts(): array
    {
        return [
            'cantidad_inicial' => 'integer',
            'cantidad_disponible' => 'integer',
            'cantidad_baja' => 'integer',
            'dado_de_baja_at' => 'datetime',
            'tostado_at' => 'date',
            'vence_at' => 'date',
            'calidad' => ResultadoCalidad::class,
            'evaluado_at' => 'datetime',
        ];
    }
}
