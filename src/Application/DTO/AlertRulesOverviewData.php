<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;

/**
 * Combined view of every alert rule the system knows about.
 *
 * Built-ins ship with the package; user rules live in the DB. The
 * resolver merges them at runtime — this DTO just exposes both lists
 * so the UI can render a single screen showing what is currently in
 * effect.
 */
final class AlertRulesOverviewData
{
    /**
     * @param  list<BuiltInRuleData>  $builtInRules
     * @param  list<ManagedAlertRule>  $userRules
     */
    public function __construct(
        public readonly array $builtInRules,
        public readonly array $userRules,
    ) {}
}
