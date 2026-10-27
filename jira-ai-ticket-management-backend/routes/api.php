<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeReportController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WorklogController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth.token')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/meta', [MetaController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::put('/tickets/{ticket}', [TicketController::class, 'update']);
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);

    Route::post('/tickets/{ticket}/worklogs', [WorklogController::class, 'store']);
    Route::get('/tickets/{ticket}/comments', [CommentController::class, 'index']);
    Route::post('/tickets/{ticket}/comments', [CommentController::class, 'store']);

    Route::get('/sprints', [SprintController::class, 'index']);
    Route::get('/sprints/{sprint}/summary', [SprintController::class, 'summary'])
        ->middleware('role:admin,manager');

    Route::middleware('role:admin,manager')->group(function () {
        Route::post('/sprints', [SprintController::class, 'store']);
        Route::put('/sprints/{sprint}', [SprintController::class, 'update']);
        Route::post('/sprints/{sprint}/start', [SprintController::class, 'start']);
        Route::post('/sprints/{sprint}/complete', [SprintController::class, 'complete']);
    });

    Route::get('/reports/employees/{user}', [EmployeeReportController::class, 'show']);

    Route::post('/assistant/query', [AssistantController::class, 'query']);
});
