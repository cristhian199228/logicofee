<?php

use App\Models\Producto;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Promociones del catálogo: qué productos se destacan, con qué descuento y
 * durante qué fechas (HU03).
 */
new #[Layout('components.layouts.app', ['titulo' => 'Promociones'])] class extends Component
{
    public ?string $aviso = null;

    /**
     * @return Collection<int, Producto>
     */
    #[Computed]
    public function productos(): Collection
    {
        return Producto::query()->orderBy('nombre')->get();
    }

    /**
     * @return Collection<int, Producto>
     */
    #[Computed]
    public function vigentes(): Collection
    {
        return $this->productos()->filter->promocionVigente();
    }

    /**
     * @return Collection<int, Producto>
     */
    #[Computed]
    public function programadas(): Collection
    {
        return $this->productos()->filter(
            fn (Producto $producto) => $producto->destacado && ! $producto->promocionVigente()
        );
    }

    #[On('promociones-actualizadas')]
    public function refrescar(): void
    {
        unset($this->productos, $this->vigentes, $this->programadas);
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
            <h1 class="font-display text-3xl font-bold text-coffee-800">Promociones del catálogo</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Destaca productos, define el descuento y la vigencia con la que aparecen en el catálogo.
            </p>
        </div>
        <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Marketing y ventas</span>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-indicador etiqueta="Promociones vigentes" :valor="$this->vigentes->count()"
            detalle="Se muestran ahora en el banner del catálogo"
            :tono="$this->vigentes->isEmpty() ? 'neutro' : 'aviso'" />
        <x-indicador etiqueta="Programadas o vencidas" :valor="$this->programadas->count()"
            detalle="Destacadas fuera de su rango de fechas" />
        <x-indicador etiqueta="Productos en catálogo" :valor="$this->productos->count()" />
    </dl>

    <div class="mt-8 space-y-5">
        @foreach ($this->productos as $producto)
            <article wire:key="promocion-{{ $producto->id }}" @class([
                'rounded-2xl border bg-white p-6',
                'border-mostaza-500' => $producto->promocionVigente(),
                'border-coffee-200' => ! $producto->promocionVigente(),
            ])>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <x-producto-imagen :$producto marco="size-16 shrink-0 rounded-xl" ilustracion="h-10 w-auto" />
                        <div class="min-w-0">
                            <h2 class="font-display text-lg font-bold text-coffee-800">{{ $producto->nombre }}</h2>
                            <p class="text-xs text-coffee-700/60">
                                {{ $producto->presentacion }} · {{ $producto->categoria->etiqueta() }} ·
                                <x-precio :valor="$producto->precio" /> de lista
                            </p>
                        </div>
                    </div>

                    <div class="text-right">
                        @if ($producto->promocionVigente())
                            <span class="rounded-full bg-mostaza-500 px-3 py-1 text-xs font-bold uppercase tracking-wide text-coffee-900">
                                {{ $producto->tieneDescuento() ? 'En promoción · -'.$producto->descuento.'%' : 'Destacado' }}
                            </span>
                            <p class="mt-1 text-sm font-semibold text-coffee-800">
                                Precio promocional <x-precio :valor="$producto->precioVigente()" />
                            </p>
                        @elseif ($producto->destacado)
                            <span class="rounded-full bg-coffee-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-coffee-700/70">
                                Fuera de vigencia
                            </span>
                        @else
                            <span class="rounded-full bg-coffee-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-coffee-700/50">
                                Sin promoción
                            </span>
                        @endif
                    </div>
                </div>

                <livewire:promocion-editor :$producto :key="'promocion-editor-'.$producto->id" />
            </article>
        @endforeach
    </div>
</div>
