<?php

use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Almacén por lotes: qué queda de cada tueste, qué está por vencer y el
 * registro de las entradas de tostado.
 */
new #[Layout('components.layouts.app', ['titulo' => 'Lotes'])] class extends Component
{
    public ?string $aviso = null;

    public string $producto = '';

    public string $codigo = '';

    public ?int $cantidad = null;

    public string $tostado_at = '';

    public string $vence_at = '';

    public function mount(): void
    {
        $this->producto = $this->productos()->first()?->slug ?? '';
        $this->tostado_at = now()->toDateString();
        $this->vence_at = now()->addYear()->toDateString();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'producto' => ['required', 'string', Rule::exists('productos', 'slug')],
            'codigo' => ['required', 'string', 'max:20', Rule::unique('lotes', 'codigo')],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'tostado_at' => ['required', 'date', 'before_or_equal:today'],
            'vence_at' => ['required', 'date', 'after:tostado_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'producto' => 'producto',
            'codigo' => 'código del lote',
            'cantidad' => 'cantidad',
            'tostado_at' => 'fecha de tostado',
            'vence_at' => 'fecha de vencimiento',
        ];
    }

    /**
     * @return Collection<int, Producto>
     */
    #[Computed]
    public function productos(): Collection
    {
        return Producto::query()
            ->with(['lotes' => fn ($query) => $query->porVencimiento()])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @return Collection<int, Lote>
     */
    #[Computed]
    public function lotes(): Collection
    {
        return $this->productos()->flatMap->lotes;
    }

    #[Computed]
    public function unidadesDisponibles(): int
    {
        return (int) $this->lotes()->sum('cantidad_disponible');
    }

    #[Computed]
    public function lotesActivos(): int
    {
        return $this->lotes()->where('cantidad_disponible', '>', 0)->count();
    }

    #[Computed]
    public function lotesPorVencer(): int
    {
        return $this->lotes()->filter(fn (Lote $lote) => ! $lote->agotado() && $lote->porVencer())->count();
    }

    /**
     * Registra la entrada de un lote nuevo y actualiza el stock del producto.
     */
    public function registrar(): void
    {
        $this->authorize('gestionar-inventario');

        $datos = $this->validate();

        $producto = Producto::where('slug', $datos['producto'])->firstOrFail();

        $lote = $producto->lotes()->create([
            'codigo' => mb_strtoupper($datos['codigo']),
            'cantidad_inicial' => $datos['cantidad'],
            'cantidad_disponible' => $datos['cantidad'],
            'tostado_at' => $datos['tostado_at'],
            'vence_at' => $datos['vence_at'],
        ]);

        $producto->sincronizarStock();

        $this->reset('codigo', 'cantidad');
        unset($this->productos, $this->lotes, $this->unidadesDisponibles, $this->lotesActivos, $this->lotesPorVencer);

        $this->aviso = "Lote {$lote->codigo} registrado con {$lote->cantidad_inicial} uds de {$producto->nombre}.";
    }
};
?>

<div>
    <x-aviso :mensaje="$aviso" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Lotes en almacén</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Cada pedido descuenta del lote más próximo a vencer. Registra aquí las entradas de tueste.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Inventario</span>
            <x-reporte-descargas seccion="lotes" />
        </div>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-coffee-200 bg-white p-5">
            <dt class="text-xs font-semibold uppercase tracking-wide text-coffee-700/50">Unidades disponibles</dt>
            <dd class="mt-1 font-display text-3xl font-bold text-coffee-800">{{ $this->unidadesDisponibles }}</dd>
        </div>
        <div class="rounded-2xl border border-coffee-200 bg-white p-5">
            <dt class="text-xs font-semibold uppercase tracking-wide text-coffee-700/50">Lotes activos</dt>
            <dd class="mt-1 font-display text-3xl font-bold text-coffee-800">{{ $this->lotesActivos }}</dd>
        </div>
        <div @class([
            'rounded-2xl border p-5',
            'border-mostaza-500 bg-mostaza-400/15' => $this->lotesPorVencer > 0,
            'border-coffee-200 bg-white' => $this->lotesPorVencer === 0,
        ])>
            <dt class="text-xs font-semibold uppercase tracking-wide text-coffee-700/50">Por vencer (30 días)</dt>
            <dd class="mt-1 font-display text-3xl font-bold text-coffee-800">{{ $this->lotesPorVencer }}</dd>
        </div>
    </dl>

    @can('gestionar-inventario')
        <section class="mt-8 rounded-2xl border-2 border-coffee-300 bg-coffee-50 p-6">
            <h2 class="font-display text-lg font-bold text-coffee-800">Registrar entrada de lote</h2>

            <form wire:submit="registrar" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label for="producto" class="block text-sm font-semibold text-coffee-800">Producto</label>
                    <select id="producto" wire:model="producto"
                        class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15">
                        @foreach ($this->productos as $producto)
                            <option value="{{ $producto->slug }}">
                                {{ $producto->nombre }} ({{ $producto->presentacion }})
                            </option>
                        @endforeach
                    </select>
                    @error('producto')
                        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                    @enderror
                </div>

                <x-campo-texto campo="codigo" etiqueta="Código" marcador="Ej. L-2610" requerido />

                <div>
                    <label for="cantidad" class="block text-sm font-semibold text-coffee-800">
                        Cantidad <span class="text-ladrillo-500" aria-hidden="true">*</span>
                    </label>
                    <input type="number" id="cantidad" wire:model="cantidad" min="1" placeholder="Ej. 80"
                        @class([
                            'mt-1.5 w-full rounded-xl border bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:outline-none focus:ring-4',
                            'border-ladrillo-500 focus:border-ladrillo-500 focus:ring-ladrillo-500/15' => $errors->has('cantidad'),
                            'border-coffee-300 focus:border-coffee-500 focus:ring-coffee-500/15' => ! $errors->has('cantidad'),
                        ]) />
                    @error('cantidad')
                        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                    @enderror
                </div>

                <x-campo-texto campo="tostado_at" etiqueta="Tostado" tipo="date" requerido />

                <x-campo-texto campo="vence_at" etiqueta="Vence" tipo="date" requerido />

                <div class="sm:col-span-2 lg:col-span-5">
                    <button type="submit" wire:loading.attr="disabled" wire:target="registrar"
                        class="rounded-xl bg-coffee-500 px-6 py-3 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30 disabled:opacity-70">
                        Registrar lote
                    </button>
                </div>
            </form>
        </section>
    @endcan

    <div class="mt-8 space-y-5">
        @foreach ($this->productos as $producto)
            <article class="rounded-2xl border border-coffee-200 bg-white p-6" wire:key="producto-{{ $producto->id }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <x-producto-imagen :$producto marco="size-16 shrink-0 rounded-xl" ilustracion="h-10 w-auto" />
                        <div class="min-w-0">
                            <h2 class="font-display text-lg font-bold text-coffee-800">{{ $producto->nombre }}</h2>
                            <p class="text-xs text-coffee-700/60">
                                {{ $producto->presentacion }} · {{ $producto->categoria->etiqueta() }}
                            </p>
                        </div>
                    </div>

                    <p @class([
                        'rounded-full px-4 py-1.5 text-sm font-bold',
                        'bg-ladrillo-500/10 text-ladrillo-500' => $producto->agotado(),
                        'bg-mostaza-400/25 text-coffee-900' => ! $producto->agotado() && $producto->bajoStock(),
                        'bg-coffee-100 text-coffee-800' => ! $producto->agotado() && ! $producto->bajoStock(),
                    ])>
                        {{ $producto->stock }} uds · mínimo {{ $producto->stock_minimo }}
                    </p>
                </div>

                @if ($producto->lotes->isEmpty())
                    <p class="mt-4 rounded-xl border border-dashed border-coffee-300 p-6 text-center text-sm text-coffee-700/60">
                        Este producto todavía no tiene lotes registrados.
                    </p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($producto->lotes as $lote)
                            <li wire:key="lote-{{ $lote->id }}" @class([
                                'rounded-xl border p-4',
                                'border-coffee-200 bg-coffee-50/60' => ! $lote->agotado() && ! $lote->porVencer() && ! $lote->vencido(),
                                'border-mostaza-500 bg-mostaza-400/10' => ! $lote->agotado() && $lote->porVencer(),
                                'border-ladrillo-500/50 bg-ladrillo-500/5' => ! $lote->agotado() && $lote->vencido(),
                                'border-coffee-200 bg-white opacity-60' => $lote->agotado(),
                            ])>
                                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <p class="font-mono text-sm font-bold text-coffee-800">{{ $lote->codigo }}</p>

                                    <p class="text-xs text-coffee-700/70">
                                        Tostado {{ $lote->tostado_at->format('d/m/Y') }} ·
                                        Vence {{ $lote->vence_at->format('d/m/Y') }}
                                    </p>

                                    <p class="text-sm font-semibold text-coffee-800">
                                        {{ $lote->cantidad_disponible }} / {{ $lote->cantidad_inicial }} uds
                                    </p>
                                </div>

                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-coffee-200">
                                    <div class="h-full rounded-full bg-coffee-500"
                                        style="width: {{ 100 - $lote->porcentajeConsumido() }}%"></div>
                                </div>

                                <p class="mt-2 text-xs font-semibold">
                                    @if ($lote->agotado())
                                        <span class="text-coffee-700/50">Lote agotado</span>
                                    @elseif ($lote->vencido())
                                        <span class="text-ladrillo-500">Vencido — retirar del almacén</span>
                                    @elseif ($lote->porVencer())
                                        <span class="text-mostaza-500">Vence en {{ $lote->diasParaVencer() }} días</span>
                                    @else
                                        <span class="text-coffee-600">Disponible · {{ $lote->porcentajeConsumido() }}% despachado</span>
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @endforeach
    </div>
</div>
