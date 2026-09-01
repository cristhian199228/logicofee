<?php

namespace App\Models;

use Database\Factories\LoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['producto_id', 'codigo', 'cantidad_inicial', 'cantidad_disponible', 'tostado_at', 'vence_at'])]
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

    /** Lotes que todavía tienen unidades por despachar. */
    #[Scope]
    protected function disponibles(Builder $query): Builder
    {
        return $query->where('cantidad_disponible', '>', 0);
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
            'tostado_at' => 'date',
            'vence_at' => 'date',
        ];
    }
}
