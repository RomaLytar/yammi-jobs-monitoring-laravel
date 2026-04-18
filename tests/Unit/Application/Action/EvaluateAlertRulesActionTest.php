<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yammi\JobsMonitor\Application\Action\EvaluateAlertRulesAction;
use Yammi\JobsMonitor\Application\Action\SendAlertAction;
use Yammi\JobsMonitor\Application\Service\AlertConfigResolver;
use Yammi\JobsMonitor\Application\Service\AlertRuleEvaluator;
use Yammi\JobsMonitor\Application\Service\AlertRuleFactory;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Domain\Alert\Contract\AlertThrottle;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Tests\Support\Alert\NullLogger;
use Yammi\JobsMonitor\Tests\Support\Alert\RecordingChannel;
use Yammi\JobsMonitor\Tests\Support\Alert\RecordingLogger;
use Yammi\JobsMonitor\Tests\Support\InMemoryAlertSettingsRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryBuiltInRuleStateRepository;
<<<<<<< HEAD
use Yammi\JobsMonitor\Tests\Support\InMemoryFailureGroupRepository;
=======
>>>>>>> origin/main
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;

final class EvaluateAlertRulesActionTest extends TestCase
{
    public function test_matching_rule_is_dispatched_to_its_channels(): void
    {
        $repo = $this->repoWithFailures(10);
        $slack = new RecordingChannel('slack');
        $mail = new RecordingChannel('mail');
        $throttle = new PassingThrottle;

        $rule = $this->failureRateRule(threshold: 5, channels: ['slack']);

        $action = new EvaluateAlertRulesAction(
<<<<<<< HEAD
            new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, 3),
=======
            new AlertRuleEvaluator($repo, 3),
>>>>>>> origin/main
            new SendAlertAction([$slack, $mail], new NullLogger),
            $throttle,
            new NullLogger,
            $this->resolverFor($rule),
        );

        $action($this->now());

        self::assertCount(1, $slack->sent);
        self::assertCount(0, $mail->sent);
        self::assertSame([$rule->ruleKey()], $throttle->attempted);
    }

    public function test_non_matching_rule_is_skipped_silently(): void
    {
        $repo = $this->repoWithFailures(1);
        $slack = new RecordingChannel('slack');
        $throttle = new PassingThrottle;

        $rule = $this->failureRateRule(threshold: 10, channels: ['slack']);

        $action = new EvaluateAlertRulesAction(
<<<<<<< HEAD
            new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, 3),
=======
            new AlertRuleEvaluator($repo, 3),
>>>>>>> origin/main
            new SendAlertAction([$slack], new NullLogger),
            $throttle,
            new NullLogger,
            $this->resolverFor($rule),
        );

        $action($this->now());

        self::assertCount(0, $slack->sent);
        self::assertSame([], $throttle->attempted);
    }

    public function test_throttled_rule_is_not_dispatched_even_when_matching(): void
    {
        $repo = $this->repoWithFailures(10);
        $slack = new RecordingChannel('slack');
        $throttle = new BlockingThrottle;

        $rule = $this->failureRateRule(threshold: 5, channels: ['slack']);

        $action = new EvaluateAlertRulesAction(
<<<<<<< HEAD
            new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, 3),
=======
            new AlertRuleEvaluator($repo, 3),
>>>>>>> origin/main
            new SendAlertAction([$slack], new NullLogger),
            $throttle,
            new NullLogger,
            $this->resolverFor($rule),
        );

        $action($this->now());

        self::assertCount(0, $slack->sent);
    }

    public function test_rule_evaluation_exception_does_not_break_subsequent_rules(): void
    {
        $repo = $this->repoWithFailures(10);
        $slack = new RecordingChannel('slack');
        $logger = new RecordingLogger;

        $bad = $this->failureRateRule(threshold: 5, channels: ['slack']);
        $good = new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '10m', // different ruleKey so throttle can distinguish
            threshold: 5,
            channels: ['slack'],
            cooldownMinutes: 15,
        );

        $action = new EvaluateAlertRulesAction(
<<<<<<< HEAD
            new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, 3),
=======
            new AlertRuleEvaluator($repo, 3),
