<?php

declare(strict_types=1);

use App\Http\Controllers\Api\IssueApiTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware('auth:sanctum');

Route::post('/tokens', IssueApiTokenController::class)->name('api.tokens.issue');
