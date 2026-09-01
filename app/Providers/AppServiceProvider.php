<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Carrito;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Una sola instancia por petición para no releer los productos del carrito.
        $this->app->scoped(Carrito::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Vite::prefetch(concurrency: 3);

        // Mover el almacén y avanzar entregas es cosa de administrador y proveedor.
        Gate::define('gestionar-inventario', fn (User $usuario) => $usuario->rol->puedeGestionarInventario());
    }
}
