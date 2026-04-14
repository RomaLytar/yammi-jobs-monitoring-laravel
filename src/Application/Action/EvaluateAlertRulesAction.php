<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;
use Yammi\JobsMonitor\Application\Service\AlertConfigResolver;
use Yammi\JobsMonitor\Application\Service\AlertRuleEvaluator;
use Yammi\JobsMonitor\Domain\Alert\Contract\AlertThrottle;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;

/**
 * Runs the configured alert rules once and dispatches any that match.
 *
 * Orchestrates four collaborators:
 *  1. AlertConfigResolver — produces the effective ruleset every tick
 *     (DB > config > built-in defaults). Returning `enabled: false`
 *     short-circuits the entire evaluation.
 *  2. AlertRuleEvaluator — decides if a single rule is currently tripped.
 *  3. AlertThrottle — blocks duplicate dispatches inside the cooldown.
 *  4. SendAlertAction — routes the payload to the configured channels.
 *
 * Fail-closed: an exception from any single rule is logged and does
 * not abort the loop. The host job path is never affected by alert
 * delivery errors.
 */
final class EvaluateAlertRulesAction
{
    public function __construct(
        private readonly AlertRuleEvaluator $evaluator,
        private readonly SendAlertAction $send,
        private readonly AlertThrottle $throttle,
        private readonly LoggerInterface $logger,
        private readonly AlertConfigResolver $resolver,
    ) {}

    public function __invoke(DateTimeImmutable $now): void
    {
        $config = $this->resolver->resolve();

        if (! $config->enabled) {
            return;
        }

        foreach ($config->rules as $rule) {
            $this->processRule($rule, $now);
        }
    }

    private function processRule(AlertRule $rule, DateTimeImmutable $now): void
    {
        try {
            $payload = $this->evaluator->evaluate($rule, $now);

            if ($payload === null) {
                return;
            }

            if (! $this->throttle->attempt($rule->ruleKey(), $rule->cooldownMinutes)) {
                return;
            }

            ($this->send)($payload, $rule->channels);
        } catch (Throwable $e) {
            $this->logRuleFailure($rule, $e);
        }
    }

    private function logRuleFailure(AlertRule $rule, Throwable $e): void
    {
        $this->logger->error(
            sprintf(
                '[jobs-monitor] Alert rule "%s" evaluation failed: %s',
                $rule->ruleKey(),
                $e->getMessage(),
            ),
            ['rule' => $rule->ruleKey(), 'exception' => $e::class],
        );
    }
}
