<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Anota en la bitácora de auditoría cada alta, cambio y baja del modelo, con
 * el usuario que lo hizo. Las contraseñas y tokens nunca se copian: solo se
 * deja constancia de que cambiaron.
 */
trait Auditable
{
    /** Columnas que no aportan al rastro o que no deben salir de su tabla. */
    private const COLUMNAS_OMITIDAS = ['created_at', 'updated_at', 'remember_token'];

    private const COLUMNAS_SECRETAS = ['password'];

    public static function bootAuditable(): void
    {
        static::created(fn (Model $modelo) => $modelo->auditar('creado', $modelo->cambiosDeAlta()));

        static::updated(function (Model $modelo) {
            $cambios = $modelo->cambiosDeEdicion();

            if ($cambios !== []) {
                $modelo->auditar('actualizado', $cambios);
            }
        });

        static::deleted(fn (Model $modelo) => $modelo->auditar('eliminado'));
    }

    public function auditorias(): MorphMany
    {
        return $this->morphMany(Auditoria::class, 'auditable');
    }

    /**
     * @param  array<string, mixed>|null  $cambios
     */
    private function auditar(string $accion, ?array $cambios = null): void
    {
        $this->auditorias()->create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'cambios' => $cambios,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cambiosDeAlta(): array
    {
        $atributos = collect($this->getAttributes())->except([...self::COLUMNAS_OMITIDAS, $this->getKeyName()]);

        return $atributos
            ->map(fn (mixed $valor, string $columna) => in_array($columna, self::COLUMNAS_SECRETAS, true) ? '[registrada]' : $valor)
            ->all();
    }

    /**
     * @return array<string, array{antes: mixed, despues: mixed}|string>
     */
    private function cambiosDeEdicion(): array
    {
        return collect($this->getChanges())
            ->except(self::COLUMNAS_OMITIDAS)
            ->map(fn (mixed $valor, string $columna) => in_array($columna, self::COLUMNAS_SECRETAS, true)
                ? '[modificada]'
                : ['antes' => $this->getRawOriginal($columna), 'despues' => $valor])
            ->all();
    }
}
