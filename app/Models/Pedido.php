<?php

namespace App\Models;

use App\Enums\EstadoPago;
use App\Enums\EstadoPedido;
use App\Enums\MetodoPago;
use App\Enums\TipoEntrega;
use App\Support\ResultadoPago;
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
    'total', 'estado', 'entregado_at', 'metodo_pago', 'estado_pago',
    'referencia_pago', 'pago_detalle', 'pagado_at', 'tipo_entrega', 'entrega_recibido_por',
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

    /** Pedidos cuyo cobro sigue abierto. */
    #[Scope]
    protected function porCobrar(Builder $query): Builder
    {
        return $query->where('estado_pago', '!=', EstadoPago::Pagado->value);
    }

    /**
     * Avanza el pedido a la siguiente etapa del flujo y sella la entrega,
     * anotando quién la recibió (HU03).
     */
    public function avanzar(?string $recibidoPor = null): bool
    {
        $siguiente = $this->estado->siguiente();

        if ($siguiente === null) {
            return false;
        }

        $this->estado = $siguiente;

        if ($siguiente === EstadoPedido::Entregado) {
            $this->entregado_at = now();
            $this->entrega_recibido_por = $recibidoPor;
        }

        return $this->save();
    }

    public function unidades(): int
    {
        return (int) $this->lineas->sum('cantidad');
    }

    public function pagado(): bool
    {
        return $this->estado_pago === EstadoPago::Pagado;
    }

    /** El efectivo se cobra al entregar; el resto ya debería estar cobrado. */
    public function cobroPendiente(): bool
    {
        return $this->estado_pago === EstadoPago::Pendiente;
    }

    /** Guarda en el pedido lo que respondió la pasarela de pago. */
    public function registrarPago(ResultadoPago $resultado): void
    {
        $this->forceFill([
            'estado_pago' => $resultado->estado,
            'referencia_pago' => $resultado->referencia,
            'pago_detalle' => $resultado->detalle,
            'pagado_at' => $resultado->aprobado() ? now() : null,
        ])->save();
    }

    /**
     * Marca el cobro como recibido. El efectivo entra así cuando el pedido
     * llega al cliente y quien entrega cobra en el momento.
     */
    public function cobrar(?string $referencia = null): void
    {
        $this->forceFill([
            'estado_pago' => EstadoPago::Pagado,
            'referencia_pago' => $referencia ?? $this->referencia_pago,
            'pagado_at' => now(),
        ])->save();
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoPedido::class,
            'metodo_pago' => MetodoPago::class,
            'estado_pago' => EstadoPago::class,
            'tipo_entrega' => TipoEntrega::class,
            'pagado_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'envio' => 'decimal:2',
            'total' => 'decimal:2',
            'entregado_at' => 'datetime',
        ];
    }
}
