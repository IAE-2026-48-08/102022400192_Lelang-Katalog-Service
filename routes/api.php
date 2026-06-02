<?php

use App\Http\Controllers\ItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.key')->group(function () {

    // Katalog Service
    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/items/{id}', [ItemController::class, 'show']);
    Route::post('/items/filter', [ItemController::class, 'filter']);

});