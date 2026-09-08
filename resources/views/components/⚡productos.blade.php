<?php

use App\Enums\CategoriaProducto;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Gestión del catálogo: alta, edición y baja de los cafés que se venden.
 * El stock no se edita aquí, sale de los lotes del almacén.
 */
new #[Layout('components.layouts.app', ['titulo' => 'Productos'])] class extends Component
{
    use WithFileUploads;

    public ?string $aviso = null;

    public ?string $errorEliminar = null;

    /** Producto sobre el que falló el retiro, para mostrar el aviso en su fila. */
    public ?int $productoConError = null;

    public string $nombre = '';

    public string $presentacion = '';

    public string $categoria = '';

    public string $descripcion = '';

    public string $precio = '';

    public int $stock_minimo = 15;

    public string $acento = '#4a7c3f';

    public $foto = null;

    public function mount(): void
    {
        $this->categoria = CategoriaProducto::cases()[0]->value;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'presentacion' => ['required', 'string', 'max:30'],
            'categoria' => ['required', Rule::enum(CategoriaProducto::class)],
            'descripcion' => ['required', 'string', 'max:500'],
            'precio' => ['required', 'numeric', 'min:0.1', 'max:9999.99'],
            'stock_minimo' => ['required', 'integer', 'min:0', 'max:10000'],
            'acento' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'acento.regex' => 'El color de acento debe ser un hexadecimal como #4a7c3f.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre',
            'presentacion' => 'presentación',
            'categoria' => 'categoría',
            'descripcion' => 'descripción',
            'precio' => 'precio',
            'stock_minimo' => 'stock mínimo',
            'acento' => 'color de acento',
            'foto' => 'foto del producto',
        ];
    }

    /**
     * @return Collection<int, Producto>
     */
    #[Computed]
    public function productos(): Collection
    {
        return Producto::query()
            ->withCount('lotes')
            ->orderBy('nombre')
            ->get();
    }

    #[Computed]
    public function destacados(): int
    {
        return $this->productos()->where('destacado', true)->count();
    }

    #[Computed]
    public function bajoStock(): int
    {
        return $this->productos()
            ->filter(fn (Producto $producto) => $producto->agotado() || $producto->bajoStock())
            ->count();
    }

    public function agregar(): void
    {
        $this->authorize('editar-catalogo');

        $datos = $this->validate();

        $producto = Producto::create([
            ...collect($datos)->except('foto')->all(),
            'slug' => $this->slugUnico($datos['nombre'], $datos['presentacion']),
            'imagen' => $this->foto?->store('productos', 'public'),
            'stock' => 0,
        ]);

        $this->reset('nombre', 'presentacion', 'descripcion', 'precio', 'foto');
        $this->refrescar();

        $this->aviso = "{$producto->nombre} ({$producto->presentacion}) se agregó al catálogo. Registra un lote para darle stock.";
    }

    /**
     * Retira el café del catálogo. El historial de pedidos conserva su copia
     * de los datos, pero el producto no se borra si todavía tiene stock.
     */
    public function retirar(int $productoId): void
    {
        $this->authorize('editar-catalogo');

        $producto = Producto::findOrFail($productoId);

        if (! $producto->agotado()) {
            $this->productoConError = $producto->id;
            $this->errorEliminar = "{$producto->nombre} todavía tiene {$producto->stock} uds en almacén. Da de baja sus lotes antes de retirarlo.";

            return;
        }

        foreach ([$producto->imagen, $producto->promocion_banner] as $archivo) {
            if ($archivo !== null) {
                Storage::disk('public')->delete($archivo);
            }
        }

        $nombre = $producto->nombre;
        $producto->delete();

        $this->reset('errorEliminar', 'productoConError');
        $this->refrescar();

        $this->aviso = "{$nombre} salió del catálogo.";
    }

    #[On('catalogo-actualizado')]
    public function refrescar(): void
    {
        unset($this->productos, $this->destacados, $this->bajoStock);
    }

    #[On('aviso')]
    public function mostrarAviso(string $mensaje): void
    {
        $this->aviso = $mensaje;
    }

    /** Dirección del producto en el catálogo, sin chocar con otra ya usada. */
    private function slugUnico(string $nombre, string $presentacion): string
    {
        $base = Str::slug($nombre.' '.$presentacion);
        $slug = $base;
        $intento = 2;

        while (Producto::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$intento++;
        }

        return $slug;
    }
};
?>

