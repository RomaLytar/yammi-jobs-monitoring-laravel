<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Alert;

use DateTimeImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\Http;
use Yammi\JobsMonitor\Application\Action\EvaluateAlertRulesAction;
use Yammi\JobsMonitor\Application\Action\SendAlertAction;
use Yammi\JobsMonitor\Application\Service\AlertConfigResolver;
use Yammi\JobsMonitor\Application\Service\AlertRuleEvaluator;
use Yammi\JobsMonitor\Application\Service\AlertRuleFactory;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\SlackNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Throttle\CacheAlertThrottle;
use Yammi\JobsMonitor\Tests\Support\Alert\NullLogger;
use Yammi\JobsMonitor\Tests\Support\InMemoryAlertSettingsRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryBuiltInRuleStateRepository;
<<<<<<< HEAD
use Yammi\JobsMonitor\Tests\Support\InMemoryFailureGroupRepository;
=======
>>>>>>> origin/main
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;
use Yammi\JobsMonitor\Tests\TestCase;

/**
 * End-to-end verification that 50 concurrent failures do not produce
 * 50 alerts. Three layers of defence are at work here:
 *
 *   1. Threshold aggregation — rule fires once when count crosses N.
 *   2. Per-rule cooldown — after firing, the rule goes quiet.
 *   3. Scheduled evaluation — orchestrator runs at most once per minute.
 *
 * This test asserts (1) and (2) directly; (3) is the caller's contract.
 */
final class AntiSpamTest extends TestCase
{
    public function test_fifty_failures_produce_exactly_one_slack_call_per_rule(): void
    {
        Http::fake();

        $this->seedFailures(50);

        $orchestrator = $this->buildOrchestrator();
        $orchestrator(new DateTimeImmutable);

        // Two rules are enabled by default: critical_failure and retry_storm.
        // We seeded 50 first-attempt failures tagged as Critical.
        //   - critical_failure:  1 alert   (50 >= threshold 1, fires once)
        //   - retry_storm:       0 alerts  (attempt=1, min_attempt=2, filter blocks all)
        //
        // Expected delivery: exactly 1 Slack call (and 1 mail, but Mail
        // is tested elsewhere; we only assert Slack calls here to keep
        // the test focused).
        Http::assertSentCount(1);
    }

    public function test_second_run_with_same_failures_delivers_zero_alerts(): void
    {
        Http::fake();

        $this->seedFailures(50);

        $orchestrator = $this->buildOrchestrator();
        $orchestrator(new DateTimeImmutable);   // first pass fires
        $orchestrator(new DateTimeImmutable);   // second pass throttled
        $orchestrator(new DateTimeImmutable);   // third pass throttled

        // Throttle cooldown for critical_failure is 10 minutes; we're
        // still within that window, so no more calls should go out.
        Http::assertSentCount(1);
    }

    public function test_retry_storm_rule_only_counts_attempt_two_plus(): void
    {
        Http::fake();

        // 50 first-attempt failures — retry_storm must stay silent.
        $this->seedFailures(50, attempt: 1, category: FailureCategory::Transient);

        $orchestrator = $this->buildOrchestrator();
        $orchestrator(new DateTimeImmutable);

        // critical_failure does not fire (category=transient, not critical)
        // retry_storm does not fire (50 but all attempt=1, below min_attempt=2)
        Http::assertSentCount(0);

        // Now add 5 more failures at attempt=2 — retry_storm should fire once.
        $this->seedFailures(5, attempt: 2, category: FailureCategory::Transient, uuidSuffix: 'b');
        $orchestrator(new DateTimeImmutable);

        // And only once — not 5 times.
        Http::assertSentCount(1);
    }

    private function buildOrchestrator(): EvaluateAlertRulesAction
    {
        // Configure a reachable Slack URL so the channel is registered.
        $this->app['config']->set('jobs-monitor.alerts.channels.slack.webhook_url', 'https://hooks.slack.test/webhook');

        $repo = $this->app->make(JobRecordRepository::class);
        $factory = new AlertRuleFactory;

        $resolver = new AlertConfigResolver(
            settingsRepo: new InMemoryAlertSettingsRepository,
            rulesRepo: new InMemoryManagedAlertRuleRepository,
            builtInStateRepo: new InMemoryBuiltInRuleStateRepository,
            builtInRulesProvider: new BuiltInRulesProvider($factory),
            ruleFactory: $factory,
            configEnabled: true,
            builtInConfigOverrides: [],
            configCustomRules: [],
        );

        $slack = new SlackNotificationChannel(
            $this->app->make(\Illuminate\Http\Client\Factory::class),
            'https://hooks.slack.test/webhook',
            null,
        );

        return new EvaluateAlertRulesAction(
<<<<<<< HEAD
            new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, 3),
=======
            new AlertRuleEvaluator($repo, 3),
>>>>>>> origin/main
            new SendAlertAction([$slack], new NullLogger),
            new CacheAlertThrottle($this->app->make(CacheFactory::class)->store('array')),
            new NullLogger,
            $resolver,
        );
    }

    private function seedFailures(
        int $count,
        int $attempt = 1,
        FailureCategory $category = FailureCategory::Critical,
        string $uuidSuffix = 'a',
    ): void {
        /** @var JobRecordRepository $repo */
        $repo = $this->app->make(JobRecordRepository::class);
        $now = new DateTimeImmutable;

        for ($i = 0; $i < $count; $i++) {
            $uuid = sprintf(
                '550e8400-e29b-41d4-a716-%03s%09d',
                $uuidSuffix,
                $i,
            );
            $record = new JobRecord(
                id: new JobIdentifier($uuid),
                attempt: new Attempt($attempt),
                jobClass: 'App\\Jobs\\TestJob',
                connection: 'redis',
                queue: new QueueName('default'),
                startedAt: $now->modify('-1 minute'),
            );
            $record->markAsFailed($now->modify('-30 seconds'), 'boom', $category);
            $repo->save($record);
        }
    }
}
