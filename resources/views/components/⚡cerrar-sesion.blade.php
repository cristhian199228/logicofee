<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public function cerrar(): void
    {
        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirectRoute('login', navigate: true);
    }
};
?>

<div>
    <button type="button" wire:click="cerrar"
        class="rounded-full border-2 border-ladrillo-500 px-4 py-1.5 text-sm font-semibold text-ladrillo-500 transition hover:bg-ladrillo-500 hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-ladrillo-500/25">
        Cerrar sesión
    </button>
</div>
