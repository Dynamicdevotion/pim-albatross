<?php

use Illuminate\Support\Facades\Route;
use Modules\SavedViews\Http\Controllers\SavedViewsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('savedviews', SavedViewsController::class)->names('savedviews');
});
