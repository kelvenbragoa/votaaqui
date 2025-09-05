<?php

use App\Http\Controllers\Api\Web\AuthWebController;
use App\Http\Controllers\Api\Web\UserController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\Api\EpisodeController;
use App\Http\Controllers\Api\EliminationController;
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
    
    // Episodes routes (public)
    Route::get('episodes', [EpisodeController::class, 'index']);
    Route::get('episodes/current', [EpisodeController::class, 'current']);
    Route::get('episodes/{episode}', [EpisodeController::class, 'show']);
    Route::get('episodes/{episode}/results', [EpisodeController::class, 'results']);
    Route::get('eliminations/history', [EpisodeController::class, 'eliminationHistory']);
    Route::get('statistics', [EpisodeController::class, 'statistics']);
});

// Admin Routes - Protected (authentication required)
Route::middleware([Sanctum::class])->prefix('admin')->group(function () {
    // Elimination management (admin only)
    Route::post('episodes/{episode}/eliminate', [EliminationController::class, 'eliminate']);
    Route::post('episodes/{episode}/revert', [EliminationController::class, 'revert']);
    Route::get('episodes/{episode}/simulate', [EliminationController::class, 'simulate']);
});

// Legacy routes for compatibility
Route::get('participants', [ParticipantController::class, 'index']);
Route::post('votes', [VoteController::class, 'store']);