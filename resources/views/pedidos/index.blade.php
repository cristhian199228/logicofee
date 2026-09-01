<x-layouts.app titulo="Historial de Pedidos">

    @if ($pedidos->isEmpty())
        <h1 class="font-display text-3xl font-bold text-coffee-800">Pedidos Realizados</h1>
        <p class="mt-8 rounded-2xl border border-dashed border-coffee-300 p-10 text-center text-sm text-coffee-700/60">
            Todavía no hay pedidos registrados.
            <a href="{{ route('catalogo.index') }}" class="font-semibold text-coffee-600 underline">Ir al catálogo</a>
        </p>
    @else
        <h1 class="font-display text-3xl font-bold text-coffee-700">Pedidos Realizados</h1>
        <p class="mt-1 text-sm text-coffee-700/70">
            {{ auth()->user()->rol->veTodosLosPedidos()
                ? 'Desglose completo de todos los pedidos registrados.'
                : 'Consulta el estado y desglose de tus pedidos.' }}
        </p>

        <span class="mt-4 inline-block rounded-full bg-coffee-200 px-3 py-1 text-xs font-bold text-coffee-800">
            {{ $pedidos->count() }} {{ $pedidos->count() === 1 ? 'pedido' : 'pedidos' }}
        </span>

        <div class="mt-6 space-y-5">
            @foreach ($pedidos as $pedido)
                <x-pedido-tarjeta :$pedido />
            @endforeach
        </div>
    @endif

</x-layouts.app>
