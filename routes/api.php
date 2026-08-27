<?php

use App\Http\Controllers\Api\TicketApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// The tenant is bound by ResolveApiTenant, prepended to the `api` middleware
// group, so it is already in place for plan: (which reads it to find the
// subscription) and for route model binding.
Route::middleware(['auth:sanctum', 'plan:api_access', 'throttle:60,1'])
    ->prefix('v1')
    ->group(function () {
        Route::middleware('abilities:tickets:read')->group(function () {
            Route::get('/tickets', [TicketApiController::class, 'index']);
            Route::get('/tickets/{ticket}', [TicketApiController::class, 'show']);
        });

        Route::middleware('abilities:tickets:write')->group(function () {
            Route::post('/tickets', [TicketApiController::class, 'store']);
            Route::put('/tickets/{ticket}', [TicketApiController::class, 'update']);
            Route::post('/tickets/{ticket}/comments', [TicketApiController::class, 'addComment']);
        });
    });
