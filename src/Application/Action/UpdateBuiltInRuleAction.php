<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;

/**
 * Persists user-supplied field overrides for a built-in alert rule.
 *
 * Stored as a ManagedAlertRule whose `overridesBuiltIn` points at the
 * built-in key — the resolver suppresses the built-in's code defaults
 * and uses the managed rule instead. An existing override is updated
 * in place; otherwise a fresh one is created with a stable key derived
 * from the built-in key.
 */
final class UpdateBuiltInRuleAction
{
    private const OVERRIDE_KEY_PREFIX = 'built_in_override_';

    public function __construct(
        private readonly ManagedAlertRuleRepository $repo,
    ) {}

    public function __invoke(string $builtInKey, AlertRule $rule, bool $enabled): ManagedAlertRule
    {
        $existing = $this->repo->findOverrideFor($builtInKey);

        $entity = new ManagedAlertRule(
            id: $existing?->id(),
            key: $existing?->key() ?? self::OVERRIDE_KEY_PREFIX.$builtInKey,
            rule: $rule,
            enabled: $enabled,
            overridesBuiltIn: $builtInKey,
            position: $existing?->position() ?? 0,
        );

        return $this->repo->save($entity);
    }
}
