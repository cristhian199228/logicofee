<?php

use App\Enums\CategoriaProducto;
use App\Models\Producto;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Edición de un café del catálogo. El slug no cambia: es la dirección con la
 * que el producto ya vive en el catálogo y en los carritos abiertos.
 */
new class extends Component
{
    use WithFileUploads;

    public Producto $producto;

    public string $nombre = '';

    public string $presentacion = '';

    public string $categoria = '';

    public string $descripcion = '';

    public string $precio = '';

    public int $stock_minimo = 0;

    public string $acento = '';

    public $foto = null;

    public function mount(): void
    {
        $this->nombre = $this->producto->nombre;
        $this->presentacion = $this->producto->presentacion;
        $this->categoria = $this->producto->categoria->value;
        $this->descripcion = $this->producto->descripcion;
        $this->precio = (string) $this->producto->precio;
        $this->stock_minimo = $this->producto->stock_minimo;
        $this->acento = $this->producto->acento;
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

    public function guardar(): void
    {
        $this->authorize('editar-catalogo');

        $datos = $this->validate();

        $anterior = $this->producto->imagen;

        $this->producto->update([
            ...collect($datos)->except('foto')->all(),
            'imagen' => $this->foto !== null
                ? $this->foto->store('productos', 'public')
                : $anterior,
        ]);

        if ($anterior !== null && $this->producto->imagen !== $anterior) {
            Storage::disk('public')->delete($anterior);
        }

        $this->reset('foto');

        $this->dispatch('aviso', mensaje: "Se actualizó {$this->producto->nombre}.");
        $this->dispatch('catalogo-actualizado');
    }
};
?>

<form wire:submit="guardar" class="mt-5 grid gap-4 border-t border-coffee-200 pt-5 sm:grid-cols-2 lg:grid-cols-3">
    <div>
        <label for="nombre-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Nombre</label>
        <input type="text" id="nombre-{{ $producto->slug }}" wire:model="nombre"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('nombre')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="presentacion-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Presentación</label>
        <input type="text" id="presentacion-{{ $producto->slug }}" wire:model="presentacion"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('presentacion')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="categoria-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Categoría</label>
        <select id="categoria-{{ $producto->slug }}" wire:model="categoria"
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
        <label for="precio-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Precio</label>
        <input type="number" id="precio-{{ $producto->slug }}" wire:model="precio" step="0.01" min="0.1"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('precio')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="minimo-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Stock mínimo</label>
        <input type="number" id="minimo-{{ $producto->slug }}" wire:model="stock_minimo" min="0"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('stock_minimo')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="acento-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Color del empaque</label>
        <input type="color" id="acento-{{ $producto->slug }}" wire:model="acento"
            class="mt-1.5 h-11 w-full cursor-pointer rounded-xl border border-coffee-300 bg-white px-2 py-1" />
        @error('acento')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="descripcion-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Descripción</label>
        <textarea id="descripcion-{{ $producto->slug }}" wire:model="descripcion" rows="2"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15"></textarea>
        @error('descripcion')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="foto-producto-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">
            Cambiar foto
        </label>
        <input type="file" id="foto-producto-{{ $producto->slug }}" wire:model="foto" accept="image/*"
            class="mt-2.5 w-full text-xs text-coffee-700/60 file:mr-2 file:cursor-pointer file:rounded-full file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-700 hover:file:bg-coffee-200" />
        @error('foto')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-3">
        <button type="submit" wire:loading.attr="disabled" wire:target="guardar"
            class="rounded-xl bg-coffee-500 px-6 py-3 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30 disabled:opacity-70">
            Guardar cambios
        </button>

        @if ($producto->tieneFoto())
            <span class="text-xs text-coffee-700/50">
                La foto actual se reemplaza solo si eliges una nueva.
            </span>
        @endif
    </div>
</form>
