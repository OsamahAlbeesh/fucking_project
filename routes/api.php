<?php

use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\GovernorateCityController;
use App\Http\Controllers\LandlordController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\Admin;
use App\Http\Middleware\Customer;
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

Route::post('verifyUser', [AdminController::class, 'verifyUser'])
    ->middleware('auth:sanctum', Admin::class)
    ->name('admin.verifyUser');
// #################################################################################

Route::get('/governorates', [GovernorateCityController::class, 'getGovernorates']);
Route::get('/cities', [GovernorateCityController::class, 'getCities']);

Route::get('/properties', [PropertyController::class, 'getAllProperties'])
    ->middleware('auth:sanctum');
Route::get('/property/{id}', [PropertyController::class, 'getPropertyDetails'])
    ->middleware('auth:sanctum');
Route::post('filter', [PropertyController::class, 'search']);


Route::post('customer/fav/{property}', [FavoriteController::class, 'toggleFavoriteAlt'])
    ->middleware('auth:sanctum', Customer::class);

Route::get('customer/fav', [FavoriteController::class, 'getMyFavoritesSimple'])
    ->middleware('auth:sanctum', Customer::class);

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});



Route::middleware(['auth:sanctum', Landlord::class])->group(function () {
        Route::post('landlord/addproperty',[LandlordController::class,'addProperty']);
        Route::delete('landlord/removeproperty',[LandlordController::class,'removeProperty']);
        Route::post('landlord/{property_id}/updateproperty',[LandlordController::class,'updatePropertyDetails']);
        Route::get('landlord/getproperties',[LandlordController::class,'getProperties']);
        Route::get('landlord/getPendingRents',[LandlordController::class,'pendingReservations']);
        Route::put('landlord/responsToRequsets',[LandlordController::class,'respondToReservation']);
        Route::get('landlord/getAllReservations',[LandlordController::class,'getAllReservations']);
    });

    Route::middleware(['auth:sanctum',Customer::class])->group(function (){
        Route::post('customer/buy',[CustomerController::class,'buyProperty'])->middleware('auth:sanctum');
        Route::post('customer/rent',[CustomerController::class,'reserveProperty'])->middleware('auth:sanctum');
        Route::post('/payment/stripe/checkout', [StripeController::class, 'createCheckoutSession']);
        Route::put('customer/rent/{property_id}',[CustomerController::class,'updateReservation'])->middleware('auth:sanctum');
        Route::delete('customer/rent',[CustomerController::class,'cancelReservation'])->middleware('auth:sanctum');
        Route::post('customer/rateProperty',[CustomerController::class,'rateProperty'])->middleware('auth:sanctum');
        Route::get('customer/myReservation',[CustomerController::class,'getMyReservation']);
});
