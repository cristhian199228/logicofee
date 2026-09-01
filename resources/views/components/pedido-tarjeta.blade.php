@props(['pedido'])

<article class="rounded-2xl border border-coffee-200 bg-coffee-50 p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="font-display text-xl font-bold text-coffee-700">{{ $pedido->codigo }}</h3>
            <p class="text-xs text-coffee-700/60">{{ $pedido->created_at->translatedFormat('d/m/Y, H:i') }}</p>
        </div>
        <x-chip-estado :estado="$pedido->estado" />
    </div>

    <h4 class="mt-5 border-b border-coffee-300 pb-1 text-sm font-bold text-coffee-800">Datos del Cliente</h4>
    <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
        <div class="flex gap-2"><dt class="text-coffee-700/60">Cliente:</dt><dd class="font-medium text-coffee-800">{{ $pedido->cliente_nombre }}</dd></div>
        <div class="flex gap-2"><dt class="text-coffee-700/60">Teléfono:</dt><dd class="font-medium text-coffee-800">{{ $pedido->cliente_telefono ?: '—' }}</dd></div>
        <div class="flex gap-2"><dt class="text-coffee-700/60">Tipo:</dt><dd class="font-medium text-coffee-800">{{ $pedido->cliente_tipo }}</dd></div>
        <div class="flex gap-2"><dt class="text-coffee-700/60">Correo:</dt><dd class="font-medium text-coffee-800">{{ $pedido->cliente_correo ?: '—' }}</dd></div>

        @if ($pedido->cliente_direccion)
            <div class="flex gap-2 sm:col-span-2"><dt class="text-coffee-700/60">Entrega:</dt><dd class="font-medium text-coffee-800">{{ $pedido->cliente_direccion }}</dd></div>
        @endif

        @if ($pedido->observaciones)
            <div class="flex gap-2 sm:col-span-2"><dt class="text-coffee-700/60">Notas:</dt><dd class="font-medium text-coffee-800">{{ $pedido->observaciones }}</dd></div>
        @endif
    </dl>

    <h4 class="mt-5 border-b border-coffee-300 pb-1 text-sm font-bold text-coffee-800">Productos Seleccionados</h4>
    <ul class="mt-2 divide-y divide-coffee-200">
        @foreach ($pedido->lineas as $linea)
            <li class="flex items-baseline justify-between gap-3 py-1">
                <span class="text-sm text-coffee-800">
                    <span class="font-bold text-coffee-600">{{ $linea->cantidad }}x</span>
                    {{ $linea->nombre }} <span class="text-coffee-700/60">({{ $linea->categoria->etiqueta() }})</span>
                </span>
                <x-precio :valor="$linea->importe()" class="shrink-0 text-sm font-semibold text-coffee-800" />
            </li>
        @endforeach
    </ul>

    <div class="mt-4 flex items-baseline justify-end gap-3 border-t border-coffee-300 pt-3">
        <span class="text-sm font-semibold text-coffee-700/70">Total del Pedido:</span>
        <x-precio :valor="$pedido->total" class="font-display text-xl font-bold text-coffee-800" />
    </div>
</article>
