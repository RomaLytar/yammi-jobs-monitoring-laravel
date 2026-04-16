<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Worker\ValueObject;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Worker\Exception\InvalidWorkerHeartbeat;

final class WorkerHeartbeat
{
    public readonly string $connection;

    public readonly string $queue;

    public readonly string $host;

    public function __construct(
        public readonly WorkerIdentifier $workerId,
        string $connection,
        string $queue,
        string $host,
        public readonly int $pid,
        public readonly DateTimeImmutable $lastSeenAt,
    ) {
        $this->connection = $this->ensureNotBlank($connection, 'connection');
        $this->queue = $this->ensureNotBlank($queue, 'queue');
        $this->host = $this->ensureNotBlank($host, 'host');

        if ($pid <= 0) {
            throw new InvalidWorkerHeartbeat(sprintf(
                'Worker PID must be a positive integer, got %d.',
                $pid,
            ));
        }
    }

    public function queueKey(): string
    {
        return $this->connection.':'.$this->queue;
    }

    private function ensureNotBlank(string $value, string $field): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidWorkerHeartbeat(sprintf(
                'Worker heartbeat %s must not be blank.',
                $field,
            ));
        }

        return $trimmed;
    }
}
