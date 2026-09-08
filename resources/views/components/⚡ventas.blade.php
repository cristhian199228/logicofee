<?php

use App\Enums\PeriodoReporte;
use App\Support\ResumenComercial;
use App\Support\ResumenIndicadores;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Panel del área de Marketing y Ventas: cómo evolucionan las ventas, quién
 * compra y qué está rindiendo el catálogo en promoción.
 */
new #[Layout('components.layouts.app', ['titulo' => 'Panel comercial'])] class extends Component
{
    #[Url(except: '')]
    public string $periodo = '';

    #[Computed]
    public function periodoElegido(): PeriodoReporte
    {
        return PeriodoReporte::tryFrom($this->periodo) ?? PeriodoReporte::Semana;
    }

    #[Computed]
    public function indicadores(): ResumenIndicadores
    {
        return new ResumenIndicadores($this->periodoElegido());
    }

    #[Computed]
    public function comercial(): ResumenComercial
    {
        return new ResumenComercial;
    }
};
?>

@php
    $periodo = $this->periodoElegido;
    $indicadores = $this->indicadores;
    $comercial = $this->comercial;
    $ventas = $indicadores->ventasPorTramo();
    $ventaMaxima = (float) $ventas->max('total');
    $ventasTotales = $indicadores->ventasTotales();
    $totalPedidos = $indicadores->totalPedidos();
    $ticketPromedio = $indicadores->ticketPromedio();
    $unidadesVendidas = $indicadores->unidadesVendidas();
    $porEstado = $indicadores->pedidosPorEstado();
    $masVendidos = $indicadores->productosMasVendidos();
    $mejoresClientes = $comercial->mejoresClientes();
    $porTipoDeCliente = $comercial->ventasPorTipoDeCliente();
    $clientesAtendidos = $comercial->clientesAtendidos();
    $promociones = $comercial->promocionesVigentes();
    $sinVentas = $comercial->productosSinVentas();
    $descuento = $comercial->descuentoEntregado();
    $porMetodoDePago = $comercial->ventasPorMetodoDePago();
    $porCobrar = $comercial->porCobrar();
    $puedeGestionarPromociones = auth()->user()->rol->puedeGestionarPromociones();
@endphp

