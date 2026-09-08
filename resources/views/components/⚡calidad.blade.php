<?php

use App\Enums\ResultadoCalidad;
use App\Models\Lote;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Control de calidad de los lotes de producción (HU07).
 */
new #[Layout('components.layouts.app', ['titulo' => 'Control de calidad'])] class extends Component
{
    public ?string $aviso = null;

    /**
     * @return Collection<int, Lote>
     */
    #[Computed]
    public function lotes(): Collection
    {
        return Lote::query()
            ->with('producto', 'evaluador')
            ->porVencimiento()
            ->get();
    }

    /**
     * @return Collection<int, Lote>
     */
    #[Computed]
    public function pendientes(): Collection
    {
        return $this->lotes()->where('calidad', ResultadoCalidad::Pendiente);
    }

    /**
     * @return Collection<int, Lote>
     */
    #[Computed]
    public function evaluados(): Collection
    {
        return $this->lotes()->filter->evaluado()->sortByDesc('evaluado_at');
    }

    /**
     * @return Collection<string, int>
     */
    #[Computed]
    public function conteos(): Collection
    {
        return collect(ResultadoCalidad::cases())->mapWithKeys(
            fn (ResultadoCalidad $resultado) => [$resultado->value => $this->lotes()->where('calidad', $resultado)->count()]
        );
    }

    #[Computed]
    public function unidadesBloqueadas(): int
    {
        return (int) $this->lotes()->filter->bloqueado()->sum('cantidad_disponible');
    }

    #[On('calidad-registrada')]
    public function refrescar(): void
    {
        unset($this->lotes, $this->pendientes, $this->evaluados, $this->conteos, $this->unidadesBloqueadas);
    }

    #[On('aviso')]
    public function mostrarAviso(string $mensaje): void
    {
        $this->aviso = $mensaje;
    }
};
?>

<div>
    <x-aviso :mensaje="$aviso" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Control de calidad de lotes</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Registra el resultado del control de cada lote. Un lote rechazado queda bloqueado
                para la venta y deja de sumar al stock del catálogo.
            </p>
        </div>
        <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Producción</span>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-indicador etiqueta="Por controlar" :valor="$this->conteos[ResultadoCalidad::Pendiente->value]"
            detalle="Lotes sin resultado registrado"
            :tono="$this->conteos[ResultadoCalidad::Pendiente->value] > 0 ? 'aviso' : 'neutro'" />
        <x-indicador etiqueta="Aprobados" :valor="$this->conteos[ResultadoCalidad::Aprobado->value]" detalle="Aptos para la venta" />
        <x-indicador etiqueta="Rechazados" :valor="$this->conteos[ResultadoCalidad::Rechazado->value]"
            detalle="Bloqueados para la venta"
            :tono="$this->conteos[ResultadoCalidad::Rechazado->value] > 0 ? 'alerta' : 'neutro'" />
        <x-indicador etiqueta="Unidades bloqueadas" :valor="$this->unidadesBloqueadas" detalle="Fuera del stock del catálogo" />
    </dl>

    <section class="mt-8">
        <h2 class="font-display text-xl font-bold text-coffee-800">Lotes por controlar</h2>

        <div class="mt-4 space-y-4">
            @forelse ($this->pendientes as $lote)
                <livewire:calidad-tarjeta :$lote :key="'pendiente-'.$lote->id" />
            @empty
                <p class="rounded-2xl border border-dashed border-coffee-300 p-10 text-center text-sm text-coffee-700/60">
                    Todos los lotes registrados ya pasaron por control de calidad.
                </p>
            @endforelse
        </div>
    </section>

    <section class="mt-10">
        <h2 class="font-display text-xl font-bold text-coffee-800">Historial de controles</h2>
        <p class="mt-1 text-sm text-coffee-700/70">
            Trazabilidad de cada evaluación: quién la registró y con qué resultado.
        </p>

        <div class="mt-4 space-y-4">
            @forelse ($this->evaluados as $lote)
                <livewire:calidad-tarjeta :$lote :key="'evaluado-'.$lote->id" />
            @empty
                <p class="rounded-2xl border border-dashed border-coffee-300 p-10 text-center text-sm text-coffee-700/60">
                    Todavía no se registró ningún control de calidad.
                </p>
            @endforelse
        </div>
    </section>
</div>
