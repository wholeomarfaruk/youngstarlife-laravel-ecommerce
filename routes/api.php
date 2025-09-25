<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/steadfast/webhook', [App\Http\Controllers\Api\SteadFastController::class, 'webhook'])->name('steadfast.webhook');
