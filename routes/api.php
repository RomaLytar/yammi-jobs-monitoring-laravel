<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\ApiController;

Route::get('/jobs', [ApiController::class, 'jobs'])->name('jobs-monitor.api.jobs');
Route::get('/jobs/{uuid}/attempts', [ApiController::class, 'attempts'])
    ->where('uuid', '[0-9a-fA-F-]+')
    ->name('jobs-monitor.api.attempts');
Route::get('/failures', [ApiController::class, 'failures'])->name('jobs-monitor.api.failures');
Route::get('/dlq', [ApiController::class, 'dlq'])->name('jobs-monitor.api.dlq');
Route::get('/stats', [ApiController::class, 'stats'])->name('jobs-monitor.api.stats');
Route::get('/stats/overview', [ApiController::class, 'statsOverview'])->name('jobs-monitor.api.stats.overview');
