<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', function () {
    return response()->file(public_path('docs.html'));
});

Route::get('/api/documentation', function () {
    return response()->file(public_path('docs.html'));
});

Route::get('/swagger.json', function () {
    return response()->file(public_path('swagger.json'));
});
