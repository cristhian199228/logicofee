<?php

use App\Support\ResumenAlmacen;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel del área de Logística y Almacén: stock por lote, vencimientos, mermas
 * y la reposición que toca pedir a producción.
 */
new #[Layout('components.layouts.app', ['titulo' => 'Panel de almacén'])] class extends Component
{
    public ?string $aviso = null;

    #[Computed]
    public function almacen(): ResumenAlmacen
    {
        return new ResumenAlmacen;
    }

    #[On('almacen-actualizado')]
    public function refrescar(): void
    {
        unset($this->almacen);
    }

    #[On('aviso')]
    public function mostrarAviso(string $mensaje): void
    {
        $this->aviso = $mensaje;
    }
};
?>

@php
    $almacen = $this->almacen;
    $unidadesDisponibles = $almacen->unidadesDisponibles();
    $valorInventario = $almacen->valorInventario();
    $lotesActivos = $almacen->lotesActivos();
    $unidadesMermadas = $almacen->unidadesMermadas();
    $pedidosPorDespachar = $almacen->pedidosPorDespachar();
    $porVencer = $almacen->lotesPorVencer();
    $vencidos = $almacen->lotesVencidos();
    $reposicion = $almacen->reposicionSugerida();
    $cobertura = $almacen->coberturaPorProducto();
@endphp

