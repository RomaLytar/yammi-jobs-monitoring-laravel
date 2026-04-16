<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

use Yammi\JobsMonitor\Application\DTO\SettingDefinitionData;
use Yammi\JobsMonitor\Application\DTO\SettingType;

/**
 * Catalog of every setting that the UI can manage.
 *
 * Adding a new setting = one new entry here. No migration needed,
 * no controller changes, no view changes — the page renders it
 * automatically from this registry.
 *
 * @internal
 */
final class SettingRegistry
{
    /** @var array<string, array{label: string, description: string, icon: string, settings: list<SettingDefinitionData>}> */
    private array $groups;

    public function __construct()
    {
        $this->groups = $this->buildGroups();
    }

    /**
     * @return array<string, array{label: string, description: string, icon: string, settings: list<SettingDefinitionData>}>
     */
    public function groups(): array
    {
        return $this->groups;
    }

    public function find(string $group, string $key): ?SettingDefinitionData
    {
        foreach ($this->groups[$group]['settings'] ?? [] as $def) {
            if ($def->key === $key) {
                return $def;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{label: string, description: string, icon: string, settings: list<SettingDefinitionData>}>
     */
    private function buildGroups(): array
    {
        return [
            'general' => [
                'label' => 'General',
                'description' => 'Core package behavior — payload storage, data retention and DLQ threshold.',
                'icon' => 'settings',
                'settings' => [
                    new SettingDefinitionData(
                        group: 'general',
                        key: 'store_payload',
                        configPath: 'jobs-monitor.store_payload',
                        type: SettingType::Boolean,
                        default: true,
                        label: 'Store job payloads',
                        description: 'Save the raw job payload alongside each record. Sensitive keys (password, token, secret, api_key, credit_card, ssn) are automatically redacted before storage. When enabled you can inspect the exact data a failed job received — invaluable for debugging. When disabled payloads are not captured, reducing storage usage and eliminating any residual PII risk.',
                    ),
                    new SettingDefinitionData(
                        group: 'general',
                        key: 'retention_days',
                        configPath: 'jobs-monitor.retention_days',
                        type: SettingType::Integer,
                        default: 30,
                        label: 'Data retention (days)',
                        description: 'Number of days to keep job records. The jobs-monitor:prune artisan command deletes everything older than this value. Lower values free disk space faster but reduce your historical visibility. Recommended: 7–14 for high-volume queues, 30–90 for moderate traffic.',
                        min: 1,
                        max: 365,
                        suffix: 'days',
                    ),
                    new SettingDefinitionData(
                        group: 'general',
                        key: 'max_tries',
                        configPath: 'jobs-monitor.max_tries',
                        type: SettingType::Integer,
                        default: 3,
                        label: 'Max tries before DLQ',
                        description: 'A job UUID is treated as "dead letter" when its latest attempt is failed and the attempt number has reached this value (or the failure category is permanent/critical). Raising this lets flaky jobs retry more before being flagged; lowering it surfaces problems sooner.',
                        min: 1,
                        max: 100,
                        suffix: 'attempts',
                    ),
                ],
            ],

            'bulk' => [
                'label' => 'Bulk Operations',
                'description' => 'Limits for bulk retry and delete actions on DLQ and failure groups.',
                'icon' => 'layers',
                'settings' => [
                    new SettingDefinitionData(
                        group: 'bulk',
                        key: 'max_ids_per_request',
                        configPath: 'jobs-monitor.bulk.max_ids_per_request',
                        type: SettingType::Integer,
                        default: 100,
                        label: 'Max IDs per request',
                        description: 'Hard cap on how many job IDs a single bulk retry/delete HTTP request can contain. The JavaScript chunker on the DLQ and failure groups pages splits larger selections into batches of this size. Raising it reduces the number of requests but makes each one longer-running — keep it under 200 to avoid HTTP timeouts.',
                        min: 10,
                        max: 500,
                    ),
                    new SettingDefinitionData(
                        group: 'bulk',
                        key: 'candidate_limit',
                        configPath: 'jobs-monitor.bulk.candidate_limit',
                        type: SettingType::Integer,
                        default: 10000,
                        label: 'Select-all candidate limit',
                        description: 'Maximum number of job IDs the "Select all matching" button can fetch. When the matching set exceeds this limit the selection is truncated and the UI warns the operator to narrow the filter. Prevents runaway queries on very large DLQ tables.',
                        min: 100,
                        max: 100000,
                    ),
                ],
            ],

            'scheduler' => [
                'label' => 'Scheduler Monitoring',
                'description' => 'Observe Laravel scheduled tasks and detect stuck or late runs.',
                'icon' => 'calendar-clock',
                'settings' => [
                    new SettingDefinitionData(
                        group: 'scheduler',
                        key: 'enabled',
                        configPath: 'jobs-monitor.scheduler.enabled',
                        type: SettingType::Boolean,
                        default: true,
                        label: 'Enable scheduler monitoring',
                        description: 'Listen to Laravel scheduler events and record every scheduled task run. When disabled the Scheduled Tasks page shows no new data and the watchdog stops scanning. Existing records are retained.',
                    ),
                    new SettingDefinitionData(
                        group: 'scheduler',
                        key: 'watchdog_enabled',
                        configPath: 'jobs-monitor.scheduler.watchdog.enabled',
                        type: SettingType::Boolean,
                        default: true,
                        label: 'Enable watchdog',
                        description: 'Periodically scan for scheduled task runs that have been stuck in "Running" for too long and flag them as "Late". A typical cause is a worker crash that prevented the Finished event from firing. Disable if you handle stale-run detection externally.',
                    ),
                    new SettingDefinitionData(
                        group: 'scheduler',
                        key: 'watchdog_tolerance_minutes',
                        configPath: 'jobs-monitor.scheduler.watchdog.tolerance_minutes',
                        type: SettingType::Integer,
                        default: 30,
                        label: 'Watchdog tolerance',
                        description: 'Minutes a scheduled task run can stay in "Running" before the watchdog flags it as "Late". Shorter values catch stuck tasks faster but may produce false positives for legitimately long-running tasks. Set this higher than your longest expected scheduled command.',
                        min: 1,
                        max: 1440,
                        suffix: 'minutes',
                    ),
                ],
            ],

            'duration_anomaly' => [
                'label' => 'Duration Anomaly Detection',
                'description' => 'Keeps a rolling baseline (p50/p95) per job class and flags runs that are suspiciously fast or slow.',
                'icon' => 'activity',
                'settings' => [
                    new SettingDefinitionData(
                        group: 'duration_anomaly',
                        key: 'enabled',
                        configPath: 'jobs-monitor.duration_anomaly.enabled',
                        type: SettingType::Boolean,
                        default: true,
                        label: 'Enable anomaly detection',
                        description: 'Track job durations and flag outliers. Catches the "job reported success but ran 100x faster than normal — likely no-op\'d" case and the "job is taking 10x longer than usual — possible deadlock or upstream timeout" case. When disabled, existing baselines are retained but no new anomalies are detected.',
                    ),
                    new SettingDefinitionData(
                        group: 'duration_anomaly',
                        key: 'min_samples',
                        configPath: 'jobs-monitor.duration_anomaly.min_samples',
                        type: SettingType::Integer,
                        default: 30,
                        label: 'Minimum samples',
                        description: 'Number of successful runs required before a job class has enough data for a reliable baseline. Lower values start detecting sooner but increase false-positive risk on young job classes. Raise this for jobs with highly variable durations.',
                        min: 5,
                        max: 1000,
                        suffix: 'runs',
                    ),
                    new SettingDefinitionData(
                        group: 'duration_anomaly',
                        key: 'short_factor',
                        configPath: 'jobs-monitor.duration_anomaly.short_factor',
                        type: SettingType::Float,
                        default: 0.1,
                        label: 'Short anomaly factor',
                        description: 'A run is flagged as "suspiciously fast" when its duration is below p50 multiplied by this factor. Value 0.1 means "10% of the median or less". Lower values are more permissive (fewer alerts); higher values are stricter.',
                        min: 0.01,
                        max: 1.0,
                    ),
                    new SettingDefinitionData(
                        group: 'duration_anomaly',
                        key: 'long_factor',
                        configPath: 'jobs-monitor.duration_anomaly.long_factor',
                        type: SettingType::Float,
                        default: 3.0,
                        label: 'Long anomaly factor',
                        description: 'A run is flagged as "suspiciously slow" when its duration exceeds p95 multiplied by this factor. Value 3.0 means "3x the 95th percentile". Lower values catch slowdowns sooner but generate more noise; higher values tolerate more variance.',
                        min: 1.1,
                        max: 100.0,
                    ),
                ],
            ],

            'outcome' => [
                'label' => 'Outcome Reports',
                'description' => 'Collect and analyze job outcomes. Results are shown on the Anomalies page: "Silent Successes" and "Partial Completions" sections.',
                'icon' => 'clipboard-check',
                'settings' => [
                    new SettingDefinitionData(
                        group: 'outcome',
                        key: 'enabled',
                        configPath: 'jobs-monitor.outcome.enabled',
                        type: SettingType::Boolean,
                        default: true,
                        label: 'Enable outcome reports',
                        description: 'When a job implements ReportsOutcome and returns an OutcomeReport, the monitor stores it and surfaces results on the Anomalies page — "Silent Successes" (jobs that returned OK but reported no-op, degraded, or zero items processed) and "Partial Completions" (jobs with warnings). Alerts can also fire on suspicious outcomes. Disabling stops collection of new outcome data but keeps existing records on the Anomalies page.',
                    ),
                ],
            ],

            'workers' => [
                'label' => 'Worker Heartbeat',
                'description' => 'Track queue workers via heartbeats and detect silent or crashed workers.',
                'icon' => 'heart-pulse',
                'settings' => [
                    new SettingDefinitionData(
                        group: 'workers',
                        key: 'enabled',
                        configPath: 'jobs-monitor.workers.enabled',
                        type: SettingType::Boolean,
                        default: true,
                        label: 'Enable worker monitoring',
                        description: 'Observe queue workers via JobProcessing / Looping / WorkerStopping events and track when each one last checked in. Catches the "everything green, nothing running" blind spot — a crashed worker with no failures to report. When disabled the Workers page shows no new data.',
                    ),
                    new SettingDefinitionData(
                        group: 'workers',
                        key: 'heartbeat_interval_seconds',
                        configPath: 'jobs-monitor.workers.heartbeat_interval_seconds',
                        type: SettingType::Integer,
                        default: 30,
                        label: 'Heartbeat interval',
                        description: 'Cache-backed throttle: at most one heartbeat write per worker per this interval. Raising it reduces database write volume on busy workers but widens the detection gap — a crashed worker takes longer to notice. Keep it at 15–60s for most setups.',
                        min: 5,
                        max: 300,
                        suffix: 'seconds',
                    ),
                    new SettingDefinitionData(
                        group: 'workers',
                        key: 'silent_after_seconds',
                        configPath: 'jobs-monitor.workers.silent_after_seconds',
                        type: SettingType::Integer,
                        default: 120,
                        label: 'Silent after',
                        description: 'A worker whose last heartbeat is older than this is flagged as "Silent" and may trigger a WORKER_SILENT alert. Should be at least 2x the heartbeat interval to avoid false positives from normal jitter. Too low = noisy; too high = slow detection.',
                        min: 30,
                        max: 3600,
                        suffix: 'seconds',
                    ),
                    new SettingDefinitionData(
                        group: 'workers',
                        key: 'retention_days',
                        configPath: 'jobs-monitor.workers.retention_days',
                        type: SettingType::Integer,
                        default: 7,
                        label: 'Worker data retention',
                        description: 'Number of days to keep worker heartbeat records. Older records are pruned. Short retention is usually fine since worker data is operational — you mainly care about the last few days.',
                        min: 1,
                        max: 90,
                        suffix: 'days',
                    ),
                    new SettingDefinitionData(
                        group: 'workers',
                        key: 'schedule_cron',
                        configPath: 'jobs-monitor.workers.schedule.cron',
                        type: SettingType::String,
                        default: '* * * * *',
                        label: 'Watchdog check frequency',
                        description: 'How often the worker watchdog runs to check for silent or underprovisioned workers. More frequent = faster detection of crashed workers, but slightly more DB load.',
                        options: [
                            '* * * * *' => 'Every minute',
                            '*/2 * * * *' => 'Every 2 minutes',
                            '*/5 * * * *' => 'Every 5 minutes',
                            '*/10 * * * *' => 'Every 10 minutes',
                            '*/15 * * * *' => 'Every 15 minutes',
                            '*/30 * * * *' => 'Every 30 minutes',
                            '0 * * * *' => 'Every hour',
                        ],
                    ),
                ],
            ],

            'alerts_schedule' => [
                'label' => 'Alerts Schedule',
                'description' => 'Control how the alert evaluation job is scheduled — cron expression, queue name, and auto-registration.',
                'icon' => 'bell-ring',
                'settings' => [
                    new SettingDefinitionData(
                        group: 'alerts_schedule',
                        key: 'schedule_enabled',
                        configPath: 'jobs-monitor.alerts.schedule.enabled',
                        type: SettingType::Boolean,
                        default: true,
                        label: 'Auto-schedule evaluation',
                        description: 'When enabled the service provider automatically registers the alert evaluation job with Laravel\'s scheduler. Disable this only if you trigger the evaluation job manually (e.g. from your own scheduler or an external cron). When disabled no alert rules are evaluated automatically — you must dispatch the job yourself.',
                    ),
                    new SettingDefinitionData(
                        group: 'alerts_schedule',
                        key: 'schedule_cron',
                        configPath: 'jobs-monitor.alerts.schedule.cron',
                        type: SettingType::String,
                        default: '* * * * *',
                        label: 'Evaluation frequency',
                        description: 'How often alert rules are evaluated. More frequent = faster incident detection, but more DB queries per hour. Every minute is recommended for most setups.',
                        options: [
                            '* * * * *' => 'Every minute',
                            '*/2 * * * *' => 'Every 2 minutes',
                            '*/5 * * * *' => 'Every 5 minutes',
                            '*/10 * * * *' => 'Every 10 minutes',
                            '*/15 * * * *' => 'Every 15 minutes',
                            '*/30 * * * *' => 'Every 30 minutes',
                            '0 * * * *' => 'Every hour',
                        ],
                    ),
                    new SettingDefinitionData(
                        group: 'alerts_schedule',
                        key: 'schedule_queue',
                        configPath: 'jobs-monitor.alerts.schedule.queue',
                        type: SettingType::String,
                        default: '',
                        label: 'Evaluation queue',
                        description: 'Queue name for the dispatched alert evaluation job. Leave empty to use your application\'s default queue. Use a dedicated queue (e.g. "monitoring") to isolate monitoring jobs from your application workload.',
                        pattern: '[a-zA-Z0-9_:.-]*',
                    ),
                ],
            ],
        ];
    }
}
