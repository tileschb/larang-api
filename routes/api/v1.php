<?php

use Illuminate\Support\Facades\Route;

Route::post('register', \App\Http\Controllers\V1\RegisterController::class)
    ->name('register');

Route::middleware('auth:sanctum')
    ->prefix('auth')
    ->as('auth.')
    ->group(function () {

    Route::post('login', [\App\Http\Controllers\V1\AuthController::class, 'login'])
        ->withoutMiddleware('auth:sanctum')
        ->name('login');

    Route::post('logout', [\App\Http\Controllers\V1\AuthController::class, 'logout'])
        ->name('logout');
    Route::get('me', [\App\Http\Controllers\V1\AuthController::class, 'me'])
        ->name('who-am-i');

    // expects a refresh token as bearer token
    Route::post('refresh', [\App\Http\Controllers\V1\AuthController::class, 'refresh'])
        ->name('token-refresh'); // !!!! WARNING, route name is checked in AppServiceProvider.php
});


