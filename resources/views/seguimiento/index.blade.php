<x-layouts.app titulo="Seguimiento de pedidos">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Seguimiento de pedidos</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                @if ($puedeAvanzar)
                    Avanza cada pedido por el flujo Pendiente → Preparación → Entregado.
                @else
                    Sigue el avance de tus pedidos por el flujo Pendiente → Preparación → Entregado.
                @endif
                {{ $total }} {{ $total === 1 ? 'pedido' : 'pedidos' }} en total.
            </p>
        </div>
        <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Tablero</span>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        @foreach (\App\Enums\EstadoPedido::cases() as $estado)
            @php($pedidos = $columnas[$estado->value])

            <section class="flex flex-col">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="font-display text-lg font-bold text-coffee-800">{{ $estado->value }}</h2>
                    <span class="rounded-full bg-white px-2.5 py-0.5 text-sm font-bold text-coffee-700">{{ $pedidos->count() }}</span>
                </div>

                <span class="mt-2 block h-1 rounded-full {{ $estado->claseBarra() }}"></span>

                <div class="mt-4 space-y-3">
                    @forelse ($pedidos as $pedido)
                        <x-seguimiento-tarjeta :$pedido :$puedeAvanzar :reciente="$pedido->codigo === $recienRegistrado" />
                    @empty
                        <p class="rounded-2xl border border-dashed border-coffee-300 p-6 text-center text-xs text-coffee-700/50">
                            Sin pedidos en esta etapa.
                        </p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

</x-layouts.app>
