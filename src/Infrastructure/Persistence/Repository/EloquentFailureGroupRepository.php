<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\FailureGroupModel;

final class EloquentFailureGroupRepository implements FailureGroupRepository
{
    public function findByFingerprint(FailureFingerprint $fingerprint): ?FailureGroup
    {
        $model = FailureGroupModel::query()
            ->where('fingerprint', $fingerprint->hash)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function save(FailureGroup $group): void
    {
        FailureGroupModel::query()->updateOrCreate(
            ['fingerprint' => $group->fingerprint()->hash],
            [
                'first_seen_at' => $group->firstSeenAt(),
                'last_seen_at' => $group->lastSeenAt(),
                'occurrences' => $group->occurrences(),
                'affected_job_classes' => $group->affectedJobClasses(),
                'last_job_uuid' => $group->lastJobId()->value,
                'sample_exception_class' => $group->sampleExceptionClass(),
                'sample_message' => $group->sampleMessage(),
                'sample_stack_trace' => $group->sampleStackTrace(),
            ],
        );
    }

    public function listOrderedByLastSeen(int $limit, int $offset): array
    {
        return FailureGroupModel::query()
            ->orderByDesc('last_seen_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (FailureGroupModel $model) => $this->toDomain($model))
            ->values()
            ->all();
    }

    public function countAll(): int
    {
        return FailureGroupModel::query()->count();
    }

    public function firstSeenSince(DateTimeImmutable $since): array
    {
        return FailureGroupModel::query()
            ->where('first_seen_at', '>=', $since)
            ->orderByDesc('first_seen_at')
            ->get()
            ->map(fn (FailureGroupModel $model) => $this->toDomain($model))
            ->values()
            ->all();
    }

    private function toDomain(FailureGroupModel $model): FailureGroup
    {
        /** @var array<int, string> $classes */
        $classes = $model->affected_job_classes ?? [];

        return new FailureGroup(
            fingerprint: new FailureFingerprint($model->fingerprint),
            firstSeenAt: $model->first_seen_at,
            lastSeenAt: $model->last_seen_at,
            occurrences: $model->occurrences,
            affectedJobClasses: array_values($classes),
            lastJobId: new JobIdentifier($model->last_job_uuid),
            sampleExceptionClass: $model->sample_exception_class,
            sampleMessage: $model->sample_message,
            sampleStackTrace: $model->sample_stack_trace,
        );
    }
}
