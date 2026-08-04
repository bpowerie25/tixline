<?php

use App\Http\Controllers\Api\TicketApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('v1')->group(function () {
    Route::get('/tickets', [TicketApiController::class, 'index']);
    Route::post('/tickets', [TicketApiController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketApiController::class, 'show']);
    Route::put('/tickets/{ticket}', [TicketApiController::class, 'update']);
    Route::post('/tickets/{ticket}/comments', [TicketApiController::class, 'addComment']);
});
