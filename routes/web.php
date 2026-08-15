<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;

Route::get('/dashboard',[AdminController::class,'dashboard']);
Route::post('/dashboard/update',[AdminController::class,'updateStatus'])->name('admin.updateStatus');
