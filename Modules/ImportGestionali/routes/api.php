<?php

use Illuminate\Support\Facades\Route;
use Modules\ImportGestionali\Http\Controllers\ImportGestionaliController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('importgestionalis', ImportGestionaliController::class)->names('importgestionali');
});
