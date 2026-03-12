<?php

use Illuminate\Support\Facades\Route;

Route::get('/plugins/hello-world', function () {
    return response()->json([
        'plugin' => 'hello-world',
        'message' => 'Hello from the example plugin.',
    ]);
})->name('plugin.hello-world.index');
