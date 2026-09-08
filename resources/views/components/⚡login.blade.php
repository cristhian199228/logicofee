<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.invitado', ['titulo' => 'Iniciar sesión'])] class extends Component
{
    #[Validate('required|string', message: 'Completa el usuario y la contraseña.')]
    public string $usuario = '';

    #[Validate('required|string', message: 'Completa el usuario y la contraseña.')]
    public string $password = '';

    public bool $recordarme = false;

    /**
     * Cuentas de demostración que la pantalla ofrece para probar cada rol.
     *
     * @return list<array{username: string, name: string, rol: string, iniciales: string, descripcion: string}>
     */
    #[Computed]
    public function cuentasDemo(): array
    {
        return config('logicoffee.cuentas_demo');
    }

    /** Completa el formulario con la cuenta de demostración elegida. */
    public function usarCuentaDemo(string $username): void
    {
        $cuenta = collect($this->cuentasDemo())->firstWhere('username', $username);

        if ($cuenta === null) {
            return;
        }

        $this->usuario = $cuenta['username'];
        $this->password = config('logicoffee.password_demo');

        $this->resetValidation();
    }

    /**
     * Autentica las credenciales del formulario y entra por la primera
     * sección del menú del rol.
     *
     * @throws ValidationException
     */
    public function entrar(): void
    {
        $this->validate();

        $this->asegurarQueNoEstaLimitado();

        $credenciales = [
            'username' => Str::lower(trim($this->usuario)),
            'password' => $this->password,
        ];

        if (! Auth::attempt($credenciales, $this->recordarme)) {
            RateLimiter::hit($this->claveLimite());

            throw ValidationException::withMessages([
                'usuario' => 'Usuario o contraseña incorrectos.',
            ]);
        }

        RateLimiter::clear($this->claveLimite());

        $this->asegurarQueLaCuentaEstaActiva();

        session()->regenerate();

        $this->redirectIntended(
            route(Auth::user()->seccionInicial()->ruta()),
            navigate: true,
        );
    }

    /**
     * Una cuenta desactivada por el administrador no entra al sistema (HU09).
     *
     * @throws ValidationException
     */
    private function asegurarQueLaCuentaEstaActiva(): void
    {
        if (Auth::user()->activo) {
            return;
        }

        Auth::guard('web')->logout();

        throw ValidationException::withMessages([
            'usuario' => 'Tu cuenta está desactivada. Comunícate con el administrador.',
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function asegurarQueNoEstaLimitado(): void
    {
        if (! RateLimiter::tooManyAttempts($this->claveLimite(), 5)) {
            return;
        }

        event(new Lockout(request()));

        throw ValidationException::withMessages([
            'usuario' => trans('auth.throttle', [
                'seconds' => $segundos = RateLimiter::availableIn($this->claveLimite()),
                'minutes' => ceil($segundos / 60),
            ]),
        ]);
    }

    private function claveLimite(): string
    {
        return Str::transliterate(Str::lower($this->usuario).'|'.request()->ip());
    }
};
?>

<main class="flex min-h-screen flex-col items-center justify-center px-4 py-12">

    <section class="relative w-full max-w-md rounded-3xl border-2 border-coffee-300 bg-coffee-50 p-8 shadow-xl shadow-coffee-800/10 sm:p-10"
        aria-labelledby="login-titulo">

        <div class="mx-auto grid size-16 place-items-center rounded-full bg-coffee-200">
            <svg class="size-8 text-coffee-700" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 12a5 5 0 100-10 5 5 0 000 10Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z" />
            </svg>
        </div>

        <h1 id="login-titulo" class="mt-5 text-center font-display text-3xl font-bold text-coffee-800">
            Iniciar Sesión
        </h1>
        <p class="mx-auto mt-2 max-w-xs text-center text-sm leading-relaxed text-coffee-700/70">
            Ingresa tus credenciales para acceder a tu rol en LogiCoffee.
        </p>

        <form wire:submit="entrar" class="mt-8 space-y-5">
            <div>
                <label for="usuario" class="block text-sm font-semibold text-coffee-800">
                    Usuario <span class="text-ladrillo-500" aria-hidden="true">*</span>
                </label>
                <input type="text" id="usuario" wire:model="usuario" autofocus
                    autocomplete="username" placeholder="Tu usuario"
                    @class([
                        'mt-2 w-full rounded-xl border bg-white px-4 py-3 text-coffee-900 placeholder:text-coffee-700/40 transition focus:outline-none focus:ring-4',
                        'border-ladrillo-500 focus:border-ladrillo-500 focus:ring-ladrillo-500/15' => $errors->any(),
                        'border-coffee-300 focus:border-coffee-500 focus:ring-coffee-500/15' => ! $errors->any(),
                    ]) />
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-coffee-800">
                    Contraseña <span class="text-ladrillo-500" aria-hidden="true">*</span>
                </label>
                <input type="password" id="password" wire:model="password" autocomplete="current-password" placeholder="••••••••"
                    @class([
                        'mt-2 w-full rounded-xl border bg-white px-4 py-3 text-coffee-900 placeholder:text-coffee-700/40 transition focus:outline-none focus:ring-4',
                        'border-ladrillo-500 focus:border-ladrillo-500 focus:ring-ladrillo-500/15' => $errors->any(),
                        'border-coffee-300 focus:border-coffee-500 focus:ring-coffee-500/15' => ! $errors->any(),
                    ]) />
            </div>

            @if ($errors->any())
                <p class="flex items-center gap-2 rounded-xl border border-ladrillo-500/30 bg-ladrillo-500/10 px-4 py-3 text-sm font-medium text-ladrillo-500" role="alert">
                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16.5v.01" />
                    </svg>
                    <span>{{ $errors->first() }}</span>
                </p>
            @endif

            <button type="submit" wire:loading.attr="disabled"
                class="mt-2 w-full rounded-xl bg-coffee-500 py-3.5 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30 active:scale-[.99] disabled:opacity-70">
                <span wire:loading.remove wire:target="entrar">Entrar</span>
                <span wire:loading wire:target="entrar">Entrando…</span>
            </button>
        </form>
    </section>

    {{-- Cuentas del seeder, para probar cada rol sin crear usuarios a mano. --}}
    <section class="mt-6 w-full max-w-md rounded-3xl border border-coffee-300 bg-white p-6 shadow-sm shadow-coffee-800/5"
        aria-labelledby="cuentas-demo-titulo">

        <h2 id="cuentas-demo-titulo" class="font-display text-lg font-bold text-coffee-800">Cuentas de demostración</h2>
        <p class="mt-1 text-sm leading-relaxed text-coffee-700/70">
            Todas comparten la contraseña
            <span class="font-mono font-bold text-coffee-800">{{ config('logicoffee.password_demo') }}</span>.
            Elige una para completar el formulario.
        </p>

        <ul class="mt-4 space-y-2">
            @foreach ($this->cuentasDemo as $cuenta)
                <li>
                    <button type="button" wire:click="usarCuentaDemo('{{ $cuenta['username'] }}')"
                        title="{{ $cuenta['descripcion'] }}"
                        class="flex w-full items-center gap-3 rounded-2xl border border-coffee-200 bg-coffee-50 px-3 py-2.5 text-left transition hover:border-coffee-400 hover:bg-coffee-100 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/20">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-coffee-700 text-xs font-bold text-coffee-100">
                            {{ $cuenta['iniciales'] }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-coffee-800">{{ $cuenta['name'] }}</span>
                            <span class="block truncate text-xs text-coffee-700/60">
                                <span class="font-mono">{{ $cuenta['username'] }}</span> · {{ $cuenta['rol'] }}
                            </span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>
    </section>
</main>
