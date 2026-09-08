<x-layouts.app titulo="Usuarios y roles">

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
        <x-indicador etiqueta="Cuentas" :valor="$usuarios->count()" :detalle="$activos.' activas'" />
        <x-indicador etiqueta="Roles" :valor="count(\App\Enums\Rol::cases())"
            detalle="Un rol por área de la empresa" />
        <x-indicador etiqueta="Cuentas desactivadas" :valor="$usuarios->count() - $activos"
            detalle="Sin acceso al sistema"
            :tono="$usuarios->count() - $activos > 0 ? 'alerta' : 'neutro'" />
    </dl>

    <section class="mt-8 rounded-2xl border border-coffee-200 bg-white p-6">
        <h2 class="font-display text-xl font-bold text-coffee-800">Áreas y accesos</h2>
        <p class="mt-1 text-sm text-coffee-700/70">
            Cada rol entra por la primera sección de su menú y solo abre las que le corresponden.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (\App\Enums\Rol::cases() as $rol)
                <article class="rounded-2xl border border-coffee-200 bg-coffee-50 p-5">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="font-display text-lg font-bold text-coffee-800">{{ $rol->value }}</h3>
                        <span class="shrink-0 rounded-full bg-coffee-700 px-3 py-1 text-xs font-bold text-white">
                            {{ $porRol[$rol->value] }}
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

        <form method="POST" action="{{ route('usuarios.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @csrf

            <x-campo-texto nombre="username" etiqueta="Usuario" marcador="Ej. despacho" requerido />
            <x-campo-texto nombre="name" etiqueta="Nombre" marcador="Ej. C. Vargas" requerido />
            <x-campo-texto nombre="email" etiqueta="Correo electrónico" tipo="email"
                marcador="Ej. despacho@logicoffee.test" requerido />

            <div>
                <label for="rol" class="block text-sm font-semibold text-coffee-800">
                    Rol <span class="text-ladrillo-500" aria-hidden="true">*</span>
                </label>
                <select id="rol" name="rol"
                    class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15">
                    @foreach (\App\Enums\Rol::cases() as $rol)
                        <option value="{{ $rol->value }}" @selected(old('rol') === $rol->value)>{{ $rol->value }}</option>
                    @endforeach
                </select>
                @error('rol')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </div>

            <x-campo-texto nombre="password" etiqueta="Contraseña" tipo="password" marcador="Mínimo 8 caracteres" requerido />
            <x-campo-texto nombre="password_confirmation" etiqueta="Repetir contraseña" tipo="password" requerido />

            <div class="sm:col-span-2 lg:col-span-3">
                <x-campo-texto nombre="descripcion" etiqueta="Descripción (opcional)"
                    marcador="Ej. Coordina la preparación y entrega de pedidos" />
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <button type="submit"
                    class="rounded-xl bg-coffee-500 px-6 py-3 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30">
                    Crear cuenta
                </button>
            </div>
        </form>
    </section>

    <div class="mt-8 space-y-5">
        @foreach ($usuarios as $usuario)
            @php($bolsa = 'usuario-'.$usuario->id)
            @php($fallo = $errors->getBag($bolsa)->any())
            @php($esPropia = $usuario->is($usuarioEnSesion))

            <article @class([
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
                            <form method="POST" action="{{ route('usuarios.estado.update', $usuario) }}">
                                @csrf @method('PATCH')
                                <button type="submit" @class([
                                    'rounded-full border-2 px-4 py-1.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-4',
                                    'border-ladrillo-500 text-ladrillo-500 hover:bg-ladrillo-500 hover:text-white focus-visible:ring-ladrillo-500/25' => $usuario->activo,
                                    'border-coffee-500 text-coffee-600 hover:bg-coffee-500 hover:text-white focus-visible:ring-coffee-500/25' => ! $usuario->activo,
                                ])>
                                    {{ $usuario->activo ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        @endunless
                    </div>
                </div>

                <form method="POST" action="{{ route('usuarios.update', $usuario) }}"
                    class="mt-5 grid gap-4 border-t border-coffee-200 pt-5 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf @method('PATCH')

                    <div>
                        <label for="name-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">Nombre</label>
                        <input type="text" id="name-{{ $usuario->id }}" name="name" required
                            value="{{ $fallo ? old('name') : $usuario->name }}"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        @error('name', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">Correo</label>
                        <input type="email" id="email-{{ $usuario->id }}" name="email" required
                            value="{{ $fallo ? old('email') : $usuario->email }}"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        @error('email', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="rol-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">Rol</label>
                        <select id="rol-{{ $usuario->id }}" name="rol" @disabled($esPropia)
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15 disabled:cursor-not-allowed disabled:bg-coffee-100 disabled:text-coffee-700/50">
                            @foreach (\App\Enums\Rol::cases() as $rol)
                                <option value="{{ $rol->value }}" @selected(($fallo ? old('rol') : $usuario->rol->value) === $rol->value)>
                                    {{ $rol->value }}
                                </option>
                            @endforeach
                        </select>
                        @if ($esPropia)
                            {{-- Un select deshabilitado no viaja en el envío. --}}
                            <input type="hidden" name="rol" value="{{ $usuario->rol->value }}" />
                            <p class="mt-1.5 text-xs text-coffee-700/50">Nadie cambia su propio rol.</p>
                        @endif
                        @error('rol', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">
                            Nueva contraseña
                        </label>
                        <input type="password" id="password-{{ $usuario->id }}" name="password" autocomplete="new-password"
                            placeholder="Dejar vacío para no cambiarla"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        @error('password', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">
                            Repetir contraseña
                        </label>
                        <input type="password" id="password_confirmation-{{ $usuario->id }}" name="password_confirmation"
                            autocomplete="new-password" placeholder="Solo si la cambias"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                    </div>

                    <div class="sm:col-span-2 lg:col-span-2">
                        <label for="descripcion-{{ $usuario->id }}" class="block text-sm font-semibold text-coffee-800">Descripción</label>
                        <input type="text" id="descripcion-{{ $usuario->id }}" name="descripcion"
                            value="{{ $fallo ? old('descripcion') : $usuario->descripcion }}"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        @error('descripcion', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full rounded-xl border border-coffee-300 bg-white py-2.5 font-semibold text-coffee-700 transition hover:border-coffee-500 hover:bg-coffee-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/20">
                            Guardar cambios
                        </button>
                    </div>

                </form>
            </article>
        @endforeach
    </div>

</x-layouts.app>
