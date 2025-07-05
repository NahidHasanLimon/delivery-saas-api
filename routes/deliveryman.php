<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeliveryMan\DeliveryManAuthController;

Route::post('login', [DeliveryManAuthController::class, 'login']);
Route::post('logout', [DeliveryManAuthController::class, 'logout'])->middleware('auth:delivery_man');
Route::post('refresh', [DeliveryManAuthController::class, 'refresh'])->middleware('auth:delivery_man');
Route::get('me', [DeliveryManAuthController::class, 'me'])->middleware('auth:delivery_man');
