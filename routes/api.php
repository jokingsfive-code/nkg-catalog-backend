<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DesignController;

$adminOnly = function (Request $request) {
    $adminKey = env('ADMIN_API_KEY');

    if (!$adminKey || $request->header('X-Admin-Key') !== $adminKey) {
        abort(403, 'Unauthorized admin action.');
    }
};

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

Route::get('/designs', [DesignController::class, 'index']);
Route::get('/designs/{design}', [DesignController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Admin protected routes
|--------------------------------------------------------------------------
*/
Route::middleware($adminOnly)->group(function () {
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::patch('/categories/{category}', [CategoryController::class, 'update']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    Route::post('/designs', [DesignController::class, 'store']);
    Route::patch('/designs/{design}', [DesignController::class, 'update']);
    Route::put('/designs/{design}', [DesignController::class, 'update']);
    Route::delete('/designs/{design}', [DesignController::class, 'destroy']);

    Route::patch('/designs/{design}/featured', [DesignController::class, 'toggleFeatured']);
});