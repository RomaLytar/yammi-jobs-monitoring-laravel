<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Playground;

/**
 * Single source of truth for which facade methods can be invoked from
 * the Settings → Playground page. Methods NOT listed here cannot be
 * called by the playground executor, regardless of HTTP input.
 *
 * Security guarantee: the executor dispatches calls through a static
 * match() keyed on the catalog key — dynamic method resolution on
 * request-supplied strings is never performed.
 */
final class MethodCatalog
{
    /**
     * @var list<PlaygroundMethod>
     */
    private readonly array $methods;

    public function __construct()
    {
        $this->methods = [
            ...$this->buildQueryMethods(),
            ...$this->buildManageMethods(),
            ...$this->buildSettingsMethods(),
        ];
    }

    /**
     * @return list<PlaygroundMethod>
     */
    public function all(): array
    {
        return $this->methods;
    }

    public function find(string $key): ?PlaygroundMethod
    {
        foreach ($this->methods as $m) {
            if ($m->key === $key) {
                return $m;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<PlaygroundMethod>>
     */
    public function grouped(): array
    {
        $grouped = [];
        foreach ($this->methods as $m) {
            $grouped[$m->facade][] = $m;
        }

        return $grouped;
    }

    /**
     * Human-readable caption for each facade, shown in the sidebar above
     * the method list so operators understand at a glance what the group
     * is for before picking a method.
     *
     * @return array<string, array{tone: string, summary: string}>
     */
    public function facadeInfo(): array
    {
        return [
            'YammiJobs' => [
                'tone' => 'info',
                'summary' => 'Read-only queries. Nothing here writes to the database — safe to call freely.',
            ],
            'YammiJobsManage' => [
                'tone' => 'danger',
                'summary' => 'Mutations: re-dispatch, delete, refresh. These change stored data and queue state — operators get a confirmation before each run.',
            ],
            'YammiJobsSettings' => [
                'tone' => 'warning',
                'summary' => 'Settings CRUD: general tuning, alert channels and recipients, managed + built-in rules. Reads are safe; writers mutate the settings table.',
            ],
        ];
    }

    /**
     * @return list<PlaygroundMethod>
     */
    private function buildQueryMethods(): array
    {
        $periodArg = new PlaygroundArgument(
            name: 'period',
            type: ArgumentType::Period,
            required: false,
            default: 'all',
            help: 'Time window. Use "all" for every record, or a compact expression like "30m", "1h", "7d", "30d".',
        );
        $pageArg = new PlaygroundArgument('page', ArgumentType::Integer, false, 1, 'Page number, 1-based.');
        $perPageArg = new PlaygroundArgument('perPage', ArgumentType::Integer, false, 50, 'Items per page. Max 500.');

        return [
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'jobs',
                description: 'Paginated list of all job records, most recent first.',
                arguments: [
                    $periodArg,
                    new PlaygroundArgument('jobClass', ArgumentType::StringText, false, null, 'Optional substring of the fully-qualified job class (e.g. "SendInvoice" or "App\\Jobs\\Emails"). Leave blank for all classes.'),
                    new PlaygroundArgument('status', ArgumentType::JobStatus, false, null, 'Optional status filter: processing/processed/failed.'),
                    $pageArg,
                    $perPageArg,
                ],
                destructive: false,
                returns: 'PagedResult<JobRecord>',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'failed',
                description: 'Paginated list of failed jobs.',
                arguments: [$periodArg, $pageArg, $perPageArg],
                destructive: false,
                returns: 'PagedResult<JobRecord>',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'attempts',
                description: 'All stored attempts for a given job UUID, ordered by attempt number.',
                arguments: [
                    new PlaygroundArgument('uuid', ArgumentType::Uuid, true, null, 'Job UUID.'),
                ],
                destructive: false,
                returns: 'list<JobRecord>',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'job',
                description: 'Single attempt of a job, identified by UUID and attempt number.',
                arguments: [
                    new PlaygroundArgument('uuid', ArgumentType::Uuid, true, null, 'Job UUID.'),
                    new PlaygroundArgument('attempt', ArgumentType::Integer, true, null, 'Attempt number (>=1).'),
                ],
                destructive: false,
                returns: '?JobRecord',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'dlq',
                description: 'Dead-letter queue: jobs that exceeded max tries or were classified as permanently failed.',
                arguments: [$pageArg, $perPageArg, new PlaygroundArgument('maxTries', ArgumentType::Integer, false, 3, 'Retry budget, matches worker setting.')],
                destructive: false,
                returns: 'PagedResult<JobRecord>',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'dlqPayload',
                description: 'Redacted payload of the last attempt stored for a DLQ entry. Returns null if no record.',
                arguments: [new PlaygroundArgument('uuid', ArgumentType::Uuid, true, null, 'Job UUID.')],
                destructive: false,
                returns: '?array',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'failureGroups',
                description: 'Paginated list of failure groups (jobs grouped by failure fingerprint).',
                arguments: [$pageArg, $perPageArg],
                destructive: false,
                returns: 'PagedResult<FailureGroup>',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'failureGroup',
                description: 'Single failure group by 16-hex-char fingerprint.',
                arguments: [new PlaygroundArgument('fingerprint', ArgumentType::Fingerprint, true, null, '16-char lowercase hex fingerprint.')],
                destructive: false,
                returns: '?FailureGroup',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'scheduled',
                description: 'Paginated list of scheduled task runs.',
                arguments: [$pageArg, $perPageArg],
                destructive: false,
                returns: 'PagedResult<ScheduledTaskRun>',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'scheduledStatusCounts',
                description: 'Count of scheduled runs grouped by status.',
                arguments: [],
                destructive: false,
                returns: 'array<string,int>',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'workers',
                description: 'Paginated list of workers (latest heartbeat per worker).',
                arguments: [$pageArg, $perPageArg],
                destructive: false,
                returns: 'PagedResult<Worker>',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'countFailures',
                description: 'Number of failures in the given window. Optional minAttempt filter suppresses first-try noise.',
                arguments: [
                    $periodArg,
                    new PlaygroundArgument('minAttempt', ArgumentType::Integer, false, null, 'Minimum attempt number to count (e.g. 2 excludes first-try failures).'),
                ],
                destructive: false,
                returns: 'int',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'countPartialCompletions',
                description: 'Number of jobs that reported progress but did not complete successfully.',
                arguments: [$periodArg],
                destructive: false,
                returns: 'int',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'countSilentSuccesses',
                description: 'Number of jobs reported as processed that did zero actual work (suspicious outcome).',
                arguments: [$periodArg],
                destructive: false,
                returns: 'int',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'stats',
                description: 'Per-class totals: processed, failed, average duration.',
                arguments: [new PlaygroundArgument('jobClass', ArgumentType::StringText, true, null, 'Fully-qualified class name, e.g. "App\\Jobs\\SendInvoice".')],
                destructive: false,
                returns: 'JobClassStatsData',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'statsAll',
                description: 'Per-class aggregate stats across all job classes.',
                arguments: [$periodArg],
                destructive: false,
                returns: 'array',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'statusCounts',
                description: 'Aggregate counts across all job classes: total / processed / failed / processing.',
                arguments: [$periodArg],
                destructive: false,
                returns: 'array',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'queueSize',
                description: 'Pending jobs on the given queue (driver-specific, null for drivers without support).',
                arguments: [new PlaygroundArgument('queue', ArgumentType::StringText, true, null, 'Queue name.')],
                destructive: false,
                returns: '?int',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'delayedSize',
                description: 'Delayed jobs on the given queue.',
                arguments: [new PlaygroundArgument('queue', ArgumentType::StringText, true, null, 'Queue name.')],
                destructive: false,
                returns: '?int',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobs',
                method: 'reservedSize',
                description: 'Jobs reserved by workers on the given queue.',
                arguments: [new PlaygroundArgument('queue', ArgumentType::StringText, true, null, 'Queue name.')],
                destructive: false,
                returns: '?int',
            ),
        ];
    }

    /**
     * @return list<PlaygroundMethod>
     */
    private function buildManageMethods(): array
    {
        return [
            new PlaygroundMethod(
                facade: 'YammiJobsManage',
                method: 'retryDlq',
                description: 'Re-dispatch a dead-letter job. Payload override (optional) replaces the stored payload before re-dispatch.',
                arguments: [
                    new PlaygroundArgument('uuid', ArgumentType::Uuid, true, null, 'Job UUID.'),
                    new PlaygroundArgument('payloadOverride', ArgumentType::JsonObject, false, null, 'Optional JSON object to replace the stored payload.'),
                ],
                destructive: true,
                returns: 'string (new UUID)',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsManage',
                method: 'deleteDlq',
                description: 'Delete every stored attempt of a DLQ entry.',
                arguments: [new PlaygroundArgument('uuid', ArgumentType::Uuid, true, null, 'Job UUID.')],
                destructive: true,
                returns: 'int (rows deleted)',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsManage',
                method: 'retryDlqBulk',
                description: 'Bulk re-dispatch of DLQ entries. Per-item errors are captured, they do not abort the batch.',
                arguments: [new PlaygroundArgument('uuids', ArgumentType::UuidList, true, null, 'Comma/newline-separated list of UUIDs.')],
                destructive: true,
                returns: 'BulkOperationResult',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsManage',
                method: 'deleteDlqBulk',
                description: 'Bulk delete of DLQ entries.',
                arguments: [new PlaygroundArgument('uuids', ArgumentType::UuidList, true, null, 'Comma/newline-separated list of UUIDs.')],
                destructive: true,
                returns: 'BulkOperationResult',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsManage',
                method: 'retryFailureGroup',
                description: 'Re-dispatch every job in a failure group. Null if the fingerprint is unknown.',
                arguments: [new PlaygroundArgument('fingerprint', ArgumentType::Fingerprint, true, null, '16-char hex fingerprint.')],
                destructive: true,
                returns: '?BulkOperationResult',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsManage',
                method: 'deleteFailureGroup',
                description: 'Delete every job in a failure group.',
                arguments: [new PlaygroundArgument('fingerprint', ArgumentType::Fingerprint, true, null, '16-char hex fingerprint.')],
                destructive: true,
                returns: '?BulkOperationResult',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsManage',
                method: 'refreshAnomalyBaselines',
                description: 'Rebuild p50/p95 duration baselines for every job class with enough samples.',
                arguments: [new PlaygroundArgument('lookbackDays', ArgumentType::Integer, false, 7, 'How many days of samples to compute from.')],
                destructive: true,
                returns: 'int (baselines updated)',
            ),
        ];
    }

    /**
     * @return list<PlaygroundMethod>
     */
    private function buildSettingsMethods(): array
    {
        return [
            new PlaygroundMethod(
                facade: 'YammiJobsSettings',
                method: 'general',
                description: 'Full listing of general settings grouped by section with current source (default / config / db).',
                arguments: [],
                destructive: false,
                returns: 'list<SettingGroupData>',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsSettings',
                method: 'alerts',
                description: 'Alert channel state, recipients and source for each value.',
                arguments: [],
                destructive: false,
                returns: 'AlertSettingsData',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsSettings',
                method: 'rules',
                description: 'Built-in and managed alert rules with effective enabled state.',
                arguments: [],
                destructive: false,
                returns: 'AlertRulesOverviewData',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsSettings',
                method: 'toggleAlerts',
                description: 'Enable or disable all alerts (null clears the DB override and falls back to config).',
                arguments: [new PlaygroundArgument('enabled', ArgumentType::NullableBoolean, true, null, 'true/false/null.')],
                destructive: true,
                returns: 'void',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsSettings',
                method: 'addAlertRecipients',
                description: 'Add one or more email addresses to the alert recipient list.',
                arguments: [new PlaygroundArgument('emails', ArgumentType::EmailList, true, null, 'Comma-separated or newline-separated emails.')],
                destructive: true,
                returns: 'void',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsSettings',
                method: 'removeAlertRecipient',
                description: 'Remove a single email from the alert recipient list.',
                arguments: [new PlaygroundArgument('email', ArgumentType::Email, true, null, 'Recipient email.')],
                destructive: true,
                returns: 'void',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsSettings',
                method: 'resetGeneral',
                description: 'Clear all DB overrides for general settings — values fall back to config or code defaults.',
                arguments: [],
                destructive: true,
                returns: 'void',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsSettings',
                method: 'toggleBuiltInRule',
                description: 'Enable / disable a built-in alert rule. Pass null to clear the DB override.',
                arguments: [
                    new PlaygroundArgument('key', ArgumentType::StringText, true, null, 'Built-in rule key (e.g. "critical_failure").'),
                    new PlaygroundArgument('enabled', ArgumentType::NullableBoolean, true, null, 'true/false/null.'),
                ],
                destructive: true,
                returns: 'void',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsSettings',
                method: 'resetBuiltInRule',
                description: 'Clear DB overrides for a built-in rule — values revert to code defaults.',
                arguments: [new PlaygroundArgument('key', ArgumentType::StringText, true, null, 'Built-in rule key.')],
                destructive: true,
                returns: 'void',
            ),
            new PlaygroundMethod(
                facade: 'YammiJobsSettings',
                method: 'deleteRule',
                description: 'Delete a managed alert rule by id.',
                arguments: [new PlaygroundArgument('id', ArgumentType::Integer, true, null, 'Rule id.')],
                destructive: true,
                returns: 'bool',
            ),
        ];
    }
}
