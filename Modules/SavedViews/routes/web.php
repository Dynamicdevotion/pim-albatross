<?php

use Illuminate\Support\Facades\Route;
use Modules\SavedViews\Http\Controllers\SavedViewsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('savedviews', SavedViewsController::class)->names('savedviews');
});