<div>
    <x-aviso :mensaje="$aviso" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Catálogo de cafés</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Da de alta cafés, edita sus datos y su foto. El stock no se toca aquí: sale de los lotes del almacén.
            </p>
        </div>
        <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Gestión de catálogo</span>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-indicador etiqueta="Productos" :valor="$this->productos->count()" detalle="Publicados en el catálogo" />
        <x-indicador etiqueta="En promoción" :valor="$this->destacados" detalle="Marcados como destacados" />
        <x-indicador etiqueta="Agotados o en el mínimo" :valor="$this->bajoStock"
            detalle="Necesitan reposición"
            :tono="$this->bajoStock > 0 ? 'aviso' : 'neutro'" />
    </dl>

    @can('editar-catalogo')
        <section class="mt-8 rounded-2xl border-2 border-coffee-300 bg-coffee-50 p-6">
            <h2 class="font-display text-lg font-bold text-coffee-800">Agregar café al catálogo</h2>

            <form wire:submit="agregar" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-campo-texto campo="nombre" etiqueta="Nombre" marcador="Ej. Bourbon Salvador" requerido />
                <x-campo-texto campo="presentacion" etiqueta="Presentación" marcador="Ej. 500 g" requerido />

                <div>
                    <label for="categoria" class="block text-sm font-semibold text-coffee-800">
                        Categoría <span class="text-ladrillo-500" aria-hidden="true">*</span>
                    </label>
                    <select id="categoria" wire:model="categoria"
                        class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15">
                        @foreach (CategoriaProducto::cases() as $categoria)
                            <option value="{{ $categoria->value }}">{{ $categoria->etiqueta() }}</option>
                        @endforeach
                    </select>
                    @error('categoria')
                        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="precio" class="block text-sm font-semibold text-coffee-800">
                        Precio <span class="text-ladrillo-500" aria-hidden="true">*</span>
                    </label>
                    <input type="number" id="precio" wire:model="precio" step="0.01" min="0.1" placeholder="Ej. 18.50"
                        class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                    @error('precio')
                        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="stock_minimo" class="block text-sm font-semibold text-coffee-800">
                        Stock mínimo <span class="text-ladrillo-500" aria-hidden="true">*</span>
                    </label>
                    <input type="number" id="stock_minimo" wire:model="stock_minimo" min="0"
                        class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                    @error('stock_minimo')
                        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="acento" class="block text-sm font-semibold text-coffee-800">Color del empaque</label>
                    <input type="color" id="acento" wire:model="acento"
                        class="mt-1.5 h-11 w-full cursor-pointer rounded-xl border border-coffee-300 bg-white px-2 py-1" />
                    @error('acento')
                        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="descripcion" class="block text-sm font-semibold text-coffee-800">
                        Descripción <span class="text-ladrillo-500" aria-hidden="true">*</span>
                    </label>
                    <textarea id="descripcion" wire:model="descripcion" rows="2"
                        placeholder="Notas de cata, origen, tueste..."
                        class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15"></textarea>
                    @error('descripcion')
                        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="foto" class="block text-sm font-semibold text-coffee-800">Foto (opcional)</label>
                    <input type="file" id="foto" wire:model="foto" accept="image/*"
                        class="mt-2.5 w-full text-xs text-coffee-700/60 file:mr-2 file:cursor-pointer file:rounded-full file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-700 hover:file:bg-coffee-200" />
                    @error('foto')
                        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <button type="submit" wire:loading.attr="disabled" wire:target="agregar"
                        class="rounded-xl bg-coffee-500 px-6 py-3 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30 disabled:opacity-70">
                        Agregar al catálogo
                    </button>
                </div>
            </form>
        </section>
    @endcan

    <div class="mt-8 space-y-5">
        @foreach ($this->productos as $producto)
            <article wire:key="producto-{{ $producto->id }}" @class([
                'rounded-2xl border bg-white p-6',
                'border-coffee-200' => ! $producto->agotado(),
                'border-ladrillo-500/40' => $producto->agotado(),
            ])>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <x-producto-imagen :$producto marco="size-16 shrink-0 rounded-xl" ilustracion="h-10 w-auto" />

                        <div class="min-w-0">
                            <h2 class="font-display text-lg font-bold text-coffee-800">
                                {{ $producto->nombre }}
                                @if ($producto->promocionVigente())
                                    <span class="ml-1 rounded-full bg-mostaza-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-coffee-900">
                                        En promoción
                                    </span>
                                @endif
                            </h2>
                            <p class="text-xs text-coffee-700/60">
                                {{ $producto->slug }} · {{ $producto->lotes_count }}
                                {{ $producto->lotes_count === 1 ? 'lote' : 'lotes' }} ·
                                <x-precio :valor="$producto->precio" /> de lista
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span @class([
                            'rounded-full px-3 py-1 text-xs font-bold',
                            'bg-ladrillo-500/10 text-ladrillo-500' => $producto->agotado(),
                            'bg-mostaza-400/25 text-coffee-900' => ! $producto->agotado() && $producto->bajoStock(),
                            'bg-coffee-100 text-coffee-700' => ! $producto->agotado() && ! $producto->bajoStock(),
                        ])>
                            {{ $producto->stock }} uds · mínimo {{ $producto->stock_minimo }}
                        </span>

                        @can('editar-catalogo')
                            <button type="button" wire:click="retirar({{ $producto->id }})"
                                class="rounded-full border-2 border-ladrillo-500 px-4 py-1.5 text-sm font-semibold text-ladrillo-500 transition hover:bg-ladrillo-500 hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-ladrillo-500/25">
                                Retirar
                            </button>
                        @endcan
                    </div>
                </div>

                @if ($productoConError === $producto->id)
                    <p class="mt-3 rounded-xl border border-ladrillo-500/40 bg-ladrillo-500/5 px-4 py-2 text-sm font-semibold text-ladrillo-500">
                        {{ $errorEliminar }}
                    </p>
                @endif

                @can('editar-catalogo')
                    <livewire:producto-editor :$producto :key="'editor-'.$producto->id" />
                @endcan
            </article>
        @endforeach
    </div>
</div>
