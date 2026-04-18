<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;

/**
 * Curated set of pre-configured alert rules shipped with the package.
 *
 * The goal is a useful-out-of-the-box experience: hosts that just turn
 * alerts on get reasonable signal without writing any rule themselves.
 * Every built-in can be disabled, re-enabled, or overridden per-field
 * via the host's `jobs-monitor.alerts.built_in.<id>` config keys.
 */
final class BuiltInRulesProvider
{
    public function __construct(private readonly AlertRuleFactory $factory) {}

    /**
     * Build the effective rule list by merging user overrides into the
     * curated defaults. Unknown ids in overrides are ignored — that way
     * stale host configs don't blow up the package on upgrade.
     *
     * @param  array<string, array<string, mixed>>  $overrides  Keyed by rule id
     * @return list<AlertRule>
     */
    public function build(array $overrides): array
    {
        $rules = [];

        foreach ($this->catalog() as $id => $default) {
            $merged = array_replace($default, $overrides[$id] ?? []);

            if (($merged['enabled'] ?? false) !== true) {
                continue;
            }

            unset($merged['enabled']);
            $rules[] = $this->factory->fromArray($merged);
        }

        return $rules;
    }

    /**
     * Curated defaults. Modifying these ships new behaviour to hosts on
     * upgrade — be deliberate.
     *
     * @return array<string, array<string, mixed>>
     */
    public function catalog(): array
    {
        return [
            'critical_failure' => [
                'enabled' => true,
                'trigger' => 'failure_category',
                'value' => 'critical',
                'window' => '5m',
                'threshold' => 1,
                'cooldown_minutes' => 10,
                'channels' => ['slack', 'mail'],
            ],
            // Retry-aware: silence first-try failures, alert only when
            // jobs fail at least once AND then fail again on retry.
            'retry_storm' => [
                'enabled' => true,
                'trigger' => 'failure_rate',
                'window' => '10m',
                'threshold' => 5,
                'min_attempt' => 2,
                'cooldown_minutes' => 15,
                'channels' => ['slack'],
            ],
            // Off by default — hosts tune threshold to their baseline
            // before enabling. Raw failure_rate is noisy on busy queues.
            'high_failure_rate' => [
                'enabled' => false,
                'trigger' => 'failure_rate',
                'window' => '5m',
                'threshold' => 20,
                'cooldown_minutes' => 15,
                'channels' => ['slack'],
            ],
            'dlq_growing' => [
                'enabled' => false,
                'trigger' => 'dlq_size',
                'threshold' => 10,
                'cooldown_minutes' => 30,
                'channels' => ['slack', 'mail'],
            ],
<<<<<<< HEAD
            // New failure group = a signature never seen before in the window.
            // Flags "something new is broken" vs. the usual suspects flaking.
            'new_failure_group' => [
                'enabled' => true,
                'trigger' => 'failure_group_new',
                'window' => '15m',
                'threshold' => 1,
                'cooldown_minutes' => 15,
                'channels' => ['slack'],
            ],
            // Per-group burst: a known fingerprint suddenly accumulates
            // failures fast. Emits one alert per bursting group with its
            // own throttle window so a chronically noisy group does not
            // silence quieter ones.
            'failure_group_burst' => [
                'enabled' => true,
                'trigger' => 'failure_group_burst',
                'window' => '5m',
                'threshold' => 5,
                'cooldown_minutes' => 15,
                'channels' => ['slack'],
            ],
            // Any scheduled-task failure is worth a heads-up — these are
            // usually crons that nobody checks until they break something.
            'scheduled_task_failed' => [
                'enabled' => true,
                'trigger' => 'scheduled_task_failed',
                'window' => '15m',
                'threshold' => 1,
                'cooldown_minutes' => 30,
                'channels' => ['slack', 'mail'],
            ],
            // Late = stayed in Running past the watchdog tolerance. Worker
            // crash, host died, or command hung. Always actionable.
            'scheduled_task_late' => [
                'enabled' => true,
                'trigger' => 'scheduled_task_late',
                'window' => '15m',
                'threshold' => 1,
                'cooldown_minutes' => 30,
                'channels' => ['slack', 'mail'],
            ],
            // Off by default — wait for baselines to stabilize before
            // enabling. Tuned conservatively (5 in 30m) to fight noise.
            'duration_anomaly' => [
                'enabled' => false,
                'trigger' => 'duration_anomaly',
                'window' => '30m',
                'threshold' => 5,
                'cooldown_minutes' => 15,
                'channels' => ['slack'],
            ],
            // Partial completion = job failed after non-zero progress.
            // Important because retry semantics differ.
            'partial_completion' => [
                'enabled' => true,
                'trigger' => 'partial_completion',
                'window' => '30m',
                'threshold' => 1,
                'cooldown_minutes' => 30,
                'channels' => ['slack'],
            ],
            // Job returned OK but processed=0. Off until host opts in to
            // ReportsOutcome — otherwise outcome_processed is null/missing.
            'zero_processed' => [
                'enabled' => false,
                'trigger' => 'zero_processed',
                'window' => '30m',
                'threshold' => 3,
                'cooldown_minutes' => 30,
                'channels' => ['slack'],
            ],
=======
>>>>>>> origin/main
        ];
    }
}