<div>


    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Panel comercial</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Evolución de las ventas, clientes que más compran y rendimiento de las promociones.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Marketing y Ventas</span>
            <x-reporte-descargas seccion="ventas" :parametros="['periodo' => $periodo->value]" />
        </div>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-indicador etiqueta="Ventas registradas" :valor="'$'.number_format($ventasTotales, 2)"
            :detalle="$totalPedidos.' pedidos'" />
        <x-indicador etiqueta="Ticket promedio" :valor="'$'.number_format($ticketPromedio, 2)"
            detalle="Venta media por pedido" />
        <x-indicador etiqueta="Clientes atendidos" :valor="$clientesAtendidos"
            :detalle="$unidadesVendidas.' unidades despachadas'" />
        <x-indicador etiqueta="Descuento entregado" :valor="'$'.number_format($descuento['descuento'], 2)"
            :detalle="$descuento['unidades'].' uds vendidas en promoción'"
            :tono="$descuento['descuento'] > 0 ? 'aviso' : 'neutro'" />
    </dl>

    <section class="mt-8 rounded-2xl border border-coffee-200 bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-xl font-bold text-coffee-800">Ventas por periodo</h2>
                <p class="mt-1 text-sm text-coffee-700/70">{{ $periodo->titulo() }} · últimos {{ $periodo->tramos() }} tramos</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach (\App\Enums\PeriodoReporte::cases() as $opcion)
                    <button type="button" wire:click="$set('periodo', @js($opcion->value))"
                        @class([
                            'rounded-full border px-4 py-1.5 text-sm font-semibold transition',
                            'border-coffee-700 bg-coffee-700 text-white' => $periodo === $opcion,
                            'border-coffee-300 bg-white text-coffee-700 hover:border-coffee-500' => $periodo !== $opcion,
                        ])>{{ $opcion->titulo() }}</button>
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
            <h2 class="font-display text-xl font-bold text-coffee-800">Clientes que más compran</h2>

            @if ($mejoresClientes->isEmpty())
                <p class="mt-4 rounded-xl border border-dashed border-coffee-300 p-8 text-center text-sm text-coffee-700/60">
                    Todavía no hay pedidos registrados.
                </p>
            @else
                <ol class="mt-4 space-y-3">
                    @foreach ($mejoresClientes as $indice => $cliente)
                        <li class="flex items-center gap-4">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-coffee-100 font-display text-sm font-bold text-coffee-700">
                                {{ $indice + 1 }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-coffee-800">{{ $cliente['nombre'] }}</p>
                                <p class="text-xs text-coffee-700/60">
                                    {{ $cliente['tipo'] }} · {{ $cliente['pedidos'] }}
                                    {{ $cliente['pedidos'] === 1 ? 'pedido' : 'pedidos' }}
                                </p>

                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-coffee-100">
                                    <div class="h-full rounded-full bg-mostaza-500"
                                        style="width: {{ (int) round($cliente['total'] / $mejoresClientes->max('total') * 100) }}%"></div>
                                </div>
                            </div>

                            <x-precio :valor="$cliente['total']" class="shrink-0 font-display text-lg font-bold text-coffee-800" />
                        </li>
                    @endforeach
                </ol>
            @endif

            <h3 class="mt-6 border-b border-coffee-200 pb-1 text-sm font-bold text-coffee-800">Ventas por tipo de cliente</h3>

            @if ($porTipoDeCliente->isEmpty())
                <p class="mt-3 text-sm text-coffee-700/60">Sin ventas para repartir.</p>
            @else
                <ul class="mt-3 divide-y divide-coffee-200">
                    @foreach ($porTipoDeCliente as $tipo)
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span class="min-w-0 truncate text-coffee-800">{{ $tipo['tipo'] }}</span>
                            <span class="shrink-0 text-coffee-700/60">
                                {{ $tipo['pedidos'] }} ped. ·
                                <x-precio :valor="$tipo['total']" class="font-semibold text-coffee-800" />
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

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
                            </div>

                            <div class="shrink-0 text-right">
                                <p class="font-display text-lg font-bold text-coffee-800">{{ $producto['unidades'] }}</p>
                                <p class="text-xs text-coffee-700/60">uds · <x-precio :valor="$producto['importe']" /></p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            <h3 class="mt-6 border-b border-coffee-200 pb-1 text-sm font-bold text-coffee-800">
                Sin ventas todavía
            </h3>

            @if ($sinVentas->isEmpty())
                <p class="mt-3 text-sm text-coffee-700/60">Todo el catálogo ha vendido al menos una vez.</p>
            @else
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($sinVentas as $producto)
                        <li class="rounded-full border border-coffee-300 px-3 py-1 text-xs font-semibold text-coffee-700">
                            {{ $producto->nombre }} ({{ $producto->presentacion }})
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section class="mt-10 rounded-2xl border border-coffee-200 bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-xl font-bold text-coffee-800">Cobranza por forma de pago</h2>
                <p class="mt-1 text-sm text-coffee-700/70">Cuánto entró por cada medio y cuánto sigue por cobrar.</p>
            </div>

            <span @class([
                'rounded-full px-4 py-2 text-sm font-bold',
                'bg-mostaza-400 text-coffee-900' => $porCobrar > 0,
                'bg-coffee-100 text-coffee-700' => $porCobrar === 0.0,
            ])>
                <x-precio :valor="$porCobrar" /> por cobrar
            </span>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            @foreach ($porMetodoDePago as $fila)
                <article class="rounded-2xl border border-coffee-200 p-5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $fila['metodo']->clasesChip() }}">
                            {{ $fila['metodo']->value }}
                        </span>
                        <span class="text-xs text-coffee-700/60">
                            {{ $fila['pedidos'] }} {{ $fila['pedidos'] === 1 ? 'pedido' : 'pedidos' }}
                        </span>
                    </div>

                    <x-precio :valor="$fila['total']" class="mt-3 block font-display text-2xl font-bold text-coffee-800" />

                    <p @class([
                        'mt-1 text-xs font-semibold',
                        'text-mostaza-500' => $fila['porCobrar'] > 0,
                        'text-coffee-700/50' => $fila['porCobrar'] <= 0,
                    ])>
                        @if ($fila['porCobrar'] > 0)
                            <x-precio :valor="$fila['porCobrar']" /> por cobrar
                        @else
                            Todo cobrado
                        @endif
                    </p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mt-10 rounded-2xl border border-coffee-200 bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-xl font-bold text-coffee-800">Promociones vigentes</h2>
                <p class="mt-1 text-sm text-coffee-700/70">Lo que hoy está destacado en el catálogo y cuánto ha movido.</p>
            </div>

            @if ($puedeGestionarPromociones)
                <a href="{{ route('promociones.index') }}" wire:navigate
                    class="rounded-xl border border-coffee-300 bg-white px-5 py-2.5 text-sm font-semibold text-coffee-700 transition hover:border-coffee-500 hover:bg-coffee-50">
                    Gestionar promociones
                </a>
            @endif
        </div>

        @if ($promociones->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-coffee-300 p-8 text-center text-sm text-coffee-700/60">
                No hay promociones vigentes hoy.
            </p>
        @else
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($promociones as $producto)
                    <article class="rounded-2xl border border-mostaza-500 bg-mostaza-400/10 p-5">
                        <p class="font-display text-lg font-bold text-coffee-800">{{ $producto->nombre }}</p>
                        <p class="text-xs text-coffee-700/60">{{ $producto->presentacion }}</p>

                        @if ($producto->promocion_titulo)
                            <p class="mt-2 text-sm font-semibold text-coffee-700">{{ $producto->promocion_titulo }}</p>
                        @endif

                        <p class="mt-3 flex items-baseline gap-2">
                            <x-precio :valor="$producto->precioVigente()" class="font-display text-2xl font-bold text-coffee-800" />
                            @if ($producto->tieneDescuento())
                                <x-precio :valor="$producto->precio" class="text-sm text-coffee-700/50 line-through" />
                                <span class="rounded-full bg-ladrillo-500 px-2 py-0.5 text-[11px] font-bold text-white">
                                    -{{ $producto->descuento }}%
                                </span>
                            @endif
                        </p>

                        <p class="mt-2 text-xs text-coffee-700/60">
                            {{ (int) $producto->unidades_vendidas }} uds vendidas ·
                            {{ $producto->promocion_termina_at
                                ? 'hasta el '.$producto->promocion_termina_at->translatedFormat('d M Y')
                                : 'sin fecha de cierre' }}
                        </p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

</div>
