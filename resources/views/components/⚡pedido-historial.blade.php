<?php

use App\Models\Pedido;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Historial de pedidos. El cliente solo ve los suyos; quien registra cobros
 * cierra el pago desde la misma tarjeta.
 */
new #[Layout('components.layouts.app', ['titulo' => 'Historial de Pedidos'])] class extends Component
{
    public ?string $aviso = null;

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

    #[Computed]
    public function porCobrar(): float
    {
        return (float) $this->pedidos()->filter->cobroPendiente()->sum('total');
    }

    #[Computed]
    public function puedeCobrar(): bool
    {
        return auth()->user()->rol->puedeRegistrarCobros();
    }

    /**
     * Cierre manual del cobro: el efectivo que entró al entregar o la
     * operación que el cliente rehízo después de un rechazo.
     */
    public function cobrar(int $pedidoId): void
    {
        abort_unless($this->puedeCobrar(), 403);

        $pedido = $this->pedidos()->firstOrFail(fn (Pedido $pedido) => $pedido->id === $pedidoId);

        if ($pedido->pagado()) {
            $this->aviso = "El pedido {$pedido->codigo} ya estaba cobrado.";

            return;
        }

        $pedido->cobrar('COB-'.$pedido->codigo);

        unset($this->pedidos, $this->porCobrar);

        $this->aviso = "Se registró el cobro de {$pedido->codigo} ({$pedido->metodo_pago->value}).";
    }
};
?>

<div>
    <x-aviso :mensaje="$aviso" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-700">Pedidos Realizados</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                {{ auth()->user()->rol->veTodosLosPedidos()
                    ? 'Desglose completo de todos los pedidos registrados.'
                    : 'Consulta el estado y desglose de tus pedidos.' }}
            </p>
        </div>
        <x-reporte-descargas seccion="historial" />
    </div>

    @if ($this->pedidos->isEmpty())
        <p class="mt-8 rounded-2xl border border-dashed border-coffee-300 p-10 text-center text-sm text-coffee-700/60">
            Todavía no hay pedidos registrados.
            <a href="{{ route('catalogo.index') }}" wire:navigate class="font-semibold text-coffee-600 underline">Ir al catálogo</a>
        </p>
    @else
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="inline-block rounded-full bg-coffee-200 px-3 py-1 text-xs font-bold text-coffee-800">
                {{ $this->pedidos->count() }} {{ $this->pedidos->count() === 1 ? 'pedido' : 'pedidos' }}
            </span>

            @if ($this->porCobrar > 0)
                <span class="inline-block rounded-full bg-mostaza-400 px-3 py-1 text-xs font-bold text-coffee-900">
                    <x-precio :valor="$this->porCobrar" /> por cobrar
                </span>
            @endif
        </div>

        <div class="mt-6 space-y-5">
            @foreach ($this->pedidos as $pedido)
                <x-pedido-tarjeta :$pedido :puedeCobrar="$this->puedeCobrar" wire:key="pedido-{{ $pedido->id }}" />
            @endforeach
        </div>
    @endif
</div>
