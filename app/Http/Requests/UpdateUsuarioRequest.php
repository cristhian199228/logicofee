<?php

namespace App\Http\Requests;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUsuarioRequest extends FormRequest
{
    /**
     * Cada cuenta tiene su propio formulario en la pantalla, así que los
     * errores viajan en una bolsa propia.
     */
    protected function prepareForValidation(): void
    {
        /** @var User $usuario */
        $usuario = $this->route('usuario');

        $this->errorBag = 'usuario-'.$usuario->id;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User $usuario */
        $usuario = $this->route('usuario');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignoreModel($usuario)],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
            'rol' => ['required', Rule::enum(Rol::class)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'rol' => 'rol',
            'descripcion' => 'descripción',
        ];
    }
}
