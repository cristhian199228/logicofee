<?php

use App\Support\Carrito;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Globo con las unidades del pedido en construcción. Vive en la cabecera y se
 * refresca solo cuando cualquier pantalla toca el carrito.
 */
new class extends Component
{
    public int $unidades = 0;

    public function mount(Carrito $carrito): void
    {
        $this->unidades = $carrito->unidades();
    }

    #[On('carrito-actualizado')]
    public function refrescar(Carrito $carrito): void
    {
        $this->unidades = $carrito->unidades();
    }
};
?>

<a href="{{ route('pedidos.create') }}" wire:navigate aria-label="Ver pedido"
    class="relative grid size-11 place-items-center rounded-full bg-coffee-700 text-white transition hover:bg-coffee-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-700/30">
    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 4h2l2.4 11.2a2 2 0 002 1.6h7.7a2 2 0 002-1.6L20.5 7H6" />
        <circle cx="10" cy="20" r="1.4" fill="currentColor" />
        <circle cx="17" cy="20" r="1.4" fill="currentColor" />
    </svg>

    @if ($unidades > 0)
        <span class="absolute -right-1 -top-1 grid size-5 place-items-center rounded-full bg-mostaza-500 text-[11px] font-bold text-coffee-900">{{ $unidades }}</span>
    @endif
</a>
