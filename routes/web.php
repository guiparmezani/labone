<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClockController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\TimeLogController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('entrar');
    Route::post('/entrar', [LoginController::class, 'store'])->name('entrar.store');
});

Route::middleware(['auth', 'ativo'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('inicio');
    Route::post('/sair', [LoginController::class, 'destroy'])->name('sair');

    Route::get('/projetos', [ProjectController::class, 'index'])->name('projetos.index');
    Route::get('/projetos/novo', [ProjectController::class, 'create'])->name('projetos.create');
    Route::post('/projetos', [ProjectController::class, 'store'])->name('projetos.store');
    Route::get('/projetos/{project}', [ProjectController::class, 'show'])->name('projetos.show');
    Route::get('/projetos/{project}/editar', [ProjectController::class, 'edit'])->name('projetos.edit');
    Route::put('/projetos/{project}', [ProjectController::class, 'update'])->name('projetos.update');
    Route::post('/projetos/{project}/encerrar', [ProjectController::class, 'close'])->name('projetos.close');
    Route::post('/projetos/{project}/reabrir', [ProjectController::class, 'reopen'])->name('projetos.reopen');
    Route::delete('/projetos/{project}', [ProjectController::class, 'destroy'])->name('projetos.destroy');
    Route::post('/projetos/{project}/subtarefas', [SubtaskController::class, 'store'])->name('subtarefas.store');
    Route::get('/projetos/{project}/ponto', [ClockController::class, 'show'])->name('projetos.ponto');
    Route::post('/ponto/iniciar', [ClockController::class, 'start'])->name('ponto.start');
    Route::post('/ponto/parar', [ClockController::class, 'stop'])->name('ponto.stop');

    Route::middleware('papel:admin,leader')->group(function () {
        Route::get('/lancamentos', [TimeLogController::class, 'index'])->name('lancamentos.index');
        Route::get('/lancamentos/novo', [TimeLogController::class, 'create'])->name('lancamentos.create');
        Route::post('/lancamentos', [TimeLogController::class, 'store'])->name('lancamentos.store');
        Route::get('/lancamentos/{timeLog}/editar', [TimeLogController::class, 'edit'])->name('lancamentos.edit');
        Route::put('/lancamentos/{timeLog}', [TimeLogController::class, 'update'])->name('lancamentos.update');
        Route::delete('/lancamentos/{timeLog}', [TimeLogController::class, 'destroy'])->name('lancamentos.destroy');
    });
    Route::get('/subtarefas/{subtask}/editar', [SubtaskController::class, 'edit'])->name('subtarefas.edit');
    Route::put('/subtarefas/{subtask}', [SubtaskController::class, 'update'])->name('subtarefas.update');
    Route::delete('/subtarefas/{subtask}', [SubtaskController::class, 'destroy'])->name('subtarefas.destroy');

    Route::middleware('papel:admin')->group(function () {
        Route::get('/relatorios', [ReportController::class, 'index'])->name('relatorios.index');
        Route::get('/relatorios/horas.csv', [ReportController::class, 'hours'])->name('relatorios.hours');
        Route::get('/relatorios/projetos.csv', [ReportController::class, 'projects'])->name('relatorios.projects');

        Route::resource('usuarios', UserController::class)
            ->except(['show', 'destroy'])
            ->parameters(['usuarios' => 'user']);
    });
});
