<?php

use App\Models\Producto;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Marca o retira el producto de la sección de promociones del catálogo, junto
 * con la imagen del banner con la que se anuncia (HU03).
 */
new class extends Component
{
    use WithFileUploads;

    public Producto $producto;

    public bool $destacado = false;

    public string $promocion_titulo = '';

    public ?int $descuento = 0;

    public string $promocion_inicia_at = '';

    public string $promocion_termina_at = '';

    public $banner = null;

    public bool $quitar_banner = false;

    public function mount(): void
    {
        $this->destacado = $this->producto->destacado;
        $this->promocion_titulo = $this->producto->promocion_titulo ?? '';
        $this->descuento = $this->producto->descuento;
        $this->promocion_inicia_at = $this->producto->promocion_inicia_at?->toDateString() ?? '';
        $this->promocion_termina_at = $this->producto->promocion_termina_at?->toDateString() ?? '';
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'destacado' => ['nullable', 'boolean'],
            'promocion_titulo' => ['nullable', 'string', 'max:60'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'quitar_banner' => ['nullable', 'boolean'],
            'descuento' => ['nullable', 'integer', 'min:0', 'max:70'],
            'promocion_inicia_at' => ['nullable', 'date'],
            'promocion_termina_at' => ['nullable', 'date', 'after_or_equal:promocion_inicia_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'destacado' => 'producto destacado',
            'promocion_titulo' => 'título de la promoción',
            'banner' => 'imagen del banner',
            'descuento' => 'descuento',
            'promocion_inicia_at' => 'inicio de la vigencia',
            'promocion_termina_at' => 'fin de la vigencia',
        ];
    }

    public function guardar(): void
    {
        $this->authorize('gestionar-promociones');

        $this->validate();

        $this->producto->update([
            'destacado' => $this->destacado,
            'promocion_titulo' => $this->destacado ? (trim($this->promocion_titulo) ?: null) : null,
            'promocion_banner' => $this->banner(),
            'descuento' => $this->destacado ? (int) $this->descuento : 0,
            'promocion_inicia_at' => $this->destacado ? ($this->promocion_inicia_at ?: null) : null,
            'promocion_termina_at' => $this->destacado ? ($this->promocion_termina_at ?: null) : null,
        ]);

        $this->reset('banner', 'quitar_banner');

        $this->dispatch('aviso', mensaje: $this->destacado
            ? "{$this->producto->nombre} se destaca en el catálogo con {$this->producto->descuento}% de descuento."
            : "{$this->producto->nombre} salió de las promociones.");

        $this->dispatch('promociones-actualizadas');
    }

    /**
     * Guarda la imagen nueva del banner, la retira si se pidió quitarla y
     * borra del disco la que quede sin uso.
     */
    private function banner(): ?string
    {
        $anterior = $this->producto->promocion_banner;

        $banner = match (true) {
            $this->banner !== null => $this->banner->store('promociones', 'public'),
            $this->quitar_banner => null,
            default => $anterior,
        };

        if ($anterior !== null && $banner !== $anterior) {
            Storage::disk('public')->delete($anterior);
        }

        return $banner;
    }
};
?>

<form wire:submit="guardar" class="mt-5 grid gap-4 border-t border-coffee-200 pt-5 sm:grid-cols-2 lg:grid-cols-5">
    <label class="flex items-center gap-2 rounded-xl border border-coffee-300 bg-coffee-50 px-4 py-2.5 lg:col-span-1">
        <input type="checkbox" wire:model.live="destacado"
            class="size-4 rounded border-coffee-300 text-coffee-600 focus:ring-coffee-500/30" />
        <span class="text-sm font-semibold text-coffee-800">Destacado</span>
    </label>

    <div class="lg:col-span-2">
        <label for="titulo-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">
            Título de la promoción
        </label>
        <input type="text" id="titulo-{{ $producto->slug }}" wire:model="promocion_titulo"
            placeholder="Ej. Semana del café de origen"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('promocion_titulo')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="descuento-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">
            Descuento (%)
        </label>
        <input type="number" id="descuento-{{ $producto->slug }}" wire:model="descuento" min="0" max="70"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('descuento')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-2 gap-2">
        <div>
            <label for="inicia-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Desde</label>
            <input type="date" id="inicia-{{ $producto->slug }}" wire:model="promocion_inicia_at"
                class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-3 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        </div>
        <div>
            <label for="termina-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Hasta</label>
            <input type="date" id="termina-{{ $producto->slug }}" wire:model="promocion_termina_at"
                class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-3 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        </div>
        @error('promocion_termina_at')
            <p class="col-span-2 mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2 lg:col-span-5">
        <p class="text-sm font-semibold text-coffee-800">Imagen del banner</p>
        <p class="mt-0.5 text-xs text-coffee-700/60">
            Encabeza la sección de promociones del catálogo. Se ve mejor apaisada (16:9), hasta 4 MB.
        </p>

        <div class="mt-2 flex flex-wrap items-center gap-4">
            @if ($producto->tieneBanner())
                <img src="{{ $producto->urlBanner() }}" alt="Banner de {{ $producto->nombre }}"
                    loading="lazy" class="h-20 w-36 shrink-0 rounded-xl object-cover object-center" />
            @else
                <span class="grid h-20 w-36 shrink-0 place-items-center rounded-xl border border-dashed border-coffee-300 text-xs text-coffee-700/40">
                    Sin banner
                </span>
            @endif

            <div class="min-w-0 flex-1">
                <label for="banner-{{ $producto->slug }}" class="sr-only">Banner de {{ $producto->nombre }}</label>
                <input type="file" id="banner-{{ $producto->slug }}" wire:model="banner" accept="image/*"
                    class="w-full text-xs text-coffee-700/60 file:mr-2 file:cursor-pointer file:rounded-full file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-700 hover:file:bg-coffee-200" />

                @if ($producto->tieneBanner())
                    <label class="mt-2 flex items-center gap-2 text-xs font-semibold text-ladrillo-500">
                        <input type="checkbox" wire:model="quitar_banner"
                            class="size-4 rounded border-coffee-300 text-ladrillo-500 focus:ring-ladrillo-500/30" />
                        Quitar el banner actual
                    </label>
                @endif

                @error('banner')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <div class="sm:col-span-2 lg:col-span-5">
        <button type="submit" wire:loading.attr="disabled" wire:target="guardar"
            class="rounded-xl bg-coffee-500 px-6 py-3 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30 disabled:opacity-70">
            Guardar promoción
        </button>
    </div>
</form>
