<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rider\RiderAuthController;

Route::post('login', [RiderAuthController::class, 'login']);
Route::post('activate', [RiderAuthController::class, 'activate']);
Route::post('password/request-otp', [RiderAuthController::class, 'requestPasswordResetOtp']);
Route::post('password/reset', [RiderAuthController::class, 'resetPassword']);
Route::post('logout', [RiderAuthController::class, 'logout'])->middleware('auth:rider');
Route::post('refresh', [RiderAuthController::class, 'refresh'])->middleware('auth:rider');
Route::get('me', [RiderAuthController::class, 'me'])->middleware('auth:rider');
