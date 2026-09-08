<?php

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * Datos y rol de una cuenta. Nadie cambia su propio rol, para que el sistema
 * no se quede sin administrador (HU09).
 */
new class extends Component
{
    public User $usuario;

    public string $name = '';

    public string $email = '';

    public string $rol = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $descripcion = '';

    public function mount(): void
    {
        $this->name = $this->usuario->name;
        $this->email = $this->usuario->email;
        $this->rol = $this->usuario->rol->value;
        $this->descripcion = $this->usuario->descripcion ?? '';
    }

    public function esPropia(): bool
    {
        return $this->usuario->is(auth()->user());
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignoreModel($this->usuario)],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
            'rol' => ['required', Rule::enum(Rol::class)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'rol' => 'rol',
            'descripcion' => 'descripción',
        ];
    }

    public function guardar(): void
    {
        $this->authorize('gestionar-usuarios');

        $datos = collect($this->validate())->except('password')->all();

        if ($this->esPropia()) {
            unset($datos['rol']);
        }

        if ($this->password !== '') {
            $datos['password'] = $this->password;
        }

        $this->usuario->update([
            ...$datos,
            'iniciales' => $this->iniciales($this->name),
        ]);

        $this->reset('password', 'password_confirmation');
        $this->rol = $this->usuario->rol->value;

        $this->dispatch('aviso', mensaje: "Se actualizó la cuenta {$this->usuario->username}.");
        $this->dispatch('usuarios-actualizados');
    }

    /** Dos letras a partir del nombre, para el avatar de la cuenta. */
    private function iniciales(string $nombre): string
    {
        $palabras = preg_split('/\s+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $iniciales = count($palabras) > 1
            ? Str::substr($palabras[0], 0, 1).Str::substr($palabras[1], 0, 1)
            : Str::substr($nombre, 0, 2);

        return Str::upper($iniciales);
    }
};
?>

@php($esPropia = $this->esPropia())

<form wire:submit="guardar" class="mt-5 grid gap-4 border-t border-coffee-200 pt-5 sm:grid-cols-2 lg:grid-cols-4">
    <div>
        <label for="name-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">Nombre</label>
        <input type="text" id="name-{{ $usuario->id }}" wire:model="name"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('name')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">Correo</label>
        <input type="email" id="email-{{ $usuario->id }}" wire:model="email"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('email')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="rol-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">Rol</label>
        <select id="rol-{{ $usuario->id }}" wire:model="rol" @disabled($esPropia)
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15 disabled:cursor-not-allowed disabled:bg-coffee-100 disabled:text-coffee-700/50">
            @foreach (Rol::cases() as $rol)
                <option value="{{ $rol->value }}">{{ $rol->value }}</option>
            @endforeach
        </select>
        @if ($esPropia)
            <p class="mt-1.5 text-xs text-coffee-700/50">Nadie cambia su propio rol.</p>
        @endif
        @error('rol')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">
            Nueva contraseña
        </label>
        <input type="password" id="password-{{ $usuario->id }}" wire:model="password" autocomplete="new-password"
            placeholder="Dejar vacío para no cambiarla"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('password')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password_confirmation-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">
            Repetir contraseña
        </label>
        <input type="password" id="password_confirmation-{{ $usuario->id }}" wire:model="password_confirmation"
            autocomplete="new-password" placeholder="Solo si la cambias"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
    </div>

    <div class="sm:col-span-2 lg:col-span-2">
        <label for="descripcion-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">Descripción</label>
        <input type="text" id="descripcion-{{ $usuario->id }}" wire:model="descripcion"
            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        @error('descripcion')
            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-end">
        <button type="submit" wire:loading.attr="disabled" wire:target="guardar"
            class="w-full rounded-xl border border-coffee-300 bg-white py-2.5 font-semibold text-coffee-700 transition hover:border-coffee-500 hover:bg-coffee-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/20 disabled:opacity-70">
            Guardar cambios
        </button>
    </div>
</form>
