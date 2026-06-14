<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DesignController;

Route::apiResource('categories', CategoryController::class);
Route::apiResource('designs', DesignController::class);
Route::patch('/designs/{design}/featured', [DesignController::class, 'toggleFeatured']);