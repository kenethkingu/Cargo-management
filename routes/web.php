<?php

use App\Http\Controllers\CargoController;
use Illuminate\Support\Facades\Route;

// Upload and welcome page
Route::get('/', [CargoController::class, 'welcome'])->name('cargo.welcome'); 

// View data page
Route::get('/view', [CargoController::class, 'index'])->name('cargo.index'); 

// Import Excel
Route::post('/cargo/import', [CargoController::class, 'import'])->name('cargo.import');

Route::get('/cargos/progress/{batch}', [CargoController::class, 'importProgress'])
    ->name('import.progress'); 

Route::resource('cargo', CargoController::class)->except(['show', 'create', 'store']);