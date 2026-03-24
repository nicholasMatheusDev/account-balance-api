<?php

use App\Http\Controllers\BalanceController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ResetController;
use Illuminate\Support\Facades\Route;

Route::post('/reset', ResetController::class);
Route::post('/event', EventController::class);
Route::get('/balance', BalanceController::class);
