<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\TurnstileLoginController;
use Illuminate\Support\Facades\Route;

// routes/web.php — session/cookie context is required for OAuth state and Sanctum SPA auth.
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

Route::post('/login', [TurnstileLoginController::class, 'login'])->name('login');
