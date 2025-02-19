<?php

use Illuminate\Support\Facades\Route;

Route::post('register', \App\Http\Controllers\V1\RegisterController::class)
    ->name('register');



