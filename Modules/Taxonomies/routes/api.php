<?php

use Illuminate\Support\Facades\Route;
use Modules\Taxonomies\Http\Controllers\TaxonomiesController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('taxonomies', TaxonomiesController::class)->names('taxonomies');
});
