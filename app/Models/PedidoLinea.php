<?php

namespace App\Models;

use App\Enums\CategoriaProducto;
use Database\Factories\PedidoLineaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pedido_id', 'producto_id', 'nombre', 'presentacion',
    'categoria', 'precio', 'cantidad',
])]
class PedidoLinea extends Model
{
    /** @use HasFactory<PedidoLineaFactory> */
    use HasFactory;

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function importe(): float
    {
        return (float) $this->precio * $this->cantidad;
    }

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaProducto::class,
            'precio' => 'decimal:2',
            'cantidad' => 'integer',
        ];
    }
}
