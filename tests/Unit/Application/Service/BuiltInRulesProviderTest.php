<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Service\AlertRuleFactory;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;

final class BuiltInRulesProviderTest extends TestCase
{
<<<<<<< HEAD
    public function test_ships_the_curated_built_in_rules(): void
=======
    public function test_ships_four_built_in_rules(): void
>>>>>>> origin/main
    {
        $provider = new BuiltInRulesProvider(new AlertRuleFactory);

        $ids = array_keys($provider->catalog());

        self::assertContains('critical_failure', $ids);
        self::assertContains('retry_storm', $ids);
        self::assertContains('high_failure_rate', $ids);
        self::assertContains('dlq_growing', $ids);
<<<<<<< HEAD
        self::assertContains('new_failure_group', $ids);
        self::assertContains('failure_group_burst', $ids);
=======
>>>>>>> origin/main
    }

    public function test_default_enabled_rules_are_the_safe_defaults(): void
    {
        $provider = new BuiltInRulesProvider(new AlertRuleFactory);

        $rules = $provider->build([]);

<<<<<<< HEAD
        // critical_failure, retry_storm, new_failure_group, failure_group_burst,
        // scheduled_task_failed, scheduled_task_late, partial_completion are
        // enabled out of the box. high_failure_rate, dlq_growing,
        // duration_anomaly, zero_processed ship disabled (need baselines or
        // host opt-in).
        self::assertCount(7, $rules);
=======
        // Only critical_failure and retry_storm are enabled out of the box.
        // high_failure_rate and dlq_growing ship disabled to avoid noise
        // in hosts that don't yet know what "normal" looks like.
        self::assertCount(2, $rules);
>>>>>>> origin/main

        $triggers = array_map(fn ($r) => $r->trigger, $rules);
        self::assertContains(AlertTrigger::FailureCategory, $triggers);
        self::assertContains(AlertTrigger::FailureRate, $triggers);
<<<<<<< HEAD
        self::assertContains(AlertTrigger::FailureGroupNew, $triggers);
        self::assertContains(AlertTrigger::FailureGroupBurst, $triggers);
        self::assertContains(AlertTrigger::ScheduledTaskFailed, $triggers);
        self::assertContains(AlertTrigger::ScheduledTaskLate, $triggers);
        self::assertContains(AlertTrigger::PartialCompletion, $triggers);
=======
>>>>>>> origin/main
    }

    public function test_retry_storm_rule_uses_min_attempt_filter(): void
    {
        $provider = new BuiltInRulesProvider(new AlertRuleFactory);

        $rules = $provider->build([]);
        $retryStorm = $this->findByTriggerAndMinAttempt($rules, AlertTrigger::FailureRate, 2);

        self::assertNotNull($retryStorm);
        self::assertSame(2, $retryStorm->minAttempt);
    }

    public function test_user_can_disable_a_built_in(): void
    {
        $provider = new BuiltInRulesProvider(new AlertRuleFactory);

        $rules = $provider->build([
            'critical_failure' => ['enabled' => false],
<<<<<<< HEAD
            'new_failure_group' => ['enabled' => false],
            'failure_group_burst' => ['enabled' => false],
            'scheduled_task_failed' => ['enabled' => false],
            'scheduled_task_late' => ['enabled' => false],
            'partial_completion' => ['enabled' => false],
        ]);

        // Only retry_storm remains from defaults.
=======
        ]);

        // Only retry_storm remains from defaults
>>>>>>> origin/main
        self::assertCount(1, $rules);
        self::assertSame(2, $rules[0]->minAttempt);
    }

    public function test_user_can_enable_a_built_in_that_was_off_by_default(): void
    {
        $provider = new BuiltInRulesProvider(new AlertRuleFactory);

        $rules = $provider->build([
            'dlq_growing' => ['enabled' => true],
        ]);

        $triggers = array_map(fn ($r) => $r->trigger, $rules);
        self::assertContains(AlertTrigger::DlqSize, $triggers);
    }

    public function test_user_can_override_threshold_on_a_built_in(): void
    {
        $provider = new BuiltInRulesProvider(new AlertRuleFactory);

        $rules = $provider->build([
            'critical_failure' => ['threshold' => 5],
        ]);

        $critical = $this->findByTrigger($rules, AlertTrigger::FailureCategory);
        self::assertNotNull($critical);
        self::assertSame(5, $critical->threshold);
    }

    public function test_user_can_override_channels(): void
    {
        $provider = new BuiltInRulesProvider(new AlertRuleFactory);

        $rules = $provider->build([
            'critical_failure' => ['channels' => ['mail']],
        ]);

        $critical = $this->findByTrigger($rules, AlertTrigger::FailureCategory);
        self::assertNotNull($critical);
        self::assertSame(['mail'], $critical->channels);
    }

    public function test_unknown_rule_id_in_overrides_is_ignored_silently(): void
    {
        $provider = new BuiltInRulesProvider(new AlertRuleFactory);

        // If host has a stale config key, we don't want to blow up.
        $rules = $provider->build([
            'not_a_real_rule' => ['enabled' => true, 'threshold' => 1],
        ]);

<<<<<<< HEAD
        self::assertCount(7, $rules); // still the defaults
=======
        self::assertCount(2, $rules); // still the defaults
>>>>>>> origin/main
    }

    /**
     * @param  list<\Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule>  $rules
     */
    private function findByTrigger(array $rules, AlertTrigger $trigger): ?\Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule
    {
        foreach ($rules as $r) {
            if ($r->trigger === $trigger) {
                return $r;
            }
        }

        return null;
    }

    /**
     * @param  list<\Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule>  $rules
     */
    private function findByTriggerAndMinAttempt(array $rules, AlertTrigger $trigger, int $minAttempt): ?\Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule
    {
        foreach ($rules as $r) {
            if ($r->trigger === $trigger && $r->minAttempt === $minAttempt) {
                return $r;
            }
        }

        return null;
    }
}
