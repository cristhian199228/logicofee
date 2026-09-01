<?php

namespace App\Models;

use App\Enums\CategoriaProducto;
use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'slug', 'nombre', 'presentacion', 'categoria', 'descripcion', 'imagen',
    'precio', 'stock', 'stock_minimo', 'acento',
])]
class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PedidoLinea::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    /**
     * Filtra el catálogo por texto libre sobre nombre y descripción (HU01).
     */
    #[Scope]
    protected function buscar(Builder $query, ?string $texto): Builder
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($texto) {
            $query->where('nombre', 'like', '%'.$texto.'%')
                ->orWhere('descripcion', 'like', '%'.$texto.'%');
        });
    }

    #[Scope]
    protected function deCategoria(Builder $query, ?CategoriaProducto $categoria): Builder
    {
        return $categoria === null
            ? $query
            : $query->where('categoria', $categoria->value);
    }

    public function agotado(): bool
    {
        return $this->stock === 0;
    }

    public function bajoStock(): bool
    {
        return ! $this->agotado() && $this->stock <= $this->stock_minimo;
    }

    /** Lote que se despachará primero: el más próximo a vencer con unidades. */
    public function loteActivo(): ?Lote
    {
        if ($this->relationLoaded('lotes')) {
            return $this->lotes
                ->where('cantidad_disponible', '>', 0)
                ->sortBy([['vence_at', 'asc'], ['id', 'asc']])
                ->first();
        }

        return $this->lotes()->disponibles()->porVencimiento()->first();
    }

    public function tieneFoto(): bool
    {
        return $this->imagen !== null && Storage::disk('public')->exists($this->imagen);
    }

    public function urlFoto(): ?string
    {
        return $this->tieneFoto() ? Storage::disk('public')->url($this->imagen) : null;
    }

    /**
     * Descuenta unidades empezando por el lote más próximo a vencer.
     *
     * @throws ValidationException si los lotes no cubren la cantidad pedida.
     */
    public function consumirDeLotes(int $cantidad): void
    {
        $lotes = $this->lotes()->disponibles()->porVencimiento()->lockForUpdate()->get();

        if ($lotes->sum('cantidad_disponible') < $cantidad) {
            throw ValidationException::withMessages([
                'carrito' => "Ya no quedan {$cantidad} unidades de {$this->nombre} en almacén.",
            ]);
        }

        foreach ($lotes as $lote) {
            if ($cantidad === 0) {
                break;
            }

            $tomadas = min($cantidad, $lote->cantidad_disponible);
            $lote->decrement('cantidad_disponible', $tomadas);
            $cantidad -= $tomadas;
        }

        $this->sincronizarStock();
    }

    /** El stock del producto es la suma de lo que queda en sus lotes. */
    public function sincronizarStock(): void
    {
        $this->forceFill(['stock' => (int) $this->lotes()->sum('cantidad_disponible')])->save();
    }

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaProducto::class,
            'precio' => 'decimal:2',
            'stock' => 'integer',
            'stock_minimo' => 'integer',
        ];
    }
}
