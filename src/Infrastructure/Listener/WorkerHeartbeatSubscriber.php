<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Listener;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Psr\Log\LoggerInterface;
use Throwable;
use Yammi\JobsMonitor\Application\Action\MarkWorkerStoppedAction;
use Yammi\JobsMonitor\Application\Action\RecordWorkerHeartbeatAction;
use Yammi\JobsMonitor\Application\Contract\WorkerIdentityResolver;
use Yammi\JobsMonitor\Application\DTO\WorkerHeartbeatData;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;

/**
 * Bridges Laravel queue events into the Worker aggregate.
 *
 * Subscribes to three events:
 *   - `JobProcessing` — pulse tied to an actual job with known queue.
 *   - `Looping`      — idle pulse; still tells us the worker is alive.
 *   - `WorkerStopping` — graceful shutdown; flags the worker as Dead
 *     immediately so the watchdog does not waste an alert cycle on it.
 *
 * Every side effect is wrapped in try/catch — failures in the monitor
 * MUST NOT break the host's job processing. The queue is sacred.
 */
final class WorkerHeartbeatSubscriber
{
    public function __construct(
        private readonly RecordWorkerHeartbeatAction $recordAction,
        private readonly MarkWorkerStoppedAction $stopAction,
        private readonly WorkerIdentityResolver $identity,
        private readonly LoggerInterface $logger,
    ) {}

    public function handleJobProcessing(JobProcessing $event): void
    {
        $this->safely(function () use ($event): void {
            $identity = $this->identity->resolve();

            ($this->recordAction)(new WorkerHeartbeatData(
                workerId: $this->workerIdString($identity),
                connection: (string) $event->connectionName,
                queue: $this->resolveQueue($event),
                host: $identity['host'],
                pid: $identity['pid'],
                observedAt: new DateTimeImmutable,
            ));
        });
    }

    public function handleLooping(Looping $event): void
    {
        $this->safely(function () use ($event): void {
            $identity = $this->identity->resolve();

            ($this->recordAction)(new WorkerHeartbeatData(
                workerId: $this->workerIdString($identity),
                connection: (string) $event->connectionName,
                queue: $event->queue !== '' ? (string) $event->queue : 'default',
                host: $identity['host'],
                pid: $identity['pid'],
                observedAt: new DateTimeImmutable,
            ));
        });
    }

    public function handleWorkerStopping(WorkerStopping $event): void
    {
        $this->safely(function (): void {
            $identity = $this->identity->resolve();

            ($this->stopAction)(
                new WorkerIdentifier($this->workerIdString($identity)),
                new DateTimeImmutable,
            );
        });
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            JobProcessing::class => 'handleJobProcessing',
            Looping::class => 'handleLooping',
            WorkerStopping::class => 'handleWorkerStopping',
        ];
    }

    /**
     * @param  array{host: string, pid: int}  $identity
     */
    private function workerIdString(array $identity): string
    {
        return $identity['host'].':'.$identity['pid'];
    }

    private function resolveQueue(JobProcessing $event): string
    {
        $queue = $event->job->getQueue();

        return is_string($queue) && $queue !== '' ? $queue : 'default';
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            $this->logger->debug(
                'jobs-monitor: worker heartbeat write failed',
                ['exception' => $e::class, 'message' => $e->getMessage()],
            );
        }
    }
}
