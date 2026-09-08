<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Rol;
use App\Enums\Seccion;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['username', 'name', 'email', 'password', 'rol', 'iniciales', 'descripcion', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    /** Lotes que este usuario evaluó en el control de calidad (HU07). */
    public function lotesEvaluados(): HasMany
    {
        return $this->hasMany(Lote::class, 'evaluado_por');
    }

    public function puedeVer(Seccion $seccion): bool
    {
        return $this->activo && $this->rol->puedeVer($seccion);
    }

    /** Iniciales que se muestran cuando la cuenta no las tiene registradas. */
    public function inicialesVisibles(): string
    {
        return $this->iniciales ?: Str::upper(Str::substr($this->name, 0, 2));
    }

    /**
     * Sección con la que arranca la sesión: la primera del menú del rol.
     */
    public function seccionInicial(): Seccion
    {
        return $this->rol->secciones()[0];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'boolean',
        ];
    }
}
