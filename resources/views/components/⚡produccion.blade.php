<?php

use App\Support\PlanProduccion;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel del área de Producción y Operaciones: qué toca tostar, qué lotes
 * esperan control de calidad y cómo viene el rendimiento.
 */
new #[Layout('components.layouts.app', ['titulo' => 'Plan de producción'])] class extends Component
{
    public ?string $aviso = null;

    #[Computed]
    public function plan(): PlanProduccion
    {
        return new PlanProduccion;
    }

    #[On('almacen-actualizado')]
    #[On('calidad-registrada')]
    public function refrescar(): void
    {
        unset($this->plan);
    }

    #[On('aviso')]
    public function mostrarAviso(string $mensaje): void
    {
        $this->aviso = $mensaje;
    }
};
?>

@php
    $plan = $this->plan;
    $ordenes = $plan->ordenesSugeridas();
    $unidadesSugeridas = $plan->unidadesSugeridas();
    $pendientes = $plan->lotesPendientes();
    $ultimosLotes = $plan->ultimosLotes();
    $unidadesProducidas = $plan->unidadesProducidas();
    $rendimiento = $plan->rendimientoCalidad();
    $unidadesBloqueadas = $plan->unidadesBloqueadas();
    $porcentajeDeMerma = $plan->porcentajeDeMerma();
    $puedeControlarCalidad = auth()->user()->rol->puedeControlarCalidad();
    $puedeMoverAlmacen = auth()->user()->rol->puedeMoverAlmacen();
@endphp

