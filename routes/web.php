<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\DashboardController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\DlqController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\JobDetailController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\SettingsController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\StatsController;

Route::get('/', DashboardController::class)->name('jobs-monitor.dashboard');
Route::get('/stats', StatsController::class)->name('jobs-monitor.stats');
Route::get('/dlq', DlqController::class)->name('jobs-monitor.dlq');
Route::get('/dlq/{uuid}/edit', [DlqController::class, 'edit'])
    ->where('uuid', '[0-9a-fA-F-]+')
    ->name('jobs-monitor.dlq.edit');
Route::post('/dlq/{uuid}/retry', [DlqController::class, 'retry'])
    ->where('uuid', '[0-9a-fA-F-]+')
    ->name('jobs-monitor.dlq.retry');
Route::post('/dlq/{uuid}/delete', [DlqController::class, 'delete'])
    ->where('uuid', '[0-9a-fA-F-]+')
    ->name('jobs-monitor.dlq.delete');
Route::get('/settings', SettingsController::class)->name('jobs-monitor.settings');
Route::get('/{uuid}/{attempt}', JobDetailController::class)
    ->where('attempt', '[0-9]+')
    ->name('jobs-monitor.detail');