<div>
    <x-aviso :mensaje="$aviso" />


    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Panel de almacén</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Stock por lote, vencimientos, mermas y lo que toca reponer para no quedarse sin café.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Logística y Almacén</span>
            <x-reporte-descargas seccion="almacen" />
        </div>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-indicador etiqueta="Unidades vendibles" :valor="$unidadesDisponibles"
            :detalle="$lotesActivos.' lotes con stock'" />
        <x-indicador etiqueta="Valor del inventario" :valor="'$'.number_format($valorInventario, 2)"
            detalle="A precio de lista" />
        <x-indicador etiqueta="Pedidos por despachar" :valor="$pedidosPorDespachar"
            detalle="Pendientes y en preparación"
            :tono="$pedidosPorDespachar > 0 ? 'aviso' : 'neutro'" />
        <x-indicador etiqueta="Unidades dadas de baja" :valor="$unidadesMermadas"
            detalle="Merma acumulada del almacén"
            :tono="$unidadesMermadas > 0 ? 'alerta' : 'neutro'" />
    </dl>

    <section class="mt-8 rounded-2xl border border-coffee-200 bg-white p-6">
        <h2 class="font-display text-xl font-bold text-coffee-800">Reposición sugerida</h2>
        <p class="mt-1 text-sm text-coffee-700/70">
            Productos en su stock mínimo o agotados, con las unidades que hacen falta para dejarlos al doble del mínimo.
        </p>

        @if ($reposicion->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-coffee-300 p-8 text-center text-sm text-coffee-700/60">
                Todo el catálogo está por encima de su stock mínimo.
            </p>
        @else
            <ul class="mt-4 divide-y divide-coffee-200">
                @foreach ($reposicion as $fila)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-coffee-800">{{ $fila['producto']->nombre }}</p>
                            <p class="text-xs text-coffee-700/60">
                                {{ $fila['producto']->presentacion }} · stock {{ $fila['producto']->stock }} ·
                                mínimo {{ $fila['producto']->stock_minimo }} ·
                                {{ $fila['dias'] === null ? 'sin consumo reciente' : 'cobertura '.$fila['dias'].' días' }}
                            </p>
                        </div>

                        <span @class([
                            'shrink-0 rounded-full px-3 py-1 text-xs font-bold',
                            'bg-ladrillo-500/10 text-ladrillo-500' => $fila['producto']->agotado(),
                            'bg-mostaza-400/25 text-coffee-900' => ! $fila['producto']->agotado(),
                        ])>Reponer {{ $fila['sugerido'] }} uds</span>
                    </li>
                @endforeach
            </ul>

            @can('gestionar-inventario')
                <a href="{{ route('lotes.index') }}" wire:navigate
                    class="mt-5 inline-block rounded-xl bg-coffee-500 px-6 py-3 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30">
                    Registrar entrada de lote
                </a>
            @endcan
        @endif
    </section>

    <section class="mt-8 rounded-2xl border border-coffee-200 bg-white p-6">
        <h2 class="font-display text-xl font-bold text-coffee-800">Vencimientos</h2>
        <p class="mt-1 text-sm text-coffee-700/70">
            Los pedidos consumen primero el lote más próximo a vencer. Lo vencido se da de baja para que deje de contar como stock.
        </p>

        @if ($vencidos->isEmpty() && $porVencer->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-coffee-300 p-8 text-center text-sm text-coffee-700/60">
                Ningún lote vence en los próximos {{ \App\Models\Lote::DIAS_AVISO_VENCIMIENTO }} días.
            </p>
        @endif

        @foreach ([['titulo' => 'Vencidos con stock', 'lotes' => $vencidos, 'alerta' => true],
                   ['titulo' => 'Por vencer en 30 días', 'lotes' => $porVencer, 'alerta' => false]] as $grupo)
            @if ($grupo['lotes']->isNotEmpty())
                <h3 class="mt-6 border-b border-coffee-200 pb-1 text-sm font-bold text-coffee-800">
                    {{ $grupo['titulo'] }} ({{ $grupo['lotes']->count() }})
                </h3>

                <div class="mt-3 space-y-3">
                    @foreach ($grupo['lotes'] as $lote)
                        <article @class([
                            'rounded-2xl border p-4',
                            'border-ladrillo-500/40 bg-ladrillo-500/5' => $grupo['alerta'],
                            'border-mostaza-500 bg-mostaza-400/10' => ! $grupo['alerta'],
                        ])>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-coffee-800">
                                        {{ $lote->codigo }} · {{ $lote->producto->nombre }}
                                    </p>
                                    <p class="text-xs text-coffee-700/60">
                                        {{ $lote->cantidad_disponible }} uds ·
                                        {{ $lote->vencido()
                                            ? 'venció el '.$lote->vence_at->translatedFormat('d M Y')
                                            : 'vence en '.$lote->diasParaVencer().' días' }}
                                        @if ($lote->tieneMerma())
                                            · {{ $lote->cantidad_baja }} uds ya dadas de baja
                                        @endif
                                    </p>
                                </div>

                                <x-chip-calidad :calidad="$lote->calidad" />
                            </div>

                            @can('gestionar-inventario')
                                <livewire:baja-lote :$lote :key="'baja-'.$lote->id" />
                            @endcan
                        </article>
                    @endforeach
                </div>
            @endif
        @endforeach
    </section>

    <section class="mt-8 rounded-2xl border border-coffee-200 bg-white p-6">
        <h2 class="font-display text-xl font-bold text-coffee-800">Cobertura de stock</h2>
        <p class="mt-1 text-sm text-coffee-700/70">
            Días que aguanta cada producto al ritmo de venta de los últimos {{ \App\Support\ResumenAlmacen::DIAS_DE_CONSUMO }} días.
        </p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-lg text-left text-sm">
                <thead>
                    <tr class="border-b border-coffee-200 text-xs uppercase tracking-wide text-coffee-700/50">
                        <th scope="col" class="py-2 pr-3 font-semibold">Producto</th>
                        <th scope="col" class="py-2 pr-3 text-right font-semibold">Stock</th>
                        <th scope="col" class="py-2 pr-3 text-right font-semibold">Consumo diario</th>
                        <th scope="col" class="py-2 text-right font-semibold">Cobertura</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-coffee-200">
                    @foreach ($cobertura as $fila)
                        <tr>
                            <td class="py-2 pr-3">
                                <span class="font-semibold text-coffee-800">{{ $fila['producto']->nombre }}</span>
                                <span class="text-coffee-700/60">({{ $fila['producto']->presentacion }})</span>
                            </td>
                            <td class="py-2 pr-3 text-right text-coffee-800">{{ $fila['producto']->stock }}</td>
                            <td class="py-2 pr-3 text-right text-coffee-700/70">{{ number_format($fila['consumo'], 2) }} uds</td>
                            <td class="py-2 text-right">
                                @if ($fila['dias'] === null)
                                    <span class="text-coffee-700/40">sin consumo</span>
                                @else
                                    <span @class([
                                        'rounded-full px-3 py-1 text-xs font-bold',
                                        'bg-ladrillo-500/10 text-ladrillo-500' => $fila['dias'] < 7,
                                        'bg-mostaza-400/25 text-coffee-900' => $fila['dias'] >= 7 && $fila['dias'] < 21,
                                        'bg-coffee-100 text-coffee-700' => $fila['dias'] >= 21,
                                    ])>{{ $fila['dias'] }} días</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

</div>
