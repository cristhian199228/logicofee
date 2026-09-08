<?php

use App\Enums\EstadoPedido;
use App\Models\Pedido;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tablero Pendiente → Preparación → Entregado (HU03). El cliente lo ve en modo
 * consulta y solo con sus propios pedidos.
 */
new #[Layout('components.layouts.app', ['titulo' => 'Seguimiento de pedidos'])] class extends Component
{
    public ?string $aviso = null;

    public ?string $recienRegistrado = null;

    public function mount(): void
    {
        $this->recienRegistrado = session('ultimo_pedido');
    }

    /**
     * @return Collection<int, Pedido>
     */
    #[Computed]
    public function pedidos(): Collection
    {
        $usuario = auth()->user();

        return Pedido::query()
            ->with('lineas')
            ->unless($usuario->rol->veTodosLosPedidos(), fn ($query) => $query->whereBelongsTo($usuario, 'usuario'))
            ->latest()
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<string, Collection<int, Pedido>>
     */
    #[Computed]
    public function columnas(): Collection
    {
        return collect(EstadoPedido::cases())->mapWithKeys(fn (EstadoPedido $estado) => [
            $estado->value => $this->pedidos()->where('estado', $estado),
        ]);
    }

    #[Computed]
    public function puedeAvanzar(): bool
    {
        return auth()->user()->rol->puedeDespacharPedidos();
    }

    #[On('pedido-avanzado')]
    public function refrescar(): void
    {
        unset($this->pedidos, $this->columnas);
    }

    #[On('aviso')]
    public function mostrarAviso(string $mensaje): void
    {
        $this->aviso = $mensaje;
    }
};
?>

<div>
    <x-aviso :mensaje="$aviso" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Seguimiento de pedidos</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                @if ($this->puedeAvanzar)
                    Avanza cada pedido por el flujo Pendiente → Preparación → Entregado.
                @else
                    Sigue el avance de tus pedidos por el flujo Pendiente → Preparación → Entregado.
                @endif
                {{ $this->pedidos->count() }} {{ $this->pedidos->count() === 1 ? 'pedido' : 'pedidos' }} en total.
            </p>
        </div>
        <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Tablero</span>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        @foreach (EstadoPedido::cases() as $estado)
            @php($pedidos = $this->columnas[$estado->value])

            <section class="flex flex-col">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="font-display text-lg font-bold text-coffee-800">{{ $estado->value }}</h2>
                    <span class="rounded-full bg-white px-2.5 py-0.5 text-sm font-bold text-coffee-700">{{ $pedidos->count() }}</span>
                </div>

                <span class="mt-2 block h-1 rounded-full {{ $estado->claseBarra() }}"></span>

                <div class="mt-4 space-y-3">
                    @forelse ($pedidos as $pedido)
                        <livewire:seguimiento-tarjeta :$pedido :puede-avanzar="$this->puedeAvanzar"
                            :reciente="$pedido->codigo === $recienRegistrado"
                            :key="'seguimiento-'.$pedido->id.'-'.$pedido->estado->value" />
                    @empty
                        <p class="rounded-2xl border border-dashed border-coffee-300 p-6 text-center text-xs text-coffee-700/50">
                            Sin pedidos en esta etapa.
                        </p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
