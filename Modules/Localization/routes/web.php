<?php

use Illuminate\Support\Facades\Route;
use Modules\Localization\Http\Controllers\LocalizationController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('localizations', LocalizationController::class)->names('localization');
});