<div>
    <x-aviso :mensaje="$aviso" />


    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Plan de producción</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Qué toca tostar, qué lotes esperan control de calidad y cómo viene el rendimiento del área.
            </p>
        </div>
        <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Producción y Operaciones</span>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-indicador etiqueta="Unidades por tostar" :valor="$unidadesSugeridas"
            :detalle="$ordenes->count().' productos en el mínimo'"
            :tono="$unidadesSugeridas > 0 ? 'aviso' : 'neutro'" />
        <x-indicador etiqueta="Producción reciente" :valor="$unidadesProducidas"
            :detalle="'Unidades tostadas en '.\App\Support\PlanProduccion::DIAS_DE_PRODUCCION.' días'" />
        <x-indicador etiqueta="Lotes aprobados" :valor="$rendimiento['porcentaje'].'%'"
            :detalle="$rendimiento['aprobados'].' aprobados · '.$rendimiento['rechazados'].' rechazados'" />
        <x-indicador etiqueta="Unidades bloqueadas" :valor="$unidadesBloqueadas"
            :detalle="'Merma del '.$porcentajeDeMerma.'% sobre lo producido'"
            :tono="$unidadesBloqueadas > 0 ? 'alerta' : 'neutro'" />
    </dl>

    <section class="mt-8 rounded-2xl border border-coffee-200 bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-xl font-bold text-coffee-800">Órdenes de tueste sugeridas</h2>
                <p class="mt-1 text-sm text-coffee-700/70">
                    Productos que llegaron a su stock mínimo, con las unidades que hacen falta para dejarlos al doble.
                </p>
            </div>

            @if ($puedeMoverAlmacen)
                <a href="{{ route('lotes.index') }}"
                    class="rounded-xl bg-coffee-500 px-6 py-3 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30">
                    Registrar lote tostado
                </a>
            @endif
        </div>

        @if ($ordenes->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-coffee-300 p-8 text-center text-sm text-coffee-700/60">
                No hay nada urgente por tostar: todo el catálogo está sobre su stock mínimo.
            </p>
        @else
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($ordenes as $orden)
                    <article @class([
                        'rounded-2xl border p-5',
                        'border-ladrillo-500/50 bg-ladrillo-500/5' => $orden['urgente'],
                        'border-mostaza-500 bg-mostaza-400/10' => ! $orden['urgente'],
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-display text-lg font-bold text-coffee-800">{{ $orden['producto']->nombre }}</p>
                                <p class="text-xs text-coffee-700/60">{{ $orden['producto']->presentacion }}</p>
                            </div>

                            @if ($orden['urgente'])
                                <span class="shrink-0 rounded-full bg-ladrillo-500 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white">
                                    Agotado
                                </span>
                            @endif
                        </div>

                        <p class="mt-4 font-display text-3xl font-bold text-coffee-800">{{ $orden['sugerido'] }} uds</p>
                        <p class="text-xs text-coffee-700/60">
                            Stock {{ $orden['producto']->stock }} · mínimo {{ $orden['producto']->stock_minimo }}
                        </p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-coffee-200 bg-white p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-xl font-bold text-coffee-800">Lotes por controlar</h2>

                @if ($puedeControlarCalidad && $pendientes->isNotEmpty())
                    <a href="{{ route('calidad.index') }}"
                        class="rounded-xl border border-coffee-300 bg-white px-5 py-2.5 text-sm font-semibold text-coffee-700 transition hover:border-coffee-500 hover:bg-coffee-50">
                        Ir al control de calidad
                    </a>
                @endif
            </div>

            @if ($pendientes->isEmpty())
                <p class="mt-4 rounded-xl border border-dashed border-coffee-300 p-8 text-center text-sm text-coffee-700/60">
                    Todos los lotes con stock ya pasaron por control de calidad.
                </p>
            @else
                <ul class="mt-4 divide-y divide-coffee-200">
                    @foreach ($pendientes as $lote)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-coffee-800">
                                    {{ $lote->codigo }} · {{ $lote->producto->nombre }}
                                </p>
                                <p class="text-xs text-coffee-700/60">
                                    {{ $lote->cantidad_disponible }} uds ·
                                    tostado {{ $lote->tostado_at->translatedFormat('d M Y') }} ·
                                    vence en {{ $lote->diasParaVencer() }} días
                                </p>
                            </div>

                            <x-chip-calidad :calidad="$lote->calidad" class="shrink-0" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-2xl border border-coffee-200 bg-white p-6">
            <h2 class="font-display text-xl font-bold text-coffee-800">Rendimiento del control de calidad</h2>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <x-indicador etiqueta="Aprobados" :valor="$rendimiento['aprobados']" detalle="Lotes aptos" />
                <x-indicador etiqueta="Rechazados" :valor="$rendimiento['rechazados']" detalle="Fuera de venta"
                    :tono="$rendimiento['rechazados'] > 0 ? 'alerta' : 'neutro'" />
                <x-indicador etiqueta="Pendientes" :valor="$rendimiento['pendientes']" detalle="Sin evaluar"
                    :tono="$rendimiento['pendientes'] > 0 ? 'aviso' : 'neutro'" />
            </div>

            <h3 class="mt-6 border-b border-coffee-200 pb-1 text-sm font-bold text-coffee-800">Últimos lotes tostados</h3>

            @if ($ultimosLotes->isEmpty())
                <p class="mt-3 text-sm text-coffee-700/60">Todavía no se registró ningún lote.</p>
            @else
                <ul class="mt-3 divide-y divide-coffee-200">
                    @foreach ($ultimosLotes as $lote)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-2 text-sm">
                            <div class="min-w-0">
                                <p class="truncate text-coffee-800">
                                    <span class="font-semibold">{{ $lote->codigo }}</span> · {{ $lote->producto->nombre }}
                                </p>
                                <p class="text-xs text-coffee-700/60">
                                    {{ $lote->cantidad_inicial }} uds tostadas el {{ $lote->tostado_at->translatedFormat('d M Y') }}
                                    @if ($lote->evaluador)
                                        · evaluó {{ $lote->evaluador->name }}
                                    @endif
                                </p>
                            </div>

                            <x-chip-calidad :calidad="$lote->calidad" class="shrink-0" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

</div>
