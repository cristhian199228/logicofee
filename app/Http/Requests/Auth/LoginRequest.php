<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario.required' => 'Completa el usuario y la contraseña.',
            'password.required' => 'Completa el usuario y la contraseña.',
        ];
    }

    /**
     * Autentica las credenciales del formulario.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->asegurarQueNoEstaLimitado();

        $credenciales = [
            'username' => Str::lower($this->string('usuario')->trim()->toString()),
            'password' => $this->string('password')->toString(),
        ];

        if (! Auth::attempt($credenciales, $this->boolean('recordarme'))) {
            RateLimiter::hit($this->claveLimite());

            throw ValidationException::withMessages([
                'usuario' => 'Usuario o contraseña incorrectos.',
            ]);
        }

        RateLimiter::clear($this->claveLimite());
    }

    /**
     * @throws ValidationException
     */
    private function asegurarQueNoEstaLimitado(): void
    {
        if (! RateLimiter::tooManyAttempts($this->claveLimite(), 5)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'usuario' => trans('auth.throttle', [
                'seconds' => $segundos = RateLimiter::availableIn($this->claveLimite()),
                'minutes' => ceil($segundos / 60),
            ]),
        ]);
    }

    private function claveLimite(): string
    {
        return Str::transliterate(
            Str::lower($this->string('usuario')->toString()).'|'.$this->ip()
        );
    }
}
