<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use DateTimeImmutable;
use Yammi\JobsMonitor\Application\Contract\WorkerAlertStateStore;
use Yammi\JobsMonitor\Application\DTO\WorkerAlertSummary;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Domain\Worker\Entity\Worker;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;

/**
 * Compares the observed worker state against thresholds and expected
 * counts, emitting WorkerSilent / WorkerUnderprovisioned alerts when
 * conditions transition and resolve events when they clear.
 *
 * Uses a state store to remember which worker ids / queue keys were
 * alerting on the previous tick — without it we cannot distinguish
 * "still tripped" from "just recovered".
 */
final class DetectSilentWorkersAction
{
    private const CATEGORY_SILENT = 'silent';

    private const CATEGORY_UNDERPROVISIONED = 'underprovisioned';

    /**
     * @param  array<string, int>  $expected  Map of queueKey ("connection:queue")
     *                                        → minimum alive worker count.
     */
    public function __construct(
        private readonly WorkerRepository $repository,
        private readonly WorkerAlertStateStore $stateStore,
        private readonly SendAlertAction $sender,
        private readonly int $silentAfterSeconds,
        private readonly array $expected,
        /** @var list<string> */
        private readonly array $channels,
    ) {}

    public function __invoke(DateTimeImmutable $now): WorkerAlertSummary
    {
        [$silentTriggered, $silentResolved] = $this->emitSilentTransitions($now);
        [$underTriggered, $underResolved] = $this->emitUnderprovisionedTransitions($now);

        return new WorkerAlertSummary(
            silentTriggered: $silentTriggered,
            silentResolved: $silentResolved,
            underprovisionedTriggered: $underTriggered,
            underprovisionedResolved: $underResolved,
        );
    }

    /**
     * @return array{0: int, 1: int} [triggered, resolved]
     */
    private function emitSilentTransitions(DateTimeImmutable $now): array
    {
        $cutoff = $now->modify(sprintf('-%d seconds', $this->silentAfterSeconds));
        $silentWorkers = $this->repository->findSilentSince($cutoff);

        $currentIds = [];
        $indexed = [];
        foreach ($silentWorkers as $worker) {
            $id = $worker->heartbeat()->workerId->value;
            $currentIds[] = $id;
            $indexed[$id] = $worker;
        }

        $previous = $this->stateStore->active(self::CATEGORY_SILENT);

        $newlySilent = array_values(array_diff($currentIds, $previous));
        $recovered = array_values(array_diff($previous, $currentIds));

        foreach ($newlySilent as $id) {
            $this->dispatch(
                $this->silentPayload($indexed[$id], $now, AlertAction::Trigger),
            );
        }

        foreach ($recovered as $id) {
            $this->dispatch(
                $this->silentResolvePayload($id, $now),
            );
        }

        $this->stateStore->replace(self::CATEGORY_SILENT, $currentIds);

        return [count($newlySilent), count($recovered)];
    }

    /**
     * @return array{0: int, 1: int} [triggered, resolved]
     */
    private function emitUnderprovisionedTransitions(DateTimeImmutable $now): array
    {
        if ($this->expected === []) {
            // Host did not configure any expectations — skip entirely,
            // including clearing any stored state from when it had.
            $this->stateStore->replace(self::CATEGORY_UNDERPROVISIONED, []);

            return [0, 0];
        }

        $aliveSince = $now->modify(sprintf('-%d seconds', $this->silentAfterSeconds));
        $observed = $this->repository->countAliveByQueueKey($aliveSince);

        $currentKeys = [];
        $shortfalls = [];
        foreach ($this->expected as $queueKey => $min) {
            $actual = $observed[$queueKey] ?? 0;
            if ($actual < $min) {
                $currentKeys[] = $queueKey;
                $shortfalls[$queueKey] = ['observed' => $actual, 'expected' => $min];
            }
        }

        $previous = $this->stateStore->active(self::CATEGORY_UNDERPROVISIONED);
        $newlyDown = array_values(array_diff($currentKeys, $previous));
        $recovered = array_values(array_diff($previous, $currentKeys));

        foreach ($newlyDown as $queueKey) {
            $this->dispatch(
                $this->underprovisionedPayload(
                    $queueKey,
                    $shortfalls[$queueKey]['observed'],
                    $shortfalls[$queueKey]['expected'],
                    $now,
                    AlertAction::Trigger,
                ),
            );
        }

        foreach ($recovered as $queueKey) {
            $this->dispatch(
                $this->underprovisionedResolvePayload($queueKey, $now),
            );
        }

        $this->stateStore->replace(self::CATEGORY_UNDERPROVISIONED, $currentKeys);

        return [count($newlyDown), count($recovered)];
    }

