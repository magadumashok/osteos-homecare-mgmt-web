<?php

use Illuminate\Support\Facades\Route;

// Single-page React app: every non-API path renders the shell and React
// Router takes over.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api(/|$)).*$');
