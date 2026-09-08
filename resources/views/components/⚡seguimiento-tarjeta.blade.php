<?php

use App\Enums\EstadoPedido;
use App\Models\Pedido;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Tarjeta del tablero: avanza el pedido a la siguiente etapa del flujo y, al
 * entregar, anota quién recibió y cierra el cobro en efectivo (HU03).
 */
new class extends Component
{
    public Pedido $pedido;

    public bool $reciente = false;

    public bool $puedeAvanzar = false;

    #[Validate('nullable|string|max:120', as: 'persona que recibe')]
    public string $recibido_por = '';

    public bool $cobrado = false;

    public function avanzar(): void
    {
        $this->authorize('despachar-pedidos');

        $this->validate();

        $esEntrega = $this->pedido->estado->siguiente() === EstadoPedido::Entregado;
        $recibidoPor = $esEntrega ? (trim($this->recibido_por) ?: null) : null;

        if (! $this->pedido->avanzar($recibidoPor)) {
            $this->dispatch('aviso', mensaje: "El pedido {$this->pedido->codigo} ya fue entregado.");

            return;
        }

        $cobrado = $esEntrega && $this->cobrado && $this->pedido->cobroPendiente();

        if ($cobrado) {
            $this->pedido->cobrar('COB-'.$this->pedido->codigo);
        }

        $this->dispatch('aviso', mensaje: $cobrado
            ? "{$this->pedido->codigo} pasó a {$this->pedido->estado->value} y se cobró {$this->pedido->metodo_pago->value}."
            : "{$this->pedido->codigo} pasó a {$this->pedido->estado->value}.");

        $this->dispatch('pedido-avanzado');
    }
};
?>

<article @class([
    'rounded-2xl border bg-white p-4',
    'border-mostaza-500 ring-4 ring-mostaza-500/20' => $reciente,
    'border-coffee-200' => ! $reciente,
    'opacity-70' => $pedido->estado === EstadoPedido::Entregado,
])>
    <div class="flex items-center justify-between gap-2">
        <p class="font-mono text-xs font-bold text-coffee-700/60">#{{ $pedido->codigo }}</p>
        @if ($reciente)
            <span class="rounded-full bg-mostaza-400 px-2 py-0.5 text-[11px] font-bold text-coffee-900">NUEVO</span>
        @endif
    </div>

    <h3 class="mt-1 font-semibold text-coffee-800">{{ $pedido->cliente_nombre }}</h3>
    <p class="text-xs text-coffee-700/60">{{ $pedido->cliente_tipo }} · {{ $pedido->created_at->translatedFormat('d/m/Y, H:i') }}</p>

    <p class="mt-2 text-sm text-coffee-700/80">
        {{ $pedido->lineas->count() }} {{ $pedido->lineas->count() === 1 ? 'producto' : 'productos' }}
        · {{ $pedido->unidades() }} uds ·
        <x-precio :valor="$pedido->total" class="font-bold text-coffee-800" />
    </p>

    <div class="mt-2 flex flex-wrap items-center gap-2">
        <x-chip-pago :$pedido />
        <span class="rounded-full border border-coffee-300 px-2.5 py-0.5 text-[11px] font-semibold text-coffee-700">
            {{ $pedido->tipo_entrega->value }}
        </span>
    </div>

    @if (($accion = $pedido->estado->accionSiguiente()) && $puedeAvanzar)
        @php($esEntrega = $pedido->estado->siguiente() === EstadoPedido::Entregado)

        <form wire:submit="avanzar">
            @if ($esEntrega)
                <div class="mt-3 space-y-2 rounded-xl border border-coffee-200 bg-coffee-50 p-3">
                    <div>
                        <label for="recibido-{{ $pedido->id }}" class="block text-[11px] font-semibold uppercase tracking-wide text-coffee-700/50">
                            Recibido por
                        </label>
                        <input type="text" id="recibido-{{ $pedido->id }}" wire:model="recibido_por" maxlength="120"
                            placeholder="Nombre de quien recibe"
                            class="mt-1 w-full rounded-lg border border-coffee-300 bg-white px-3 py-1.5 text-sm text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        @error('recibido_por')
                            <p class="mt-1 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($pedido->cobroPendiente())
                        <label class="flex items-center gap-2 text-xs font-semibold text-coffee-800">
                            <input type="checkbox" wire:model="cobrado"
                                class="size-4 rounded border-coffee-300 text-coffee-600 focus:ring-coffee-500/30" />
                            Cobré el pedido en {{ $pedido->metodo_pago->value }}
                        </label>
                    @endif
                </div>
            @endif

            <button type="submit" wire:loading.attr="disabled" wire:target="avanzar"
                class="mt-3 w-full rounded-xl border border-coffee-300 bg-white py-2 text-sm font-semibold text-coffee-700 transition hover:border-coffee-500 hover:bg-coffee-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/20 disabled:opacity-60">
                {{ $accion }} →
            </button>
        </form>
    @elseif ($pedido->estado === EstadoPedido::Entregado)
        <p class="mt-3 text-xs font-medium text-coffee-700/50">
            entregado {{ $pedido->entregado_at?->format('d/m/Y H:i') }}
            @if ($pedido->entrega_recibido_por)
                · recibió {{ $pedido->entrega_recibido_por }}
            @endif
        </p>
    @else
        <p class="mt-3 text-xs font-medium text-coffee-700/50">en curso</p>
    @endif
</article>