>>>>>>> origin/main
            new SendAlertAction([$slack], new NullLogger),
            new ExplodingThrottle($bad->ruleKey()),
            $logger,
            $this->resolverFor($bad, $good),
        );

        $action($this->now());

        // Good rule still fired even though the first rule's throttle blew up
        self::assertCount(1, $slack->sent);
        // Bad rule's failure was logged
        self::assertNotEmpty($logger->records);
        self::assertStringContainsString($bad->ruleKey(), $logger->records[0]['message']);
    }

    public function test_each_rule_gets_its_own_channel_routing(): void
    {
        $repo = $this->repoWithFailures(10);
        $slack = new RecordingChannel('slack');
        $mail = new RecordingChannel('mail');
        $throttle = new PassingThrottle;

        $slackOnly = $this->failureRateRule(threshold: 5, channels: ['slack']);
        $mailOnly = $this->failureRateRule(threshold: 5, channels: ['mail']);

        $action = new EvaluateAlertRulesAction(
<<<<<<< HEAD
            new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, 3),
=======
            new AlertRuleEvaluator($repo, 3),
>>>>>>> origin/main
            new SendAlertAction([$slack, $mail], new NullLogger),
            $throttle,
            new NullLogger,
            $this->resolverFor($slackOnly, $mailOnly),
        );

        $action($this->now());

        self::assertCount(1, $slack->sent);
        self::assertCount(1, $mail->sent);
    }

    public function test_disabled_resolver_short_circuits_without_dispatching(): void
    {
        $repo = $this->repoWithFailures(50);
        $slack = new RecordingChannel('slack');
        $throttle = new PassingThrottle;

        $rule = $this->failureRateRule(threshold: 5, channels: ['slack']);

        $action = new EvaluateAlertRulesAction(
<<<<<<< HEAD
            new AlertRuleEvaluator($repo, new InMemoryFailureGroupRepository, 3),
=======
            new AlertRuleEvaluator($repo, 3),
>>>>>>> origin/main
            new SendAlertAction([$slack], new NullLogger),
            $throttle,
            new NullLogger,
            $this->disabledResolverFor($rule),
        );

        $action($this->now());

        self::assertCount(0, $slack->sent);
        self::assertSame([], $throttle->attempted);
    }

    /**
     * @param  list<string>  $channels
     */
    private function failureRateRule(int $threshold, array $channels): AlertRule
    {
        return new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '5m',
            threshold: $threshold,
            channels: $channels,
            cooldownMinutes: 15,
        );
    }

    private function resolverFor(AlertRule ...$rules): AlertConfigResolver
    {
        return $this->buildResolver($rules, configEnabled: true);
    }

    private function disabledResolverFor(AlertRule ...$rules): AlertConfigResolver
    {
        $settings = new InMemoryAlertSettingsRepository;
        $settings->save(new AlertSettings(false, null, null, new EmailRecipientList([])));

        return $this->buildResolver($rules, configEnabled: true, settingsRepo: $settings);
    }

    /**
     * @param  list<AlertRule>  $rules
     */
    private function buildResolver(
        array $rules,
        bool $configEnabled,
        ?InMemoryAlertSettingsRepository $settingsRepo = null,
    ): AlertConfigResolver {
        $rulesRepo = new InMemoryManagedAlertRuleRepository;
        foreach (array_values($rules) as $position => $rule) {
            $rulesRepo->save(new ManagedAlertRule(
                id: null,
                key: 'test_rule_'.$position,
                rule: $rule,
                enabled: true,
                overridesBuiltIn: null,
                position: $position,
            ));
        }

        $state = new InMemoryBuiltInRuleStateRepository;
        foreach (['critical_failure', 'retry_storm', 'high_failure_rate', 'dlq_growing'] as $key) {
            $state->setEnabled($key, false);
        }

        $factory = new AlertRuleFactory;

        return new AlertConfigResolver(
            settingsRepo: $settingsRepo ?? new InMemoryAlertSettingsRepository,
            rulesRepo: $rulesRepo,
            builtInStateRepo: $state,
            builtInRulesProvider: new BuiltInRulesProvider($factory),
            ruleFactory: $factory,
            configEnabled: $configEnabled,
            builtInConfigOverrides: [],
            configCustomRules: [],
        );
    }

    private function repoWithFailures(int $count): InMemoryJobRecordRepository
    {
        $repo = new InMemoryJobRecordRepository;
        for ($i = 0; $i < $count; $i++) {
            $uuid = sprintf('550e8400-e29b-41d4-a716-44665544%04d', $i);
            $record = new JobRecord(
                id: new JobIdentifier($uuid),
                attempt: Attempt::first(),
                jobClass: 'App\\Jobs\\TestJob',
                connection: 'redis',
                queue: new QueueName('default'),
                startedAt: $this->now()->modify('-1 minute'),
            );
            $record->markAsFailed($this->now()->modify('-30 seconds'), 'boom');
            $repo->save($record);
        }

        return $repo;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-04-13T12:00:00Z');
    }
}

final class PassingThrottle implements AlertThrottle
{
    /** @var list<string> */
    public array $attempted = [];

    public function attempt(string $ruleKey, int $cooldownMinutes): bool
    {
        $this->attempted[] = $ruleKey;

        return true;
    }
}

final class BlockingThrottle implements AlertThrottle
{
    public function attempt(string $ruleKey, int $cooldownMinutes): bool
    {
        return false;
    }
}

final class ExplodingThrottle implements AlertThrottle
{
    public function __construct(private readonly string $explodingRuleKey) {}

    public function attempt(string $ruleKey, int $cooldownMinutes): bool
    {
        if ($ruleKey === $this->explodingRuleKey) {
            throw new RuntimeException('throttle transport down');
        }

        return true;
    }
}
