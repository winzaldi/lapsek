<?php

use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => response()->json(['status' => 'ok']))
    ->name('api.v1.health');
