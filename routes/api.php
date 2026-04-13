<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\Api\AlertRulesApiController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\Api\AlertSettingsApiController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\Api\SettingsApiController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\ApiController;

Route::get('/jobs', [ApiController::class, 'jobs'])->name('jobs-monitor.api.jobs');
Route::get('/jobs/{uuid}/attempts', [ApiController::class, 'attempts'])
    ->where('uuid', '[0-9a-fA-F-]+')
    ->name('jobs-monitor.api.attempts');
Route::get('/failures', [ApiController::class, 'failures'])->name('jobs-monitor.api.failures');
Route::get('/dlq', [ApiController::class, 'dlq'])->name('jobs-monitor.api.dlq');
Route::post('/dlq/{uuid}/retry', [ApiController::class, 'dlqRetry'])
    ->where('uuid', '[0-9a-fA-F-]+')
    ->name('jobs-monitor.api.dlq.retry');
Route::post('/dlq/{uuid}/delete', [ApiController::class, 'dlqDelete'])
    ->where('uuid', '[0-9a-fA-F-]+')
    ->name('jobs-monitor.api.dlq.delete');
Route::get('/stats', [ApiController::class, 'stats'])->name('jobs-monitor.api.stats');
Route::get('/stats/overview', [ApiController::class, 'statsOverview'])->name('jobs-monitor.api.stats.overview');
Route::get('/stats/time-series', [ApiController::class, 'timeSeries'])->name('jobs-monitor.api.stats.time-series');
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
