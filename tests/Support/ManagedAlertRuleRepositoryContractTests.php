<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;

trait ManagedAlertRuleRepositoryContractTests
{
    abstract protected function createRuleRepository(): ManagedAlertRuleRepository;

    public function test_empty_repository_returns_empty_list(): void
    {
        self::assertSame([], $this->createRuleRepository()->all());
    }

    public function test_save_assigns_id_and_makes_rule_findable(): void
    {
        $repo = $this->createRuleRepository();

        $persisted = $repo->save($this->buildRule(key: 'critical'));

        self::assertNotNull($persisted->id());
        self::assertSame('critical', $persisted->key());

        $found = $repo->findById($persisted->id());
        self::assertNotNull($found);
        self::assertSame($persisted->id(), $found->id());
        self::assertSame('critical', $found->key());
    }

    public function test_find_by_key_returns_rule(): void
    {
        $repo = $this->createRuleRepository();
        $repo->save($this->buildRule(key: 'critical'));

        $found = $repo->findByKey('critical');

        self::assertNotNull($found);
        self::assertSame('critical', $found->key());
    }

    public function test_find_missing_returns_null(): void
    {
        $repo = $this->createRuleRepository();

        self::assertNull($repo->findById(999));
        self::assertNull($repo->findByKey('nonexistent'));
    }

    public function test_save_with_existing_key_updates_in_place(): void
    {
        $repo = $this->createRuleRepository();
        $first = $repo->save($this->buildRule(key: 'critical', threshold: 5));

        $updated = $repo->save(new ManagedAlertRule(
            id: null,
            key: 'critical',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureRate,
                window: '5m',
                threshold: 99,
                channels: ['mail'],
                cooldownMinutes: 30,
            ),
            enabled: true,
            overridesBuiltIn: null,
            position: 0,
        ));

        self::assertSame($first->id(), $updated->id());
        self::assertSame(99, $updated->rule()->threshold);
        self::assertSame(['mail'], $updated->rule()->channels);
        self::assertCount(1, $repo->all());
    }

    public function test_all_returns_rules_ordered_by_position(): void
    {
        $repo = $this->createRuleRepository();
        $repo->save($this->buildRule(key: 'a', position: 2));
        $repo->save($this->buildRule(key: 'b', position: 0));
        $repo->save($this->buildRule(key: 'c', position: 1));

        $keys = array_map(static fn (ManagedAlertRule $r): string => $r->key(), $repo->all());

        self::assertSame(['b', 'c', 'a'], $keys);
    }

    public function test_delete_removes_rule_and_returns_true(): void
    {
        $repo = $this->createRuleRepository();
        $persisted = $repo->save($this->buildRule(key: 'doomed'));

        self::assertTrue($repo->delete($persisted->id()));
        self::assertNull($repo->findById($persisted->id()));
    }

    public function test_delete_unknown_id_returns_false(): void
    {
        self::assertFalse($this->createRuleRepository()->delete(999));
    }

    public function test_round_trip_preserves_all_alert_rule_fields(): void
    {
        $repo = $this->createRuleRepository();
        $original = new ManagedAlertRule(
            id: null,
            key: 'cat_critical',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureCategory,
                window: '15m',
                threshold: 3,
                channels: ['slack', 'mail'],
                cooldownMinutes: 60,
                triggerValue: 'critical',
                minAttempt: 2,
            ),
            enabled: false,
            overridesBuiltIn: 'critical_failure',
            position: 5,
        );

        $persisted = $repo->save($original);
        $loaded = $repo->findById($persisted->id());

        self::assertNotNull($loaded);
        self::assertSame('cat_critical', $loaded->key());
        self::assertFalse($loaded->isEnabled());
        self::assertSame('critical_failure', $loaded->overridesBuiltIn());
        self::assertSame(5, $loaded->position());
        self::assertSame(AlertTrigger::FailureCategory, $loaded->rule()->trigger);
        self::assertSame('15m', $loaded->rule()->window);
        self::assertSame(3, $loaded->rule()->threshold);
        self::assertSame(['slack', 'mail'], $loaded->rule()->channels);
        self::assertSame(60, $loaded->rule()->cooldownMinutes);
        self::assertSame('critical', $loaded->rule()->triggerValue);
        self::assertSame(2, $loaded->rule()->minAttempt);
    }

    private function buildRule(
        string $key,
        int $threshold = 10,
        int $position = 0,
    ): ManagedAlertRule {
        return new ManagedAlertRule(
            id: null,
            key: $key,
            rule: new AlertRule(
                trigger: AlertTrigger::FailureRate,
                window: '5m',
                threshold: $threshold,
                channels: ['slack'],
                cooldownMinutes: 15,
            ),
            enabled: true,
            overridesBuiltIn: null,
            position: $position,
        );
    }
}
