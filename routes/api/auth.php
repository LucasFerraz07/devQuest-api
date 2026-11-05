<?php

use App\Http\Controllers\Auth\Auth;
use Illuminate\Support\Facades\Route;

Route::post('/register', [Auth::class, 'register'])->middleware('throttle:registration');
Route::post('/login', [Auth::class, 'login'])->middleware('throttle:login');
Route::post('/refresh', [Auth::class, 'refresh'])->middleware('throttle:refresh');
Route::middleware('auth.api')->group(function () {
    Route::get('/me', [Auth::class, 'me']);
    Route::post('/logout', [Auth::class, 'logout']);
});
