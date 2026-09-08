<x-layouts.app titulo="Panel de indicadores">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Panel de indicadores</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Resumen de pedidos, ventas y productos más vendidos para la toma de decisiones.
            </p>
        </div>
        <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Gerencia</span>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-indicador etiqueta="Ventas registradas" :valor="'$'.number_format($ventasTotales, 2)"
            detalle="Total facturado, envío incluido" />
        <x-indicador etiqueta="Pedidos" :valor="$totalPedidos" detalle="Registrados en el sistema" />
        <x-indicador etiqueta="Ticket promedio" :valor="'$'.number_format($ticketPromedio, 2)" detalle="Venta media por pedido" />
        <x-indicador etiqueta="Unidades vendidas" :valor="$unidadesVendidas" detalle="Bolsas despachadas" />
        <x-indicador etiqueta="Por cobrar" :valor="'$'.number_format($porCobrar, 2)"
            detalle="Pedidos con el cobro abierto"
            :tono="$porCobrar > 0 ? 'aviso' : 'neutro'" />
    </dl>

    <section class="mt-8">
        <h2 class="font-display text-xl font-bold text-coffee-800">Pedidos por estado</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            @foreach ($porEstado as $fila)
                <article class="rounded-2xl border border-coffee-200 bg-white p-5">
                    <x-chip-estado :estado="$fila['estado']" />
                    <p class="mt-3 font-display text-3xl font-bold text-coffee-800">{{ $fila['cantidad'] }}</p>
                    <p class="text-xs text-coffee-700/60">
                        {{ $fila['cantidad'] === 1 ? 'pedido' : 'pedidos' }} ·
                        <x-precio :valor="$fila['total']" class="font-semibold text-coffee-800" />
                    </p>
                    <span class="mt-3 block h-1 rounded-full {{ $fila['estado']->claseBarra() }}"></span>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mt-10 rounded-2xl border border-coffee-200 bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-xl font-bold text-coffee-800">Ventas por periodo</h2>
                <p class="mt-1 text-sm text-coffee-700/70">{{ $periodo->titulo() }} · últimos {{ $periodo->tramos() }} tramos</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach (\App\Enums\PeriodoReporte::cases() as $opcion)
                    <a href="{{ route('reportes.index', ['periodo' => $opcion->value]) }}"
                        @class([
                            'rounded-full border px-4 py-1.5 text-sm font-semibold transition',
                            'border-coffee-700 bg-coffee-700 text-white' => $periodo === $opcion,
                            'border-coffee-300 bg-white text-coffee-700 hover:border-coffee-500' => $periodo !== $opcion,
                        ])>{{ $opcion->titulo() }}</a>
                @endforeach
            </div>
        </div>

        @if ($ventaMaxima === 0.0)
            <p class="mt-6 rounded-xl border border-dashed border-coffee-300 p-10 text-center text-sm text-coffee-700/60">
                Todavía no hay ventas en este periodo.
            </p>
        @else
            <div class="mt-6 flex items-end gap-2 overflow-x-auto sm:gap-4">
                @foreach ($ventas as $tramo)
                    <div class="flex min-w-16 flex-1 flex-col items-center gap-2">
                        <span class="text-xs font-bold text-coffee-800">
                            {{ $tramo['total'] > 0 ? '$'.number_format($tramo['total'], 0) : '—' }}
                        </span>

                        <div class="flex h-40 w-full items-end">
                            <div class="w-full rounded-t-lg bg-coffee-500 transition"
                                style="height: {{ max(2, (int) round($tramo['total'] / $ventaMaxima * 100)) }}%"
                                role="img"
                                aria-label="{{ $tramo['etiqueta'] }}: {{ number_format($tramo['total'], 2) }} en {{ $tramo['pedidos'] }} pedidos"></div>
                        </div>

                        <span class="text-center text-[11px] font-semibold text-coffee-700/60">{{ $tramo['etiqueta'] }}</span>
                        <span class="text-[11px] text-coffee-700/40">{{ $tramo['pedidos'] }} ped.</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <div class="mt-10 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-coffee-200 bg-white p-6">
            <h2 class="font-display text-xl font-bold text-coffee-800">Productos más vendidos</h2>

            @if ($masVendidos->isEmpty())
                <p class="mt-4 rounded-xl border border-dashed border-coffee-300 p-8 text-center text-sm text-coffee-700/60">
                    Aún no hay productos vendidos.
                </p>
            @else
                <ol class="mt-4 space-y-3">
                    @foreach ($masVendidos as $indice => $producto)
                        <li class="flex items-center gap-4">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-coffee-100 font-display text-sm font-bold text-coffee-700">
                                {{ $indice + 1 }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-coffee-800">{{ $producto['nombre'] }}</p>
                                <p class="text-xs text-coffee-700/60">{{ $producto['presentacion'] }}</p>

                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-coffee-100">
                                    <div class="h-full rounded-full bg-mostaza-500"
                                        style="width: {{ (int) round($producto['unidades'] / $masVendidos->max('unidades') * 100) }}%"></div>
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <p class="font-display text-lg font-bold text-coffee-800">{{ $producto['unidades'] }}</p>
                                <p class="text-xs text-coffee-700/60">uds · <x-precio :valor="$producto['importe']" /></p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="rounded-2xl border border-coffee-200 bg-white p-6">
            <h2 class="font-display text-xl font-bold text-coffee-800">Alertas de almacén y producción</h2>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <x-indicador etiqueta="Lotes bloqueados" :valor="$lotesBloqueados"
                    detalle="Rechazados en control de calidad"
                    :tono="$lotesBloqueados > 0 ? 'alerta' : 'neutro'" />
                <x-indicador etiqueta="Lotes por controlar" :valor="$lotesSinEvaluar"
                    detalle="Con unidades y sin evaluar"
                    :tono="$lotesSinEvaluar > 0 ? 'aviso' : 'neutro'" />
            </div>

            <h3 class="mt-6 border-b border-coffee-200 pb-1 text-sm font-bold text-coffee-800">
                Stock en el mínimo o agotado
            </h3>

            @if ($alertasDeStock->isEmpty())
                <p class="mt-3 text-sm text-coffee-700/60">Todo el catálogo está por encima de su stock mínimo.</p>
            @else
                <ul class="mt-3 divide-y divide-coffee-200">
                    @foreach ($alertasDeStock as $producto)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate text-sm text-coffee-800">
                                {{ $producto->nombre }}
                                <span class="text-coffee-700/60">({{ $producto->presentacion }})</span>
                            </span>

                            <span @class([
                                'shrink-0 rounded-full px-3 py-1 text-xs font-bold',
                                'bg-ladrillo-500/10 text-ladrillo-500' => $producto->agotado(),
                                'bg-mostaza-400/25 text-coffee-900' => ! $producto->agotado(),
                            ])>
                                {{ $producto->stock }} uds · mínimo {{ $producto->stock_minimo }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

</x-layouts.app>
