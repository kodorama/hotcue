<?php

use Illuminate\Support\Facades\Route;

Route::get('/settings', function () {
    return view('settings');
})->name('settings');

