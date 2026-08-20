<?php

use App\Http\Controllers\PropertyReportController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\GovernorateCityController;
use App\Http\Controllers\LandlordController;
use App\Http\Controllers\DemoPaymentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\Admin;
use App\Http\Middleware\Customer;
use App\Http\Middleware\Landlord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthOtpController;

// Routs for Authintications
Route::post('register',[UserController::class,'register']);
Route::post('login',[UserController::class,'login']);
Route::post('logout',[UserController::class,'logout'])->middleware('auth:sanctum');

// #################################################################################


// Routs for Admin
Route::get('getUsers',[UserController::class,'getUsers'])
    ->middleware('auth:sanctum', Admin::class);

Route::post('verifyUser', [AdminController::class, 'verifyUser'])
    ->middleware('auth:sanctum', Admin::class)
    ->name('admin.verifyUser');
Route::get('admin/disputes', [AdminController::class, 'getDisputes'])
    ->middleware('auth:sanctum', Admin::class);
Route::get('admin/reservations/ready-to-complete', [AdminController::class, 'getReservationsReadyToComplete'])
    ->middleware('auth:sanctum', Admin::class);
Route::post('admin/disputes/{disputeId}/resolve', [AdminController::class, 'resolveDispute'])
    ->middleware('auth:sanctum', Admin::class);
Route::post('admin/payments/{transactionId}/refund-demo', [AdminController::class, 'refund  DemoPayment'])
    ->middleware('auth:sanctum', Admin::class);
Route::post('admin/reservations/{bookingId}/complete', [AdminController::class, 'completeReservation'])
    ->middleware('auth:sanctum', Admin::class);
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
        // المبلغ يُحسب في الخادم ويُنقل من رصيد العميل إلى رصيد المالك بمحاكاة demo.
        Route::get('customer/reservations/{bookingId}/payment', [DemoPaymentController::class, 'getPaymentDocument']);
        Route::post('customer/payment/demo', [DemoPaymentController::class, 'payReservationDeposit']);
        Route::post('customer/payments/{transactionId}/request-refund', [DemoPaymentController::class, 'requestRefund']);
        Route::post('customer/reservations/{bookingId}/dispute', [DemoPaymentController::class, 'openDispute']);
        Route::put('customer/rent/{property_id}',[CustomerController::class,'updateReservation'])->middleware('auth:sanctum');
        // يُستعمل POST للحفاظ على سجل الحجز بدلاً من حذفه.
        Route::post('customer/reservations/cancel',[CustomerController::class,'cancelReservation']);
        Route::post('customer/rateProperty',[CustomerController::class,'rateProperty'])->middleware('auth:sanctum');
        Route::get('customer/myReservation',[CustomerController::class,'getMyReservation']);
});
// يستطيع أي مستخدم مصادق عليه فقط إنشاء بلاغ.
Route::post('reports', [PropertyReportController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:10,1'])
    ->name('reports.store');

// المدير فقط يستطيع رؤية البلاغات.
Route::get('admin/reports', [PropertyReportController::class, 'index'])
    ->middleware('auth:sanctum', Admin::class)
    ->name('admin.reports.index');

// المدير فقط يستطيع تعديل حالة البلاغ وملاحظته.
Route::put('admin/reports/{report}/status', [PropertyReportController::class, 'updateStatus'])
    ->middleware('auth:sanctum', Admin::class)
    ->name('admin.reports.update-status');

// OTP + Password Reset

Route::post('send-register-otp',
    [AuthOtpController::class, 'sendRegisterOtp']);

Route::post('verify-register-otp',
    [AuthOtpController::class, 'verifyRegisterOtp']);

Route::post('forgot-password',
    [AuthOtpController::class, 'forgotPassword']);

Route::post('verify-reset-otp',
    [AuthOtpController::class, 'verifyResetOtp']);

Route::post('reset-password',
    [AuthOtpController::class, 'resetPassword']);
