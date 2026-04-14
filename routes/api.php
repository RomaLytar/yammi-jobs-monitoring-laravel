<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\Api\AlertRulesApiController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\Api\AlertSettingsApiController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\Api\FailureGroupsApiController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\Api\SettingsApiController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\ApiController;

Route::get('/jobs', [ApiController::class, 'jobs'])->name('jobs-monitor.api.jobs');
Route::get('/jobs/{uuid}/attempts', [ApiController::class, 'attempts'])
    ->where('uuid', '[0-9a-fA-F-]+')
    ->name('jobs-monitor.api.attempts');
Route::get('/failures', [ApiController::class, 'failures'])->name('jobs-monitor.api.failures');
Route::get('/failures/bulk/candidates', [ApiController::class, 'failuresCandidates'])
    ->name('jobs-monitor.api.failures.bulk.candidates');
Route::get('/dlq', [ApiController::class, 'dlq'])->name('jobs-monitor.api.dlq');
Route::get('/dlq/bulk/candidates', [ApiController::class, 'dlqBulkCandidates'])
    ->name('jobs-monitor.api.dlq.bulk.candidates');
Route::post('/dlq/bulk/retry', [ApiController::class, 'dlqBulkRetry'])
    ->name('jobs-monitor.api.dlq.bulk.retry');
Route::post('/dlq/bulk/delete', [ApiController::class, 'dlqBulkDelete'])
    ->name('jobs-monitor.api.dlq.bulk.delete');
Route::post('/dlq/{uuid}/retry', [ApiController::class, 'dlqRetry'])
    ->where('uuid', '[0-9a-fA-F-]+')
    ->name('jobs-monitor.api.dlq.retry');
Route::post('/dlq/{uuid}/delete', [ApiController::class, 'dlqDelete'])
    ->where('uuid', '[0-9a-fA-F-]+')
    ->name('jobs-monitor.api.dlq.delete');
Route::get('/failures/groups', [FailureGroupsApiController::class, 'index'])
    ->name('jobs-monitor.api.failures.groups.index');
Route::get('/failures/groups/bulk/candidates', [FailureGroupsApiController::class, 'bulkCandidates'])
    ->name('jobs-monitor.api.failures.groups.bulk.candidates');
Route::post('/failures/groups/bulk/retry', [FailureGroupsApiController::class, 'bulkRetry'])
    ->name('jobs-monitor.api.failures.groups.bulk.retry');
Route::post('/failures/groups/bulk/delete', [FailureGroupsApiController::class, 'bulkDelete'])
    ->name('jobs-monitor.api.failures.groups.bulk.delete');
Route::get('/failures/groups/{fingerprint}', [FailureGroupsApiController::class, 'show'])
    ->where('fingerprint', '[0-9a-f]{16}')
    ->name('jobs-monitor.api.failures.groups.show');
Route::post('/failures/groups/{fingerprint}/retry', [FailureGroupsApiController::class, 'retry'])
    ->where('fingerprint', '[0-9a-f]{16}')
    ->name('jobs-monitor.api.failures.groups.retry');
Route::post('/failures/groups/{fingerprint}/delete', [FailureGroupsApiController::class, 'destroy'])
    ->where('fingerprint', '[0-9a-f]{16}')
    ->name('jobs-monitor.api.failures.groups.delete');

Route::get('/stats', [ApiController::class, 'stats'])->name('jobs-monitor.api.stats');
Route::get('/stats/overview', [ApiController::class, 'statsOverview'])->name('jobs-monitor.api.stats.overview');
Route::get('/stats/time-series', [ApiController::class, 'timeSeries'])->name('jobs-monitor.api.stats.time-series');
Route::get('/stats/summary', [ApiController::class, 'summary'])->name('jobs-monitor.api.stats.summary');
Route::get('/settings', SettingsApiController::class)->name('jobs-monitor.api.settings');
Route::get('/settings/alerts', [AlertSettingsApiController::class, 'show'])
    ->name('jobs-monitor.api.settings.alerts.show');
Route::post('/settings/alerts/toggle', [AlertSettingsApiController::class, 'toggle'])
    ->name('jobs-monitor.api.settings.alerts.toggle');
Route::put('/settings/alerts', [AlertSettingsApiController::class, 'update'])
    ->name('jobs-monitor.api.settings.alerts.update');
Route::post('/settings/alerts/recipients', [AlertSettingsApiController::class, 'addRecipients'])
    ->name('jobs-monitor.api.settings.alerts.recipients.add');
Route::delete('/settings/alerts/recipients/{email}', [AlertSettingsApiController::class, 'removeRecipient'])
    ->where('email', '.+')
    ->name('jobs-monitor.api.settings.alerts.recipients.delete');

Route::get('/settings/alerts/rules', [AlertRulesApiController::class, 'index'])
    ->name('jobs-monitor.api.settings.alerts.rules.index');
Route::post('/settings/alerts/rules', [AlertRulesApiController::class, 'store'])
    ->name('jobs-monitor.api.settings.alerts.rules.store');
Route::get('/settings/alerts/rules/{id}', [AlertRulesApiController::class, 'show'])
    ->where('id', '[0-9]+')
    ->name('jobs-monitor.api.settings.alerts.rules.show');
Route::put('/settings/alerts/rules/{id}', [AlertRulesApiController::class, 'update'])
    ->where('id', '[0-9]+')
    ->name('jobs-monitor.api.settings.alerts.rules.update');
Route::delete('/settings/alerts/rules/{id}', [AlertRulesApiController::class, 'destroy'])
    ->where('id', '[0-9]+')
    ->name('jobs-monitor.api.settings.alerts.rules.destroy');
Route::post('/settings/alerts/rules/built-in/{key}/toggle', [AlertRulesApiController::class, 'toggleBuiltIn'])
    ->where('key', '[a-z0-9_]+')
    ->name('jobs-monitor.api.settings.alerts.rules.built-in.toggle');
