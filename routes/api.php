<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('v2/login', [App\Http\Controllers\API\AuthController::class, 'login']);

Route::group(['middleware' => 'auth:api'], function () {
    Route::get('v2/get-dashboard/', [App\Http\Controllers\API\DashboardController::class, 'index']);

    Route::get('v2/get-stock/', [App\Http\Controllers\API\DashboardController::class, 'stock']);

    Route::get('v2/list-production', [App\Http\Controllers\API\ProductionController::class, 'index']);
    Route::post('v2/add-production', [App\Http\Controllers\API\ProductionController::class, 'add']);
    Route::get('v2/get-data-production/{flag}/{id}', [App\Http\Controllers\API\ProductionController::class, 'indexEdit']);
    Route::post('v2/edit-production', [App\Http\Controllers\API\ProductionController::class, 'edit']);
    Route::get('v2/summary-production', [App\Http\Controllers\API\ProductionController::class, 'summary']);
    Route::post('v2/add-transaction-hash-production', [App\Http\Controllers\API\ProductionController::class, 'addHash']);
    
    Route::get('v2/list-logistic', [App\Http\Controllers\API\LogisticController::class, 'index']);
    Route::get('v2/get-buyer-by-date/{date}', [App\Http\Controllers\API\LogisticController::class, 'getBuyer']);
    Route::get('v2/get-sugar/{buyer}/{date}', [App\Http\Controllers\API\LogisticController::class, 'getSugar']);
    Route::get('v2/get-maxlength/{buyer}/{date}/{sugar}', [App\Http\Controllers\API\LogisticController::class, 'getMaxlength']);
    Route::post('v2/add-logistic', [App\Http\Controllers\API\LogisticController::class, 'add']);
    Route::get('v2/get-data-logistic/{flag}/{id}', [App\Http\Controllers\API\LogisticController::class, 'indexEdit']);
    Route::post('v2/edit-logistic', [App\Http\Controllers\API\LogisticController::class, 'edit']);
    Route::get('v2/summary-logistic', [App\Http\Controllers\API\LogisticController::class, 'summary']);
    Route::post('v2/add-transaction-hash-logistics', [App\Http\Controllers\API\LogisticController::class, 'addHash']);
    
    Route::get('v2/sales', [App\Http\Controllers\API\SalesController::class, 'index']);
    Route::post('v2/add-sales', [App\Http\Controllers\API\SalesController::class, 'add']);
    Route::get('v2/summary-sales', [App\Http\Controllers\API\SalesController::class, 'summary']);
    Route::get('v2/get-data-sales/{id}', [App\Http\Controllers\API\SalesController::class, 'indexEdit']);
    Route::post('v2/edit-sales', [App\Http\Controllers\API\SalesController::class, 'edit']);
    Route::post('v2/add-transaction-hash-sales', [App\Http\Controllers\API\SalesController::class, 'addHash']);

    Route::get('v2/list-user', [App\Http\Controllers\API\PabrikUserController::class, 'listUser']);
    Route::post('v2/add-user', [App\Http\Controllers\API\PabrikUserController::class, 'addUser']);

    Route::get('v2/delete-data/{flag}/{id}', [App\Http\Controllers\API\PabrikController::class, 'deleteData']);
});