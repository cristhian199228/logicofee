<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AvancePedidoController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProductoFotoController;
use App\Http\Controllers\SeguimientoController;
use Illuminate\Support\Facades\Route;

// Cada rol entra por la primera sección de su menú.
Route::get('/', function () {
    $usuario = auth()->user();

    return $usuario === null
        ? redirect()->route('login')
        : redirect()->route($usuario->seccionInicial()->ruta());
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('seccion:catalogo')->group(function () {
        Route::get('catalogo', [CatalogoController::class, 'index'])->name('catalogo.index');
    });

    Route::middleware('seccion:pedido')->group(function () {
        Route::get('pedido', [PedidoController::class, 'create'])->name('pedidos.create');
        Route::post('pedidos', [PedidoController::class, 'store'])->name('pedidos.store');

        Route::post('carrito', [CarritoController::class, 'store'])->name('carrito.store');
        Route::patch('carrito/{producto}', [CarritoController::class, 'update'])->name('carrito.update');
        Route::delete('carrito/{producto}', [CarritoController::class, 'destroy'])->name('carrito.destroy');
    });

    Route::middleware('seccion:historial')->group(function () {
        Route::get('pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
    });

    Route::middleware('seccion:seguimiento')->group(function () {
        Route::get('seguimiento', [SeguimientoController::class, 'index'])->name('seguimiento.index');
    });

    Route::middleware('seccion:lotes')->group(function () {
        Route::get('lotes', [LoteController::class, 'index'])->name('lotes.index');
    });

    // Solo administrador y proveedor mueven almacén y entregas.
    Route::middleware('can:gestionar-inventario')->group(function () {
        Route::post('pedidos/{pedido}/avance', [AvancePedidoController::class, 'store'])->name('pedidos.avance.store');
        Route::post('lotes', [LoteController::class, 'store'])->name('lotes.store');
        Route::post('productos/{producto}/foto', [ProductoFotoController::class, 'update'])->name('productos.foto.update');
        Route::delete('productos/{producto}/foto', [ProductoFotoController::class, 'destroy'])->name('productos.foto.destroy');
    });
});
