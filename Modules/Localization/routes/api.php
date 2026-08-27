<?php

use Illuminate\Support\Facades\Route;
use Modules\Localization\Http\Controllers\LocalizationController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('localizations', LocalizationController::class)->names('localization');
});
