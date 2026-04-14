<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Service\AlertRuleEvaluator;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Tests\Support\InMemoryFailureGroupRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;

final class AlertRuleEvaluatorTest extends TestCase
{
    private const NOW = '2026-04-13T12:00:00Z';

    public function test_failure_rate_below_threshold_returns_null(): void
    {
        $repo = new InMemoryJobRecordRepository;
        $this->seedFailures($repo, count: 4, ago: '-2 minutes');

        $evaluator = new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, maxTries: 3);

        $rule = new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '5m',
            threshold: 10,
            channels: ['slack'],
            cooldownMinutes: 15,
        );

        self::assertNull($evaluator->evaluate($rule, $this->now()));
    }

    public function test_failure_rate_at_or_above_threshold_returns_payload(): void
    {
        $repo = new InMemoryJobRecordRepository;
        $this->seedFailures($repo, count: 10, ago: '-2 minutes');

        $evaluator = new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, maxTries: 3);

        $rule = new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '5m',
            threshold: 10,
            channels: ['slack'],
            cooldownMinutes: 15,
        );

        $payload = $evaluator->evaluate($rule, $this->now());

        self::assertNotNull($payload);
        self::assertSame(AlertTrigger::FailureRate, $payload->trigger);
        self::assertSame('Failure rate threshold reached', $payload->subject);
        self::assertStringContainsString('10', $payload->body);
        self::assertStringContainsString('5m', $payload->body);
        self::assertSame(10, $payload->context['count']);
        self::assertSame(10, $payload->context['threshold']);
        self::assertSame('5m', $payload->context['window']);
        self::assertEquals($this->now(), $payload->triggeredAt);
    }

    public function test_failure_category_rule_filters_by_category(): void
    {
        $repo = new InMemoryJobRecordRepository;
        // 2 critical, 5 transient within window
        $this->seedFailures($repo, count: 2, ago: '-1 minute', category: FailureCategory::Critical, uuidSuffix: 'a');
        $this->seedFailures($repo, count: 5, ago: '-1 minute', category: FailureCategory::Transient, uuidSuffix: 'b');

        $evaluator = new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, maxTries: 3);

        $criticalRule = new AlertRule(
            trigger: AlertTrigger::FailureCategory,
            window: '5m',
            threshold: 2,
            channels: ['slack'],
            cooldownMinutes: 5,
            triggerValue: 'critical',
        );

        $payload = $evaluator->evaluate($criticalRule, $this->now());

        self::assertNotNull($payload);
        self::assertSame(AlertTrigger::FailureCategory, $payload->trigger);
        self::assertSame('critical', $payload->context['category']);
        self::assertSame(2, $payload->context['count']);
    }

    public function test_failure_category_rule_does_not_fire_for_other_categories(): void
    {
        $repo = new InMemoryJobRecordRepository;
        $this->seedFailures($repo, count: 10, ago: '-1 minute', category: FailureCategory::Transient);

        $evaluator = new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, maxTries: 3);

        $rule = new AlertRule(
            trigger: AlertTrigger::FailureCategory,
            window: '5m',
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: 5,
            triggerValue: 'critical',
        );

        self::assertNull($evaluator->evaluate($rule, $this->now()));
    }

    public function test_job_class_failure_rate_filters_by_class(): void
    {
        $repo = new InMemoryJobRecordRepository;
        $this->seedFailures(
            $repo,
            count: 3,
            ago: '-1 minute',
            jobClass: 'App\\Jobs\\SendInvoice',
            uuidSuffix: 'a',
        );
        $this->seedFailures(
            $repo,
            count: 10,
            ago: '-1 minute',
            jobClass: 'App\\Jobs\\GenerateReport',
            uuidSuffix: 'b',
        );

        $evaluator = new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, maxTries: 3);

        $rule = new AlertRule(
            trigger: AlertTrigger::JobClassFailureRate,
            window: '5m',
            threshold: 3,
            channels: ['slack'],
            cooldownMinutes: 5,
            triggerValue: 'App\\Jobs\\SendInvoice',
        );

        $payload = $evaluator->evaluate($rule, $this->now());

        self::assertNotNull($payload);
        self::assertSame('App\\Jobs\\SendInvoice', $payload->context['job_class']);
        self::assertSame(3, $payload->context['count']);
    }

    public function test_dlq_size_rule_fires_when_dlq_large(): void
    {
        $repo = new InMemoryJobRecordRepository;

        // Seed 5 dead-letter records (failed + exhausted attempts)
        for ($i = 0; $i < 5; $i++) {
            $uuid = sprintf('550e8400-e29b-41d4-a716-44665544%04d', $i);
            $record = new JobRecord(
                id: new JobIdentifier($uuid),
                attempt: new Attempt(3),
                jobClass: 'App\\Jobs\\Whatever',
                connection: 'redis',
                queue: new QueueName('default'),
                startedAt: $this->now()->modify('-1 hour'),
            );
            $record->markAsFailed(
                $this->now()->modify('-30 minutes'),
                'boom',
                FailureCategory::Transient,
            );
            $repo->save($record);
        }

        $evaluator = new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, maxTries: 3);

        $rule = new AlertRule(
            trigger: AlertTrigger::DlqSize,
            window: null,
            threshold: 3,
            channels: ['slack'],
            cooldownMinutes: 30,
        );

        $payload = $evaluator->evaluate($rule, $this->now());

        self::assertNotNull($payload);
        self::assertSame(AlertTrigger::DlqSize, $payload->trigger);
        self::assertSame(5, $payload->context['count']);
        self::assertSame(3, $payload->context['threshold']);
    }

    public function test_dlq_size_rule_silent_when_below_threshold(): void
    {
        $repo = new InMemoryJobRecordRepository;
        $evaluator = new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, maxTries: 3);

        $rule = new AlertRule(
            trigger: AlertTrigger::DlqSize,
            window: null,
            threshold: 5,
            channels: ['slack'],
            cooldownMinutes: 30,
        );

        self::assertNull($evaluator->evaluate($rule, $this->now()));
    }

    public function test_failures_outside_window_are_ignored(): void
    {
        $repo = new InMemoryJobRecordRepository;
        // 10 failures but all 2 hours ago — window is only 5 minutes
        $this->seedFailures($repo, count: 10, ago: '-2 hours');

        $evaluator = new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, maxTries: 3);

        $rule = new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '5m',
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: 5,
        );

        self::assertNull($evaluator->evaluate($rule, $this->now()));
    }

    private function seedFailures(
        InMemoryJobRecordRepository $repo,
        int $count,
        string $ago,
        ?FailureCategory $category = null,
        string $jobClass = 'App\\Jobs\\TestJob',
        string $uuidSuffix = '0',
    ): void {
        $finishedAt = $this->now()->modify($ago);

        for ($i = 0; $i < $count; $i++) {
            // build unique uuid
            $uuid = sprintf(
                '550e8400-e29b-41d4-a716-%03s%09d',
                $uuidSuffix,
                $i,
            );
            $record = new JobRecord(
                id: new JobIdentifier($uuid),
                attempt: Attempt::first(),
                jobClass: $jobClass,
                connection: 'redis',
                queue: new QueueName('default'),
                startedAt: $finishedAt->modify('-1 second'),
            );
            $record->markAsFailed($finishedAt, 'boom', $category);
            $repo->save($record);
        }
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }
}
