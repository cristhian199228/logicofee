<?php

use Illuminate\Support\Facades\Route;

// Cada rol entra por la primera sección de su menú.
Route::get('/', function () {
    $usuario = auth()->user();

    return $usuario === null
        ? redirect()->route('login')
        : redirect()->route($usuario->seccionInicial()->ruta());
})->name('home');

Route::middleware('guest')->group(function () {
    Route::livewire('login', 'login')->name('login');
});

/*
 * Toda la aplicación son componentes Livewire de página completa: cada
 * pantalla resuelve sus propias acciones sin recargar el navegador.
 */
Route::middleware(['auth', 'cuenta.activa'])->group(function () {
    Route::middleware('seccion:catalogo')->group(function () {
        Route::livewire('catalogo', 'catalogo')->name('catalogo.index');
    });

    Route::middleware('seccion:pedido')->group(function () {
        Route::livewire('pedido', 'pedido-registrar')->name('pedidos.create');
    });

    Route::middleware('seccion:historial')->group(function () {
        Route::livewire('pedidos', 'pedido-historial')->name('pedidos.index');
    });

    Route::middleware('seccion:seguimiento')->group(function () {
        Route::livewire('seguimiento', 'seguimiento')->name('seguimiento.index');
    });

    Route::middleware('seccion:lotes')->group(function () {
        Route::livewire('lotes', 'lotes')->name('lotes.index');
    });

    Route::middleware('seccion:calidad')->group(function () {
        Route::livewire('calidad', 'calidad')->name('calidad.index');
    });

    Route::middleware('seccion:promociones')->group(function () {
        Route::livewire('promociones', 'promociones')->name('promociones.index');
    });

    Route::middleware('seccion:reportes')->group(function () {
        Route::livewire('reportes', 'reportes')->name('reportes.index');
    });

    Route::middleware('seccion:usuarios')->group(function () {
        Route::livewire('usuarios', 'usuarios')->name('usuarios.index');
    });

    // Paneles por área empresarial: comercial, almacén y producción.
    Route::middleware('seccion:ventas')->group(function () {
        Route::livewire('ventas', 'ventas')->name('ventas.index');
    });

    Route::middleware('seccion:almacen')->group(function () {
        Route::livewire('almacen', 'almacen')->name('almacen.index');
    });

    Route::middleware('seccion:produccion')->group(function () {
        Route::livewire('produccion', 'produccion')->name('produccion.index');
    });

    Route::middleware('seccion:productos')->group(function () {
        Route::livewire('productos', 'productos')->name('productos.index');
    });
});
