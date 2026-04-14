<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\AlertSettingsController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\ApiController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\DashboardController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\DlqController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\FailureGroupsController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\JobDetailController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\SettingsController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\StatsController;

Route::get('/', DashboardController::class)->name('jobs-monitor.dashboard');
Route::get('/stats', StatsController::class)->name('jobs-monitor.stats');
Route::get('/time-series', [ApiController::class, 'timeSeries'])->name('jobs-monitor.time-series');
Route::get('/summary', [ApiController::class, 'summary'])->name('jobs-monitor.summary');
Route::get('/dlq', DlqController::class)->name('jobs-monitor.dlq');
Route::post('/dlq/bulk/retry', [DlqController::class, 'bulkRetry'])
    ->name('jobs-monitor.dlq.bulk.retry');
Route::post('/dlq/bulk/delete', [DlqController::class, 'bulkDelete'])
    ->name('jobs-monitor.dlq.bulk.delete');
Route::get('/dlq/bulk/candidates', [DlqController::class, 'bulkCandidates'])
    ->name('jobs-monitor.dlq.bulk.candidates');
Route::get('/failures/bulk/candidates', [ApiController::class, 'failuresCandidates'])
    ->name('jobs-monitor.failures.bulk.candidates');
Route::get('/failures/groups', [FailureGroupsController::class, 'index'])
    ->name('jobs-monitor.failures.groups.index');
Route::get('/failures/groups/{fingerprint}', [FailureGroupsController::class, 'show'])
    ->where('fingerprint', '[0-9a-f]{16}')
    ->name('jobs-monitor.failures.groups.show');
Route::post('/failures/groups/{fingerprint}/retry', [FailureGroupsController::class, 'bulkRetry'])
    ->where('fingerprint', '[0-9a-f]{16}')
    ->name('jobs-monitor.failures.groups.retry');
Route::post('/failures/groups/{fingerprint}/delete', [FailureGroupsController::class, 'bulkDelete'])
    ->where('fingerprint', '[0-9a-f]{16}')
    ->name('jobs-monitor.failures.groups.delete');
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
Route::get('/settings/alerts', [AlertSettingsController::class, 'index'])
    ->name('jobs-monitor.settings.alerts');
Route::post('/settings/alerts/toggle', [AlertSettingsController::class, 'toggle'])
    ->name('jobs-monitor.settings.alerts.toggle');
Route::post('/settings/alerts', [AlertSettingsController::class, 'update'])
    ->name('jobs-monitor.settings.alerts.update');
Route::post('/settings/alerts/recipients', [AlertSettingsController::class, 'addRecipients'])
    ->name('jobs-monitor.settings.alerts.recipients.add');
Route::delete('/settings/alerts/recipients/{email}', [AlertSettingsController::class, 'removeRecipient'])
    ->where('email', '.+')
    ->name('jobs-monitor.settings.alerts.recipients.delete');
Route::post('/settings/alerts/built-in/{key}/toggle', [AlertSettingsController::class, 'toggleBuiltIn'])
    ->where('key', '[a-z0-9_]+')
    ->name('jobs-monitor.settings.alerts.built-in.toggle');
Route::post('/settings/alerts/built-in/{key}', [AlertSettingsController::class, 'updateBuiltIn'])
    ->where('key', '[a-z0-9_]+')
    ->name('jobs-monitor.settings.alerts.built-in.update');
Route::post('/settings/alerts/built-in/{key}/reset', [AlertSettingsController::class, 'resetBuiltIn'])
    ->where('key', '[a-z0-9_]+')
    ->name('jobs-monitor.settings.alerts.built-in.reset');
Route::get('/{uuid}/{attempt}', JobDetailController::class)
    ->where('attempt', '[0-9]+')
    ->name('jobs-monitor.detail');
