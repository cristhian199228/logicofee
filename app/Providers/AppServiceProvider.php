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

        // Cada área de la empresa mueve la parte del sistema que le toca.
        Gate::define('gestionar-inventario', fn (User $usuario) => $usuario->rol->puedeMoverAlmacen());
        Gate::define('despachar-pedidos', fn (User $usuario) => $usuario->rol->puedeDespacharPedidos());
        Gate::define('controlar-calidad', fn (User $usuario) => $usuario->rol->puedeControlarCalidad());
        Gate::define('registrar-cobros', fn (User $usuario) => $usuario->rol->puedeRegistrarCobros());
        Gate::define('editar-catalogo', fn (User $usuario) => $usuario->rol->puedeEditarCatalogo());
        Gate::define('gestionar-promociones', fn (User $usuario) => $usuario->rol->puedeGestionarPromociones());

        // Las cuentas de usuario solo las toca el administrador.
        Gate::define('gestionar-usuarios', fn (User $usuario) => $usuario->rol->esAdministrador());
    }
}
