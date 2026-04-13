<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\UpdateBuiltInRuleAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;

final class UpdateBuiltInRuleActionTest extends TestCase
{
    public function test_creates_new_override_when_none_exists(): void
    {
        $repo = new InMemoryManagedAlertRuleRepository;
        $action = new UpdateBuiltInRuleAction($repo);

        $persisted = $action('critical_failure', $this->rule(threshold: 10), true);

        self::assertNotNull($persisted->id());
        self::assertSame('critical_failure', $persisted->overridesBuiltIn());
        self::assertTrue($persisted->isEnabled());
        self::assertSame(10, $persisted->rule()->threshold);
    }

    public function test_updates_existing_override_in_place(): void
    {
        $repo = new InMemoryManagedAlertRuleRepository;
        $initial = $repo->save(new ManagedAlertRule(
            id: null,
            key: 'built_in_override_critical_failure',
            rule: $this->rule(threshold: 5),
            enabled: true,
            overridesBuiltIn: 'critical_failure',
            position: 0,
        ));

        $action = new UpdateBuiltInRuleAction($repo);
        $updated = $action('critical_failure', $this->rule(threshold: 77), false);

        self::assertSame($initial->id(), $updated->id());
        self::assertSame(77, $updated->rule()->threshold);
        self::assertFalse($updated->isEnabled());
        self::assertCount(1, $repo->all(), 'no new rule was created');
    }

    private function rule(int $threshold): AlertRule
    {
        return new AlertRule(
            trigger: AlertTrigger::FailureCategory,
            window: '5m',
            threshold: $threshold,
            channels: ['slack', 'mail'],
            cooldownMinutes: 15,
            triggerValue: 'critical',
        );
    }
}
