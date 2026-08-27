<?php

use Illuminate\Support\Facades\Route;
use Modules\Taxonomies\Http\Controllers\TaxonomiesController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('taxonomies', TaxonomiesController::class)->names('taxonomies');
});
