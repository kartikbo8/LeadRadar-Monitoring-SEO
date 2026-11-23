<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompetitorController;
use App\Http\Controllers\HealthMonitorController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SearchConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('search-console.index');
});

Route::get('/companies', [CompanyController::class, 'index'])
    ->middleware('throttle:10,1')
    ->name('companies.index');

Route::get('/competitors', [CompetitorController::class, 'index'])
    ->middleware('throttle:5,1')
    ->name('competitors.index');

Route::get('/competitors/export', [CompetitorController::class, 'export'])
    ->middleware('throttle:5,1')
    ->name('competitors.export');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegistration'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->prefix('health-monitor')->name('health-monitor.')->group(function () {
    Route::get('/', [HealthMonitorController::class, 'index'])->name('index');
    Route::post('/sites', [HealthMonitorController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('sites.store');
    Route::post('/sites/{siteId}/verify', [HealthMonitorController::class, 'verify'])
        ->middleware('throttle:5,1')
        ->name('sites.verify');
    Route::post('/sites/{siteId}/scan', [HealthMonitorController::class, 'scan'])
        ->middleware('throttle:3,1')
        ->name('sites.scan');
    Route::patch('/sites/{siteId}', [HealthMonitorController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('sites.update');
    Route::get('/sites/{siteId}/runs/{runId}/download', [HealthMonitorController::class, 'downloadReport'])
        ->whereNumber(['siteId', 'runId'])
        ->name('sites.runs.download');
    Route::patch('/sites/{siteId}/toggle', [HealthMonitorController::class, 'toggle'])
        ->name('sites.toggle');
    Route::delete('/sites/{siteId}', [HealthMonitorController::class, 'destroy'])
        ->name('sites.destroy');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::prefix('search-console')->name('search-console.')->group(function () {
    Route::get('/', [SearchConsoleController::class, 'index'])->name('index');
    Route::get('/connect', [SearchConsoleController::class, 'connect'])->name('connect');
    Route::get('/callback', [SearchConsoleController::class, 'callback'])->name('callback');
    Route::post('/disconnect', [SearchConsoleController::class, 'disconnect'])->name('disconnect');
});
