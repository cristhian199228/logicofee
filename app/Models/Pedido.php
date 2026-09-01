<?php

namespace App\Models;

use App\Enums\EstadoPedido;
use Database\Factories\PedidoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo', 'user_id', 'cliente_nombre', 'cliente_telefono', 'cliente_correo',
    'cliente_tipo', 'cliente_direccion', 'observaciones', 'subtotal', 'envio',
    'total', 'estado', 'entregado_at',
])]
class Pedido extends Model
{
    /** @use HasFactory<PedidoFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'codigo';
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PedidoLinea::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    #[Scope]
    protected function enEstado(Builder $query, EstadoPedido $estado): Builder
    {
        return $query->where('estado', $estado->value);
    }

    /** Avanza el pedido a la siguiente etapa del flujo y sella la entrega (HU03). */
    public function avanzar(): bool
    {
        $siguiente = $this->estado->siguiente();

        if ($siguiente === null) {
            return false;
        }

        $this->estado = $siguiente;

        if ($siguiente === EstadoPedido::Entregado) {
            $this->entregado_at = now();
        }

        return $this->save();
    }

    public function unidades(): int
    {
        return (int) $this->lineas->sum('cantidad');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoPedido::class,
            'subtotal' => 'decimal:2',
            'envio' => 'decimal:2',
            'total' => 'decimal:2',
            'entregado_at' => 'datetime',
        ];
    }
}
