<?php

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Cuentas de usuario y sus roles (HU09).
 */
new #[Layout('components.layouts.app', ['titulo' => 'Usuarios y roles'])] class extends Component
{
    public ?string $aviso = null;

    public string $username = '';

    public string $name = '';

    public string $email = '';

    public string $rol = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $descripcion = '';

    public function mount(): void
    {
        $this->rol = Rol::cases()[0]->value;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'username' => ['required', 'string', 'alpha_dash', 'max:30', Rule::unique('users', 'username')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
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
            'username' => 'usuario',
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'rol' => 'rol',
            'descripcion' => 'descripción',
        ];
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function usuarios(): Collection
    {
        return User::query()
            ->withCount('pedidos')
            ->orderBy('rol')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function activos(): int
    {
        return $this->usuarios()->where('activo', true)->count();
    }

    /**
     * @return Collection<string, int>
     */
    #[Computed]
    public function porRol(): Collection
    {
        return collect(Rol::cases())->mapWithKeys(
            fn (Rol $rol) => [$rol->value => $this->usuarios()->where('rol', $rol)->count()]
        );
    }

    public function crear(): void
    {
        $this->authorize('gestionar-usuarios');

        $datos = $this->validate();

        $usuario = User::create([
            ...collect($datos)->except('password_confirmation')->all(),
            'iniciales' => $this->iniciales($datos['name']),
            'activo' => true,
        ]);

        $this->reset('username', 'name', 'email', 'password', 'password_confirmation', 'descripcion');
        $this->refrescar();

        $this->aviso = "Cuenta {$usuario->username} creada con el rol {$usuario->rol->value}.";
    }

    /**
     * Activa o desactiva una cuenta. Nadie desactiva la suya, para no dejar el
     * sistema sin quien lo administre.
     */
    public function alternarEstado(int $usuarioId): void
    {
        $this->authorize('gestionar-usuarios');

        $usuario = User::findOrFail($usuarioId);

        if ($usuario->is(auth()->user())) {
            $this->aviso = 'No puedes desactivar tu propia cuenta.';

            return;
        }

        $usuario->update(['activo' => ! $usuario->activo]);

        $this->refrescar();

        $this->aviso = $usuario->activo
            ? "La cuenta {$usuario->username} quedó activa."
            : "La cuenta {$usuario->username} quedó desactivada y ya no puede iniciar sesión.";
    }

    #[On('usuarios-actualizados')]
    public function refrescar(): void
    {
        unset($this->usuarios, $this->activos, $this->porRol);
    }

    #[On('aviso')]
    public function mostrarAviso(string $mensaje): void
    {
        $this->aviso = $mensaje;
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

<div>
    <x-aviso :mensaje="$aviso" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Usuarios y roles</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Crea cuentas, asigna el rol con el que entran al sistema y activa o desactiva accesos.
            </p>
        </div>
        <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Administración</span>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-indicador etiqueta="Cuentas" :valor="$this->usuarios->count()" :detalle="$this->activos.' activas'" />
        <x-indicador etiqueta="Roles" :valor="count(Rol::cases())"
            detalle="Un rol por área de la empresa" />
        <x-indicador etiqueta="Cuentas desactivadas" :valor="$this->usuarios->count() - $this->activos"
            detalle="Sin acceso al sistema"
            :tono="$this->usuarios->count() - $this->activos > 0 ? 'alerta' : 'neutro'" />
    </dl>

    <section class="mt-8 rounded-2xl border border-coffee-200 bg-white p-6">
        <h2 class="font-display text-xl font-bold text-coffee-800">Áreas y accesos</h2>
        <p class="mt-1 text-sm text-coffee-700/70">
            Cada rol entra por la primera sección de su menú y solo abre las que le corresponden.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (Rol::cases() as $rol)
                <article class="rounded-2xl border border-coffee-200 bg-coffee-50 p-5">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="font-display text-lg font-bold text-coffee-800">{{ $rol->value }}</h3>
                        <span class="shrink-0 rounded-full bg-coffee-700 px-3 py-1 text-xs font-bold text-white">
                            {{ $this->porRol[$rol->value] }}
                        </span>
                    </div>

                    <p class="mt-2 text-sm text-coffee-700/70">{{ $rol->proposito() }}</p>

                    <ul class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($rol->secciones() as $seccion)
                            <li class="rounded-full border border-coffee-300 bg-white px-2.5 py-0.5 text-[11px] font-semibold text-coffee-700">
                                {{ $seccion->titulo() }}
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mt-8 rounded-2xl border-2 border-coffee-300 bg-coffee-50 p-6">
        <h2 class="font-display text-lg font-bold text-coffee-800">Crear cuenta</h2>

        <form wire:submit="crear" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-campo-texto campo="username" etiqueta="Usuario" marcador="Ej. despacho" requerido />
            <x-campo-texto campo="name" etiqueta="Nombre" marcador="Ej. C. Vargas" requerido />
            <x-campo-texto campo="email" etiqueta="Correo electrónico" tipo="email"
                marcador="Ej. despacho@logicoffee.test" requerido />

            <div>
                <label for="rol" class="block text-sm font-semibold text-coffee-800">
                    Rol <span class="text-ladrillo-500" aria-hidden="true">*</span>
                </label>
                <select id="rol" wire:model="rol"
                    class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15">
                    @foreach (Rol::cases() as $rol)
                        <option value="{{ $rol->value }}">{{ $rol->value }}</option>
                    @endforeach
                </select>
                @error('rol')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </div>

            <x-campo-texto campo="password" etiqueta="Contraseña" tipo="password" marcador="Mínimo 8 caracteres" requerido />
            <x-campo-texto campo="password_confirmation" etiqueta="Repetir contraseña" tipo="password" requerido />

            <div class="sm:col-span-2 lg:col-span-3">
                <x-campo-texto campo="descripcion" etiqueta="Descripción (opcional)"
                    marcador="Ej. Coordina la preparación y entrega de pedidos" />
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="crear"
                    class="rounded-xl bg-coffee-500 px-6 py-3 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30 disabled:opacity-70">
                    Crear cuenta
                </button>
            </div>
        </form>
    </section>

    <div class="mt-8 space-y-5">
        @foreach ($this->usuarios as $usuario)
            @php($esPropia = $usuario->is(auth()->user()))

            <article wire:key="usuario-{{ $usuario->id }}" @class([
                'rounded-2xl border bg-white p-6',
                'border-coffee-200' => $usuario->activo,
                'border-ladrillo-500/40 bg-ladrillo-500/5' => ! $usuario->activo,
            ])>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-coffee-700 font-display text-lg font-bold text-white">
                            {{ $usuario->inicialesVisibles() }}
                        </span>

                        <div class="min-w-0">
                            <h2 class="font-display text-lg font-bold text-coffee-800">
                                {{ $usuario->name }}
                                @if ($esPropia)
                                    <span class="ml-1 rounded-full bg-coffee-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-coffee-700">Tu cuenta</span>
                                @endif
                            </h2>
                            <p class="text-xs text-coffee-700/60">
                                {{ '@'.$usuario->username }} · {{ $usuario->email }} ·
                                {{ $usuario->pedidos_count }} {{ $usuario->pedidos_count === 1 ? 'pedido' : 'pedidos' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span @class([
                            'rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide',
                            'bg-coffee-700 text-white' => $usuario->activo,
                            'bg-ladrillo-500 text-white' => ! $usuario->activo,
                        ])>{{ $usuario->activo ? 'Activa' : 'Desactivada' }}</span>

                        <span class="rounded-full border border-coffee-300 px-3 py-1 text-xs font-bold text-coffee-700">
                            {{ $usuario->rol->value }}
                        </span>

                        @unless ($esPropia)
                            <button type="button" wire:click="alternarEstado({{ $usuario->id }})" @class([
                                'rounded-full border-2 px-4 py-1.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-4',
                                'border-ladrillo-500 text-ladrillo-500 hover:bg-ladrillo-500 hover:text-white focus-visible:ring-ladrillo-500/25' => $usuario->activo,
                                'border-coffee-500 text-coffee-600 hover:bg-coffee-500 hover:text-white focus-visible:ring-coffee-500/25' => ! $usuario->activo,
                            ])>
                                {{ $usuario->activo ? 'Desactivar' : 'Activar' }}
                            </button>
                        @endunless
                    </div>
                </div>

                <livewire:usuario-editor :$usuario :key="'usuario-editor-'.$usuario->id" />
            </article>
        @endforeach
    </div>
</div>
