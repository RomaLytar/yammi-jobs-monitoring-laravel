<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Playground;

use DateTimeInterface;
use UnitEnum;
use Yammi\JobsMonitor\Application\DTO\AlertRulesOverviewData;
use Yammi\JobsMonitor\Application\DTO\AlertSettingsData;
use Yammi\JobsMonitor\Application\DTO\BulkOperationResult;
use Yammi\JobsMonitor\Application\DTO\JobClassStatsData;
use Yammi\JobsMonitor\Application\DTO\PagedResult;
use Yammi\JobsMonitor\Application\DTO\SettingGroupData;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Worker\Entity\Worker;

/**
 * Converts facade return values into plain arrays / scalars that are
 * safe to json_encode and display in the playground. Job payloads are
 * always routed through the host-provided {@see PayloadRedactor} so
 * sensitive keys (tokens, passwords, credit cards) are stripped before
 * reaching the browser.
 */
final class ResultSerializer
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly PayloadRedactor $redactor,
    ) {}

    public function serialize(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(self::DATE_FORMAT);
        }

        if ($value instanceof UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        if ($value instanceof PagedResult) {
            return [
                'items' => array_map(fn ($i) => $this->serialize($i), $value->items),
                'total' => $value->total,
                'page' => $value->page,
                'per_page' => $value->perPage,
                'total_pages' => $value->totalPages(),
                'has_more_pages' => $value->hasMorePages(),
            ];
        }

        if ($value instanceof BulkOperationResult) {
            return [
                'succeeded' => $value->succeeded,
                'failed' => $value->failed,
                'total' => $value->total(),
                'errors' => $value->errors,
            ];
        }

        if ($value instanceof JobRecord) {
            return $this->serializeJobRecord($value);
        }

        if ($value instanceof FailureGroup) {
            return $this->serializeFailureGroup($value);
        }

        if ($value instanceof Worker) {
            return $this->serializeWorker($value);
        }

        if ($value instanceof ScheduledTaskRun) {
            return $this->serializeScheduledTaskRun($value);
        }

        if ($value instanceof ManagedAlertRule) {
            return $this->serializeManagedAlertRule($value);
        }

        if ($value instanceof JobClassStatsData
            || $value instanceof AlertSettingsData
            || $value instanceof AlertRulesOverviewData
            || $value instanceof SettingGroupData) {
            return $this->serializeReadonlyDto($value);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->serialize($v);
            }

            return $out;
        }

        if (is_object($value)) {
            return $this->serializeReadonlyDto($value);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeJobRecord(JobRecord $record): array
    {
        $payload = $record->payload();

        return [
            'uuid' => $record->id->value,
            'attempt' => $record->attempt->value,
            'job_class' => $record->jobClass,
            'connection' => $record->connection,
            'queue' => $record->queue->value,
            'started_at' => $record->startedAt->format(self::DATE_FORMAT),
            'finished_at' => $record->finishedAt()?->format(self::DATE_FORMAT),
            'status' => $record->status()->value,
            'duration_ms' => $record->duration()?->milliseconds,
            'failure_category' => $record->failureCategory()?->value,
            'exception' => $record->exception(),
            'payload' => $payload !== null ? $this->redactor->redact($payload) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFailureGroup(FailureGroup $group): array
    {
        return [
            'fingerprint' => $group->fingerprint()->hash,
            'first_seen_at' => $group->firstSeenAt()->format(self::DATE_FORMAT),
            'last_seen_at' => $group->lastSeenAt()->format(self::DATE_FORMAT),
            'occurrences' => $group->occurrences(),
            'affected_job_classes' => $group->affectedJobClasses(),
            'last_job_uuid' => $group->lastJobId()->value,
            'sample_exception_class' => $group->sampleExceptionClass(),
            'sample_message' => $group->sampleMessage(),
            'sample_stack_trace' => $group->sampleStackTrace(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWorker(Worker $worker): array
    {
        $hb = $worker->heartbeat();

        return [
            'worker_id' => $hb->workerId->value,
            'queue' => $hb->queue,
            'connection' => $hb->connection,
            'host' => $hb->host,
            'pid' => $hb->pid,
            'last_seen_at' => $hb->lastSeenAt->format(self::DATE_FORMAT),
            'stopped_at' => $worker->stoppedAt()?->format(self::DATE_FORMAT),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeScheduledTaskRun(ScheduledTaskRun $run): array
    {
        return [
            'task_name' => $run->taskName,
            'command' => $run->command,
            'started_at' => $run->startedAt->format(self::DATE_FORMAT),
            'finished_at' => $run->finishedAt()?->format(self::DATE_FORMAT),
            'status' => $run->status()->value,
            'exit_code' => $run->exitCode(),
            'duration_ms' => $run->duration()?->milliseconds,
            'exception' => $run->exception(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeManagedAlertRule(ManagedAlertRule $rule): array
    {
        return [
            'id' => $rule->id(),
            'key' => $rule->key(),
            'enabled' => $rule->isEnabled(),
            'overrides_built_in' => $rule->overridesBuiltIn(),
            'position' => $rule->position(),
            'rule' => $this->serializeReadonlyDto($rule->rule()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReadonlyDto(object $value): array
    {
        $reflection = new \ReflectionObject($value);
        $out = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $out[$prop->getName()] = $this->serialize($prop->getValue($value));
        }

        return $out;
    }
}
