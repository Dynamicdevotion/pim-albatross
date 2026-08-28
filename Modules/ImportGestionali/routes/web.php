<?php

use Illuminate\Support\Facades\Route;
use Modules\ImportGestionali\Http\Controllers\ImportGestionaliController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('importgestionalis', ImportGestionaliController::class)->names('importgestionali');
});
