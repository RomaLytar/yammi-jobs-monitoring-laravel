<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\AlertSettingsController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\ApiController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\DashboardController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\DatabaseSettingsController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\DatabaseSettingsController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\DlqController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\DurationAnomaliesController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\FailureGroupsController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\FailureGroupsPageController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\GeneralSettingsController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\JobDetailController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\ScheduledTasksController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\SettingsController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\StatsController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\WorkersController;

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
Route::get('/failures', FailureGroupsPageController::class)
    ->name('jobs-monitor.failures.groups.page');
Route::get('/failures/groups', [FailureGroupsController::class, 'index'])
    ->name('jobs-monitor.failures.groups.index');
Route::get('/failures/groups/bulk/candidates', [FailureGroupsController::class, 'bulkCandidates'])
    ->name('jobs-monitor.failures.groups.bulk.candidates');
Route::post('/failures/groups/bulk/retry', [FailureGroupsController::class, 'bulkRetryMany'])
    ->name('jobs-monitor.failures.groups.bulk.retry');
Route::post('/failures/groups/bulk/delete', [FailureGroupsController::class, 'bulkDeleteMany'])
    ->name('jobs-monitor.failures.groups.bulk.delete');
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
Route::get('/scheduled', ScheduledTasksController::class)->name('jobs-monitor.scheduled');
Route::post('/scheduled/{id}/retry', [ScheduledTasksController::class, 'retry'])
    ->where('id', '[0-9]+')
    ->name('jobs-monitor.scheduled.retry');
Route::get('/anomalies', DurationAnomaliesController::class)->name('jobs-monitor.anomalies');
Route::post('/anomalies/refresh-baselines', [DurationAnomaliesController::class, 'refreshBaselines'])
    ->name('jobs-monitor.anomalies.refresh-baselines');
Route::get('/workers', WorkersController::class)->name('jobs-monitor.workers');
Route::get('/workers/summary', [WorkersController::class, 'summary'])->name('jobs-monitor.workers.summary');
Route::get('/settings', SettingsController::class)->name('jobs-monitor.settings');
Route::get('/settings/database', [DatabaseSettingsController::class, 'index'])
    ->name('jobs-monitor.settings.database');
Route::post('/settings/database/setup', [DatabaseSettingsController::class, 'setup'])
    ->name('jobs-monitor.settings.database.setup');
Route::post('/settings/database/transfer', [DatabaseSettingsController::class, 'transfer'])
    ->name('jobs-monitor.settings.database.transfer');
Route::post('/settings/database/run-migrations', [DatabaseSettingsController::class, 'runMigrations'])
    ->name('jobs-monitor.settings.database.run-migrations');
Route::get('/settings/database/transfer-status', [DatabaseSettingsController::class, 'transferStatus'])
    ->name('jobs-monitor.settings.database.transfer-status');
Route::get('/settings/general', [GeneralSettingsController::class, 'index'])
    ->name('jobs-monitor.settings.general');
Route::post('/settings/general', [GeneralSettingsController::class, 'update'])
    ->name('jobs-monitor.settings.general.update');
Route::post('/settings/general/reset', [GeneralSettingsController::class, 'reset'])
    ->name('jobs-monitor.settings.general.reset');
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
