<x-layouts.invitado titulo="Iniciar sesión">
    <main class="flex min-h-screen flex-col items-center justify-center gap-6 px-4 py-12">

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

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="usuario" class="block text-sm font-semibold text-coffee-800">
                        Usuario <span class="text-ladrillo-500" aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="usuario" name="usuario" value="{{ old('usuario') }}" autofocus
                        autocomplete="username" placeholder="Ej. admin"
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
                    <input type="password" id="password" name="password" autocomplete="current-password" placeholder="••••••••"
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

                <button type="submit"
                    class="mt-2 w-full rounded-xl bg-coffee-500 py-3.5 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30 active:scale-[.99]">
                    Entrar
                </button>
            </form>
        </section>

        <aside class="w-full max-w-md rounded-2xl border border-coffee-300 bg-coffee-50/60 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-coffee-700/70">Credenciales de demostración</p>
            <ul class="mt-2 space-y-1 text-sm text-coffee-800">
                <li class="flex justify-between gap-3"><span><code class="font-semibold">admin</code> · Administrador</span><code class="text-coffee-700/60">demo1234</code></li>
                <li class="flex justify-between gap-3"><span><code class="font-semibold">proveedor</code> · Proveedor</span><code class="text-coffee-700/60">demo1234</code></li>
                <li class="flex justify-between gap-3"><span><code class="font-semibold">cliente</code> · Cliente</span><code class="text-coffee-700/60">demo1234</code></li>
            </ul>
        </aside>
    </main>
</x-layouts.invitado>
