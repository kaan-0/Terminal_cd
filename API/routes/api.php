<?php

use App\Http\Controllers\Api\MedicionController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/v1/mediciones',
    [MedicionController::class, 'store']
);