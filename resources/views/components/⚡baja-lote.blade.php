<?php

use App\Models\Lote;
use Livewire\Component;

/**
 * Baja por merma de un lote: unidades vencidas o dañadas que salen del stock
 * vendible sin pasar por un pedido.
 */
new class extends Component
{
    public Lote $lote;

    public ?int $cantidad = null;

    public string $motivo = '';

    public function mount(): void
    {
        $this->cantidad = $this->lote->cantidad_disponible;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'cantidad' => ['required', 'integer', 'min:1', 'max:'.max($this->lote->cantidad_disponible, 1)],
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'cantidad.max' => 'El lote solo tiene :max unidades disponibles para dar de baja.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'cantidad' => 'cantidad',
            'motivo' => 'motivo de la baja',
        ];
    }

    public function darDeBaja(): void
    {
        $this->authorize('gestionar-inventario');

        $datos = $this->validate();

        $this->lote->darDeBaja($datos['cantidad'], trim($datos['motivo']));

        $this->reset('motivo');
        $this->cantidad = $this->lote->cantidad_disponible;

        $this->dispatch('aviso', mensaje: "Se dieron de baja {$datos['cantidad']} uds del lote {$this->lote->codigo}: {$this->lote->baja_nota}.");
        $this->dispatch('almacen-actualizado');
    }
};
?>

<form wire:submit="darDeBaja" class="mt-3 grid gap-3 border-t border-coffee-200/70 pt-3 sm:grid-cols-[8rem_1fr_auto]">
    <div>
        <label for="cantidad-{{ $lote->id }}" class="block text-xs font-semibold text-coffee-800">
            Unidades
        </label>
        <input type="number" id="cantidad-{{ $lote->id }}" wire:model="cantidad"
            min="1" max="{{ $lote->cantidad_disponible }}"
            class="mt-1 w-full rounded-xl border border-coffee-300 bg-white px-3 py-2 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('cantidad')
            <p class="mt-1 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="motivo-{{ $lote->id }}" class="block text-xs font-semibold text-coffee-800">
            Motivo de la baja
        </label>
        <input type="text" id="motivo-{{ $lote->id }}" wire:model="motivo"
            placeholder="Ej. Lote vencido, retirado del almacén"
            class="mt-1 w-full rounded-xl border border-coffee-300 bg-white px-3 py-2 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('motivo')
            <p class="mt-1 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-end">
        <button type="submit" wire:loading.attr="disabled" wire:target="darDeBaja"
            class="w-full rounded-xl border-2 border-ladrillo-500 px-5 py-2 text-sm font-semibold text-ladrillo-500 transition hover:bg-ladrillo-500 hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-ladrillo-500/25 disabled:opacity-70">
            Dar de baja
        </button>
    </div>
</form>
