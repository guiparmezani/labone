<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('entrar');
    Route::post('/entrar', [LoginController::class, 'store'])->name('entrar.store');
});

Route::middleware(['auth', 'ativo'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('inicio');
    Route::post('/sair', [LoginController::class, 'destroy'])->name('sair');

    Route::middleware('papel:admin')->group(function () {
        Route::resource('usuarios', UserController::class)
            ->except(['show', 'destroy'])
            ->parameters(['usuarios' => 'user']);
    });
});
