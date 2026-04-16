<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\DeleteManagedAlertRuleAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;

final class DeleteManagedAlertRuleActionTest extends TestCase
{
    public function test_returns_true_when_rule_exists(): void
    {
        $repo = new InMemoryManagedAlertRuleRepository;
        $saved = $repo->save($this->makeRule('test-rule-1'));

        $action = new DeleteManagedAlertRuleAction($repo);

        $result = $action((int) $saved->id());

        self::assertTrue($result);
        self::assertNull($repo->findById((int) $saved->id()));
    }

    public function test_returns_false_when_rule_does_not_exist(): void
    {
        $repo = new InMemoryManagedAlertRuleRepository;

        $action = new DeleteManagedAlertRuleAction($repo);

        $result = $action(999);

        self::assertFalse($result);
    }

    public function test_does_not_affect_other_rules(): void
    {
        $repo = new InMemoryManagedAlertRuleRepository;
        $first = $repo->save($this->makeRule('keep-me'));
        $second = $repo->save($this->makeRule('delete-me'));

        $action = new DeleteManagedAlertRuleAction($repo);

        $action((int) $second->id());

        self::assertNotNull($repo->findById((int) $first->id()));
        self::assertNull($repo->findById((int) $second->id()));
    }

    private function makeRule(string $key): ManagedAlertRule
    {
        return new ManagedAlertRule(
            id: null,
            key: $key,
            rule: new AlertRule(
                trigger: AlertTrigger::FailureRate,
                window: '5m',
                threshold: 10,
                channels: ['slack'],
                cooldownMinutes: 15,
            ),
            enabled: true,
            overridesBuiltIn: null,
            position: 0,
        );
    }
}
