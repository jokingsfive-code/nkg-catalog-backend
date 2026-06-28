<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DesignController;

function checkAdminKey(Request $request)
{
    $adminKey = env('ADMIN_API_KEY');

    if (!$adminKey || $request->header('X-Admin-Key') !== $adminKey) {
        abort(403, 'Unauthorized admin action.');
    }
}

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

Route::get('/designs', [DesignController::class, 'index']);
Route::get('/design/code/{code}', [DesignController::class, 'findByCode']);
Route::get('/designs/{design}', [DesignController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Admin protected routes
|--------------------------------------------------------------------------
*/
Route::post('/categories', function (Request $request) {
    checkAdminKey($request);
    return app(CategoryController::class)->store($request);
});

Route::patch('/categories/{category}', function (Request $request, \App\Models\Category $category) {
    checkAdminKey($request);
    return app(CategoryController::class)->update($request, $category);
});

Route::put('/categories/{category}', function (Request $request, \App\Models\Category $category) {
    checkAdminKey($request);
    return app(CategoryController::class)->update($request, $category);
});

Route::delete('/categories/{category}', function (Request $request, \App\Models\Category $category) {
    checkAdminKey($request);
    return app(CategoryController::class)->destroy($category);
});

Route::post('/designs', function (Request $request) {
    checkAdminKey($request);
    return app(DesignController::class)->store($request);
});

Route::patch('/designs/{design}', function (Request $request, \App\Models\Design $design) {
    checkAdminKey($request);
    return app(DesignController::class)->update($request, $design);
});

Route::put('/designs/{design}', function (Request $request, \App\Models\Design $design) {
    checkAdminKey($request);
    return app(DesignController::class)->update($request, $design);
});

Route::delete('/designs/{design}', function (Request $request, \App\Models\Design $design) {
    checkAdminKey($request);
    return app(DesignController::class)->destroy($design);
});

Route::patch('/designs/{design}/featured', function (Request $request, \App\Models\Design $design) {
    checkAdminKey($request);
    return app(DesignController::class)->toggleFeatured($design);
});