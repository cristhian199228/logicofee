<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Cuentas de usuario y sus roles (HU09).
 */
class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $usuarios = User::query()
            ->withCount('pedidos')
            ->orderBy('rol')
            ->orderBy('name')
            ->get();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'activos' => $usuarios->where('activo', true)->count(),
            'porRol' => collect(Rol::cases())->mapWithKeys(
                fn (Rol $rol) => [$rol->value => $usuarios->where('rol', $rol)->count()]
            ),
            'usuarioEnSesion' => $request->user(),
        ]);
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $usuario = User::create([
            ...$request->validated(),
            'iniciales' => $this->iniciales($request->string('name')->toString()),
            'activo' => true,
        ]);

        return back()->with('aviso', "Cuenta {$usuario->username} creada con el rol {$usuario->rol->value}.");
    }

    /**
     * Actualiza los datos y el rol de la cuenta. Nadie cambia su propio rol,
     * para que el sistema no se quede sin administrador.
     */
    public function update(UpdateUsuarioRequest $request, User $usuario): RedirectResponse
    {
        $datos = $request->safe()->except('password');

        if ($usuario->is($request->user())) {
            unset($datos['rol']);
        }

        if ($request->filled('password')) {
            $datos['password'] = $request->string('password')->toString();
        }

        $usuario->update([
            ...$datos,
            'iniciales' => $this->iniciales($request->string('name')->toString()),
        ]);

        return back()->with('aviso', "Se actualizó la cuenta {$usuario->username}.");
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
}
