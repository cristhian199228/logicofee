<?php

namespace App\Support;

use App\Models\Producto;
use Illuminate\Support\Collection;

/**
 * Pedido en construcción del usuario, guardado en la sesión.
 *
 * Solo conserva identificadores y cantidades; los datos del producto se leen
 * siempre de la base para que precio y stock estén al día.
 */
class Carrito
{
    private const CLAVE = 'carrito';

    /** @var Collection<int, Producto>|null */
    private ?Collection $productos = null;

    /**
     * Líneas del pedido, en el orden en que se agregaron.
     *
     * @return Collection<int, array{producto: Producto, cantidad: int}>
     */
    public function lineas(): Collection
    {
        $productos = $this->productos();

        return collect($this->cantidades())
            ->filter(fn (int $cantidad, int $productoId) => $productos->has($productoId))
            ->map(fn (int $cantidad, int $productoId) => [
                'producto' => $productos->get($productoId),
                'cantidad' => $cantidad,
            ])
            ->values();
    }

    public function vacio(): bool
    {
        return $this->lineas()->isEmpty();
    }

    public function unidades(): int
    {
        return (int) $this->lineas()->sum('cantidad');
    }

    public function productosDistintos(): int
    {
        return $this->lineas()->count();
    }

    public function cantidadDe(Producto $producto): int
    {
        return $this->cantidades()[$producto->id] ?? 0;
    }

    /** Unidades que aún se pueden agregar sin superar el stock del almacén. */
    public function disponible(Producto $producto): int
    {
        return max(0, $producto->stock - $this->cantidadDe($producto));
    }

    public function subtotal(): float
    {
        return (float) $this->lineas()->sum(
            fn (array $linea) => (float) $linea['producto']->precio * $linea['cantidad']
        );
    }

    public function envio(): float
    {
        return $this->vacio() ? 0.0 : (float) config('logicoffee.envio');
    }

    public function total(): float
    {
        return $this->subtotal() + $this->envio();
    }

    /** Agrega unidades sin superar el stock disponible. */
    public function agregar(Producto $producto, int $cantidad = 1): void
    {
        $this->ajustar($producto, $cantidad);
    }

    /**
     * Suma o resta unidades. Al llegar a cero la línea desaparece; nunca se
     * superan las unidades que quedan en almacén.
     */
    public function ajustar(Producto $producto, int $delta): void
    {
        $cantidades = $this->cantidades();
        $nueva = ($cantidades[$producto->id] ?? 0) + $delta;

        if ($nueva <= 0) {
            $this->quitar($producto);

            return;
        }

        $cantidades[$producto->id] = min($nueva, $producto->stock);

        $this->guardar($cantidades);
    }

    public function quitar(Producto $producto): void
    {
        $cantidades = $this->cantidades();
        unset($cantidades[$producto->id]);

        $this->guardar($cantidades);
    }

    public function vaciar(): void
    {
        session()->forget(self::CLAVE);
        $this->productos = null;
    }

    /**
     * @return array<int, int>
     */
    private function cantidades(): array
    {
        return session()->get(self::CLAVE, []);
    }

    /**
     * @param  array<int, int>  $cantidades
     */
    private function guardar(array $cantidades): void
    {
        session()->put(self::CLAVE, $cantidades);
        $this->productos = null;
    }

    /**
     * @return Collection<int, Producto>
     */
    private function productos(): Collection
    {
        return $this->productos ??= Producto::whereKey(array_keys($this->cantidades()))
            ->get()
            ->keyBy('id');
    }
}
