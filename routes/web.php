<?php

use App\Http\Controllers\CargoController;
use Illuminate\Support\Facades\Route;

// Upload and welcome page
Route::get('/', [CargoController::class, 'welcome'])->name('cargo.welcome'); // no table

// View data page
Route::get('/view', [CargoController::class, 'index'])->name('cargo.index'); // fetch table

// Import Excel
Route::post('/cargo/import', [CargoController::class, 'import'])->name('cargo.import');

// Resource routes
Route::resource('cargo', CargoController::class);
