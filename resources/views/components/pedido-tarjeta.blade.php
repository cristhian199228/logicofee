@props(['pedido', 'puedeCobrar' => false])

<article class="rounded-2xl border border-coffee-200 bg-coffee-50 p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="font-display text-xl font-bold text-coffee-700">{{ $pedido->codigo }}</h3>
            <p class="text-xs text-coffee-700/60">{{ $pedido->created_at->translatedFormat('d/m/Y, H:i') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-chip-pago :$pedido />
            <x-chip-estado :estado="$pedido->estado" />
        </div>
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

    <h4 class="mt-5 border-b border-coffee-300 pb-1 text-sm font-bold text-coffee-800">Pago y entrega</h4>
    <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
        <div class="flex gap-2">
            <dt class="text-coffee-700/60">Forma de pago:</dt>
            <dd class="font-medium text-coffee-800">
                {{ $pedido->metodo_pago->value }}
                <span class="text-coffee-700/60">({{ $pedido->estado_pago->descripcion() }})</span>
            </dd>
        </div>
        <div class="flex gap-2">
            <dt class="text-coffee-700/60">Forma de entrega:</dt>
            <dd class="font-medium text-coffee-800">{{ $pedido->tipo_entrega->value }}</dd>
        </div>

        @if ($pedido->pago_detalle)
            <div class="flex gap-2"><dt class="text-coffee-700/60">Medio:</dt><dd class="font-medium text-coffee-800">{{ $pedido->pago_detalle }}</dd></div>
        @endif

        @if ($pedido->referencia_pago)
            <div class="flex gap-2">
                <dt class="text-coffee-700/60">Operación:</dt>
                <dd class="font-mono font-medium text-coffee-800">{{ $pedido->referencia_pago }}</dd>
            </div>
        @endif

        @if ($pedido->pagado_at)
            <div class="flex gap-2">
                <dt class="text-coffee-700/60">Cobrado:</dt>
                <dd class="font-medium text-coffee-800">{{ $pedido->pagado_at->translatedFormat('d/m/Y, H:i') }}</dd>
            </div>
        @endif

        @if ($pedido->entregado_at)
            <div class="flex gap-2 sm:col-span-2">
                <dt class="text-coffee-700/60">Entregado:</dt>
                <dd class="font-medium text-coffee-800">
                    {{ $pedido->entregado_at->translatedFormat('d/m/Y, H:i') }}
                    @if ($pedido->entrega_recibido_por)
                        · recibió {{ $pedido->entrega_recibido_por }}
                    @endif
                </dd>
            </div>
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

    <div class="mt-4 flex flex-wrap items-center justify-end gap-3 border-t border-coffee-300 pt-3">
        @if ($puedeCobrar && ! $pedido->pagado())
            <form method="POST" action="{{ route('pedidos.pago.update', $pedido) }}" class="mr-auto">
                @csrf @method('PATCH')
                <button type="submit"
                    class="rounded-full border-2 border-coffee-500 px-4 py-1.5 text-sm font-semibold text-coffee-600 transition hover:bg-coffee-500 hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/25">
                    Registrar cobro
                </button>
            </form>
        @endif

        <span class="text-sm font-semibold text-coffee-700/70">Total del Pedido:</span>
        <x-precio :valor="$pedido->total" class="font-display text-xl font-bold text-coffee-800" />
    </div>
</article>
