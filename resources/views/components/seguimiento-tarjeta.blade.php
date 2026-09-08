@props(['pedido', 'reciente' => false, 'puedeAvanzar' => false])

<article @class([
    'rounded-2xl border bg-white p-4',
    'border-mostaza-500 ring-4 ring-mostaza-500/20' => $reciente,
    'border-coffee-200' => ! $reciente,
    'opacity-70' => $pedido->estado === \App\Enums\EstadoPedido::Entregado,
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
        @php($esEntrega = $pedido->estado->siguiente() === \App\Enums\EstadoPedido::Entregado)

        <form method="POST" action="{{ route('pedidos.avance.store', $pedido) }}">
            @csrf

            @if ($esEntrega)
                <div class="mt-3 space-y-2 rounded-xl border border-coffee-200 bg-coffee-50 p-3">
                    <div>
                        <label for="recibido-{{ $pedido->id }}" class="block text-[11px] font-semibold uppercase tracking-wide text-coffee-700/50">
                            Recibido por
                        </label>
                        <input type="text" id="recibido-{{ $pedido->id }}" name="recibido_por" maxlength="120"
                            placeholder="Nombre de quien recibe"
                            class="mt-1 w-full rounded-lg border border-coffee-300 bg-white px-3 py-1.5 text-sm text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                    </div>

                    @if ($pedido->cobroPendiente())
                        <label class="flex items-center gap-2 text-xs font-semibold text-coffee-800">
                            <input type="checkbox" name="cobrado" value="1"
                                class="size-4 rounded border-coffee-300 text-coffee-600 focus:ring-coffee-500/30" />
                            Cobré el pedido en {{ $pedido->metodo_pago->value }}
                        </label>
                    @endif
                </div>
            @endif

            <button type="submit"
                class="mt-3 w-full rounded-xl border border-coffee-300 bg-white py-2 text-sm font-semibold text-coffee-700 transition hover:border-coffee-500 hover:bg-coffee-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/20">
                {{ $accion }} →
            </button>
        </form>
    @elseif ($pedido->estado === \App\Enums\EstadoPedido::Entregado)
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
