<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Activa o desactiva una cuenta de usuario (HU09).
 */
class EstadoUsuarioController extends Controller
{
    public function update(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('aviso', 'No puedes desactivar tu propia cuenta.');
        }

        $usuario->update(['activo' => ! $usuario->activo]);

        return back()->with('aviso', $usuario->activo
            ? "La cuenta {$usuario->username} quedó activa."
            : "La cuenta {$usuario->username} quedó desactivada y ya no puede iniciar sesión.");
    }
}
