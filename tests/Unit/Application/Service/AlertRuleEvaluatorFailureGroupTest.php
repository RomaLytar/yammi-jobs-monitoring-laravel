<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Service\AlertRuleEvaluator;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Tests\Support\InMemoryFailureGroupRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;

final class AlertRuleEvaluatorFailureGroupTest extends TestCase
{
    private const NOW = '2026-04-13T12:00:00Z';

    public function test_returns_no_payload_when_no_new_groups_in_window(): void
    {
        $groups = new InMemoryFailureGroupRepository;
        $groups->save($this->makeGroup('1111111111111111', firstSeenAgoMinutes: 30));

        $evaluator = $this->evaluator($groups);

        self::assertSame([], $evaluator->evaluate($this->rule(threshold: 1, window: '10m'), $this->now()));
    }

    public function test_returns_payload_when_threshold_met_for_new_groups(): void
    {
        $groups = new InMemoryFailureGroupRepository;
        $groups->save($this->makeGroup('1111111111111111', firstSeenAgoMinutes: 2));
        $groups->save($this->makeGroup('2222222222222222', firstSeenAgoMinutes: 4));

        $evaluator = $this->evaluator($groups);

        $payloads = $evaluator->evaluate($this->rule(threshold: 2, window: '10m'), $this->now());

        self::assertCount(1, $payloads);
        $payload = $payloads[0];
        self::assertSame(AlertTrigger::FailureGroupNew, $payload->trigger);
        self::assertSame(2, $payload->context['count']);
        self::assertSame(2, $payload->context['threshold']);
        self::assertSame('10m', $payload->context['window']);
        self::assertStringContainsString('new failure groups', strtolower($payload->subject));
    }

    public function test_includes_fingerprint_hashes_in_context(): void
    {
        $groups = new InMemoryFailureGroupRepository;
        $groups->save($this->makeGroup('1111111111111111', firstSeenAgoMinutes: 2));
        $groups->save($this->makeGroup('2222222222222222', firstSeenAgoMinutes: 4));

        $evaluator = $this->evaluator($groups);

        $payloads = $evaluator->evaluate($this->rule(threshold: 1, window: '10m'), $this->now());

        self::assertCount(1, $payloads);
        $payload = $payloads[0];
        self::assertIsArray($payload->context['fingerprints']);
        self::assertContains('1111111111111111', $payload->context['fingerprints']);
        self::assertContains('2222222222222222', $payload->context['fingerprints']);
    }

    private function evaluator(InMemoryFailureGroupRepository $groups): AlertRuleEvaluator
    {
        return new AlertRuleEvaluator(
            new InMemoryJobRecordRepository,
            $groups,
            maxTries: 3,
        );
    }

    private function rule(int $threshold, string $window): AlertRule
    {
        return new AlertRule(
            trigger: AlertTrigger::FailureGroupNew,
            window: $window,
            threshold: $threshold,
            channels: ['slack'],
            cooldownMinutes: 15,
        );
    }

    private function makeGroup(string $hash, int $firstSeenAgoMinutes): FailureGroup
    {
        $firstSeen = $this->now()->modify("-{$firstSeenAgoMinutes} minutes");

        return new FailureGroup(
            fingerprint: new FailureFingerprint($hash),
            firstSeenAt: $firstSeen,
            lastSeenAt: $firstSeen,
            occurrences: 1,
            affectedJobClasses: ['App\\Jobs\\OrderJob'],
            lastJobId: new JobIdentifier('550e8400-e29b-41d4-a716-446655440000'),
            sampleExceptionClass: 'App\\Exceptions\\Boom',
            sampleMessage: 'boom',
            sampleStackTrace: '',
        );
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }
}
