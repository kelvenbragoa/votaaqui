<?php

use App\Http\Controllers\Api\Web\AuthWebController;
use App\Http\Controllers\Api\Web\UserController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Middleware\Sanctum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('login',[AuthWebController::class,'login']);

Route::middleware([Sanctum::class])->group(function () {
    
    Route::post('logout',[AuthWebController::class,'logout']);

    Route::resource('users', UserController::class);

});

// VotaAqui Routes - Public routes (no authentication required)
Route::prefix('votaaqui')->group(function () {
    // Participants routes
    Route::get('participants', [ParticipantController::class, 'index']);
    Route::get('participants/{participant}', [ParticipantController::class, 'show']);
    
    // Voting routes
    Route::post('votes', [VoteController::class, 'store']);
    Route::get('votes/statistics', [VoteController::class, 'statistics']);
    Route::get('participants/{participant}/votes', [VoteController::class, 'participantVotes']);
});

// Legacy routes for compatibility
Route::get('participants', [ParticipantController::class, 'index']);
Route::post('votes', [VoteController::class, 'store']);