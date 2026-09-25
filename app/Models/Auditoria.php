<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro de quién creó, modificó o eliminó un pedido o una cuenta, y cuándo.
 * Solo se escribe: nadie edita la bitácora.
 */
#[Fillable(['user_id', 'accion', 'auditable_type', 'auditable_id', 'cambios', 'ip'])]
class Auditoria extends Model
{
    public const UPDATED_AT = null;

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cambios' => 'array',
        ];
    }
}