    private function silentPayload(Worker $worker, DateTimeImmutable $now, AlertAction $action): AlertPayload
    {
        $hb = $worker->heartbeat();
        $elapsed = $now->getTimestamp() - $hb->lastSeenAt->getTimestamp();

        return new AlertPayload(
            trigger: AlertTrigger::WorkerSilent,
            subject: sprintf('Worker silent: %s', $hb->workerId->value),
            body: sprintf(
                'Last heartbeat %ds ago on queue %s (threshold: %ds).',
                $elapsed,
                $hb->queueKey(),
                $this->silentAfterSeconds,
            ),
            context: [
                'worker_id' => $hb->workerId->value,
                'connection' => $hb->connection,
                'queue' => $hb->queue,
                'queue_key' => $hb->queueKey(),
                'host' => $hb->host,
                'pid' => $hb->pid,
                'last_seen_at' => $hb->lastSeenAt->format(DATE_ATOM),
                'silent_after_seconds' => $this->silentAfterSeconds,
            ],
            triggeredAt: $now,
            fingerprint: $this->silentFingerprint($hb->workerId->value),
            action: $action,
        );
    }

    private function silentResolvePayload(string $workerId, DateTimeImmutable $now): AlertPayload
    {
        return new AlertPayload(
            trigger: AlertTrigger::WorkerSilent,
            subject: sprintf('Worker silent: %s', $workerId),
            body: 'Worker heartbeat resumed.',
            context: ['worker_id' => $workerId],
            triggeredAt: $now,
            fingerprint: $this->silentFingerprint($workerId),
            action: AlertAction::Resolve,
        );
    }

    private function underprovisionedPayload(
        string $queueKey,
        int $observed,
        int $expected,
        DateTimeImmutable $now,
        AlertAction $action,
    ): AlertPayload {
        return new AlertPayload(
            trigger: AlertTrigger::WorkerUnderprovisioned,
            subject: sprintf('Queue underprovisioned: %s', $queueKey),
            body: sprintf(
                'Observed %d alive worker(s) for %s; expected at least %d.',
                $observed,
                $queueKey,
                $expected,
            ),
            context: [
                'queue_key' => $queueKey,
                'observed' => $observed,
                'expected' => $expected,
                'shortfall' => $expected - $observed,
            ],
            triggeredAt: $now,
            fingerprint: $this->underprovisionedFingerprint($queueKey),
            action: $action,
        );
    }

    private function underprovisionedResolvePayload(string $queueKey, DateTimeImmutable $now): AlertPayload
    {
        return new AlertPayload(
            trigger: AlertTrigger::WorkerUnderprovisioned,
            subject: sprintf('Queue underprovisioned: %s', $queueKey),
            body: 'Queue now meets expected worker count.',
            context: ['queue_key' => $queueKey],
            triggeredAt: $now,
            fingerprint: $this->underprovisionedFingerprint($queueKey),
            action: AlertAction::Resolve,
        );
    }

    private function silentFingerprint(string $workerId): string
    {
        return 'worker_silent:'.$workerId;
    }

    private function underprovisionedFingerprint(string $queueKey): string
    {
        return 'worker_underprovisioned:'.$queueKey;
    }

    private function dispatch(AlertPayload $payload): void
    {
        ($this->sender)($payload, $this->channels);
    }
}
