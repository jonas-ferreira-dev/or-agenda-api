<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\ProfessionalProfileController;
use App\Http\Controllers\Api\PublicBookingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PlatformUserController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProfessionalAvailabilityController;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'OR Agenda API running',
    ]);
});



Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| Rotas públicas
|--------------------------------------------------------------------------
| Essas rotas NÃO podem ficar dentro do auth:sanctum.
*/
Route::prefix('public')->group(function () {
    Route::get('/professionals/{slug}', [PublicBookingController::class, 'showProfessional'])
        ->middleware('throttle:60,1');

    Route::get('/professionals/{slug}/services', [PublicBookingController::class, 'services'])
        ->middleware('throttle:60,1');

    Route::get('/professionals/{slug}/availability', [PublicBookingController::class, 'availability'])
        ->middleware('throttle:60,1');

    Route::post('/professionals/{slug}/appointments', [PublicBookingController::class, 'store'])
        ->middleware('throttle:10,1');
});

/*
|--------------------------------------------------------------------------
| Rotas privadas
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('platform.admin')->group(function () {
         Route::get('/platform/users', [PlatformUserController::class, 'index']);
         Route::post('/platform/users', [PlatformUserController::class, 'store']);
         Route::put('/platform/users/{user}', [PlatformUserController::class, 'update']);
    });


    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource(
        '/professional-availabilities',
        ProfessionalAvailabilityController::class
    )->only(['index', 'store', 'update', 'destroy']);

    Route::get('/me', [MeController::class, 'show']);
    Route::put('/me', [MeController::class, 'update']);
    Route::put('/me/password', [MeController::class, 'updatePassword']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::get('/professional-profile', [ProfessionalProfileController::class, 'show']);
    Route::post('/professional-profile', [ProfessionalProfileController::class, 'store']);
    Route::put('/professional-profile', [ProfessionalProfileController::class, 'update']);

    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);
    Route::get('/services/{id}', [ServiceController::class, 'show']);
    Route::put('/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);

    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);
    Route::delete('/clients/{id}', [ClientController::class, 'destroy']);

    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::patch('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
    Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
    Route::put('/appointments/{id}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);

    Route::prefix('reports')->group(function () {
        Route::get('/revenue', [ReportController::class, 'revenue']);
        Route::get('/appointments', [ReportController::class, 'appointments']);
        Route::get('/cancellations', [ReportController::class, 'cancellations']);
        Route::get('/clients', [ReportController::class, 'clients']);
    });

});