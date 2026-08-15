<?php

use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FlatController;
use App\Http\Controllers\GovernorateCityController;
use App\Http\Controllers\LandlordController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\Admin;
use App\Http\Middleware\Tenant;
use App\Http\Middleware\Landlord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Routs for Authintications
Route::post('register',[UserController::class,'register']);
Route::post('login',[UserController::class,'login']);
Route::post('logout',[UserController::class,'logout'])->middleware('auth:sanctum');

Route::post('/stripe/webhook', [StripeController::class, 'handleWebhook']);
// #################################################################################


// Routs for Admin
Route::get('getUsers',[UserController::class,'getUsers'])
    ->middleware('auth:sanctum', Admin::class);

Route::post('verifyUser',[UserController::class,'verifyUser'])
    ->middleware('auth:sanctum', Admin::class);
// #################################################################################

Route::get('/governorates', [GovernorateCityController::class, 'getGovernorates']);
Route::get('/cities', [GovernorateCityController::class, 'getCities']);

Route::get('/flats', [FlatController::class, 'getAllFlats'])
    ->middleware('auth:sanctum');
Route::get('/flat/{id}', [FlatController::class, 'getFlatDetails']);

Route::post('filter', [FlatController::class, 'search']);


Route::post('tenant/fav/{flat}', [FavoriteController::class, 'toggleFavoriteAlt'])
    ->middleware('auth:sanctum', Tenant::class);

Route::get('tenant/fav', [FavoriteController::class, 'getMyFavoritesSimple'])
    ->middleware('auth:sanctum', Tenant::class);

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});


// للمؤجر فقط

Route::get('/payment-success-test', function (Illuminate\Http\Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'Stripe redirected you here successfully!',
        'session_id' => $request->query('session_id')
    ]);
});

Route::middleware(['auth:sanctum', Landlord::class])->group(function () {
        Route::post('landlord/addflat',[LandlordController::class,'addFlat']);
        Route::post('landlord/removeflat',[LandlordController::class,'removeFlat']);
        Route::post('landlord/{flat_id}/updateflat',[LandlordController::class,'updateFlatDetails']);
        Route::get('landlord/getflats',[LandlordController::class,'getFlats']);
        Route::get('landlord/getPendingRents',[LandlordController::class,'pendingReservations']);
        Route::put('landlord/responsToRequsets',[LandlordController::class,'respondToReservation']);
        Route::get('landlord/getAllReservations',[LandlordController::class,'getAllReservations']);
    });

    Route::middleware(['auth:sanctum',Tenant::class])->group(function (){
        Route::post('tenant/buy',[TenantController::class,'buyFlat'])->middleware('auth:sanctum');
        Route::post('tenant/rent',[TenantController::class,'reserveFlat'])->middleware('auth:sanctum');
        Route::post('/payment/stripe/checkout', [StripeController::class, 'createCheckoutSession']);
        Route::put('tenant/rent',[TenantController::class,'updateReservation'])->middleware('auth:sanctum');
        Route::delete('tenant/rent',[TenantController::class,'cancelReservation'])->middleware('auth:sanctum');
        Route::post('tenant/rateFlat',[TenantController::class,'rateFlat'])->middleware('auth:sanctum');
        Route::get('tenant/myReservation',[TenantController::class,'getMyReservation']);
});
