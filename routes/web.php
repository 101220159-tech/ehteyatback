<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Backend API — use /api routes from your frontend app',
        'health' => url('/api/health'),
    ]);
});
