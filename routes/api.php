<?php

use App\Http\Controllers\IssueWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// GitHub webhook endpoint
Route::post('/webhook/github', [IssueWebhookController::class, 'handle'])
    ->name('webhook.github');

// Health check endpoint
Route::get('/health', [IssueWebhookController::class, 'health'])
    ->name('health');
