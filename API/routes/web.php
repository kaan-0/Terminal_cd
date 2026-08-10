<?php

use App\Http\Controllers\AdministracionClienteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'login'])
        ->name('login.store');
});

Route::middleware(['auth', 'user.active'])->group(function (): void {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

    Route::prefix('administracion')
        ->name('admin.')
        ->middleware('admin')
        ->group(function (): void {
            Route::get(
                '/clientes',
                [AdministracionClienteController::class, 'index']
            )->name('clientes.index');

            Route::post(
                '/clientes',
                [AdministracionClienteController::class, 'storeCliente']
            )->name('clientes.store');

            Route::post(
                '/clientes/{cliente}/dispositivos',
                [AdministracionClienteController::class, 'storeDispositivo']
            )->name('dispositivos.store');

            Route::get(
                '/documentos/configuracion',
                [AdministracionClienteController::class, 'descargarDocumentoConfiguracion']
)->name('documentos.configuracion');

            Route::post(
                '/clientes/{cliente}/usuarios',
                [AdministracionClienteController::class, 'storeUsuario']
            )->name('usuarios.store');

            Route::patch(
                '/clientes/{cliente}/estado',
                [AdministracionClienteController::class, 'cambiarEstadoCliente']
            )->name('clientes.estado');

            Route::patch(
                '/dispositivos/{dispositivo}/estado',
                [AdministracionClienteController::class, 'cambiarEstadoDispositivo']
            )->name('dispositivos.estado');

            Route::put(
                '/dispositivos/{dispositivo}/sensores/{ranura}',
                [AdministracionClienteController::class, 'guardarSensor']
            )->name('sensores.guardar');

            Route::put(
                '/sensores/{sensor}/lecturas',
                [AdministracionClienteController::class, 'guardarLecturasSensor']
            )->name('sensores.lecturas.guardar');

            Route::patch(
                '/sensores/{sensor}/estado',
                [AdministracionClienteController::class, 'cambiarEstadoSensor']
            )->name('sensores.estado');

            Route::post(
                '/dispositivos/{dispositivo}/regenerar-token',
                [AdministracionClienteController::class, 'regenerarToken']
            )->name('dispositivos.regenerar-token');

            Route::patch(
                '/usuarios/{usuario}/estado',
                [AdministracionClienteController::class, 'cambiarEstadoUsuario']
            )->name('usuarios.estado');

            Route::patch(
                '/usuarios/{usuario}/password',
                [AdministracionClienteController::class, 'actualizarPasswordUsuario']
            )->name('usuarios.password');
        });
});
