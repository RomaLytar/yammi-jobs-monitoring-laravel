<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;

trait BuiltInRuleStateRepositoryContractTests
{
    abstract protected function createBuiltInStateRepository(): BuiltInRuleStateRepository;

    public function test_unknown_key_returns_null(): void
    {
        self::assertNull($this->createBuiltInStateRepository()->findEnabled('unknown'));
    }

    public function test_set_enabled_then_find_returns_value(): void
    {
        $repo = $this->createBuiltInStateRepository();

        $repo->setEnabled('critical_failure', true);
        self::assertTrue($repo->findEnabled('critical_failure'));

        $repo->setEnabled('retry_storm', false);
        self::assertFalse($repo->findEnabled('retry_storm'));
    }

    public function test_set_enabled_overwrites_previous_value(): void
    {
        $repo = $this->createBuiltInStateRepository();

        $repo->setEnabled('critical_failure', true);
        $repo->setEnabled('critical_failure', false);

        self::assertFalse($repo->findEnabled('critical_failure'));
    }

    public function test_clear_removes_override(): void
    {
        $repo = $this->createBuiltInStateRepository();
        $repo->setEnabled('critical_failure', false);

        $repo->clear('critical_failure');

        self::assertNull($repo->findEnabled('critical_failure'));
    }

    public function test_clear_unknown_key_is_noop(): void
    {
        $repo = $this->createBuiltInStateRepository();

        $repo->clear('unknown');

        self::assertNull($repo->findEnabled('unknown'));
    }

    public function test_all_returns_full_state_map(): void
    {
        $repo = $this->createBuiltInStateRepository();
        $repo->setEnabled('critical_failure', true);
        $repo->setEnabled('retry_storm', false);
        $repo->setEnabled('dlq_growing', true);

        $all = $repo->all();

        self::assertSame(
            ['critical_failure' => true, 'dlq_growing' => true, 'retry_storm' => false],
            $this->sortMap($all),
        );
    }

    public function test_all_on_empty_repo_returns_empty_array(): void
    {
        self::assertSame([], $this->createBuiltInStateRepository()->all());
    }

    /**
     * @param  array<string, bool>  $map
     * @return array<string, bool>
     */
    private function sortMap(array $map): array
    {
        ksort($map);

        return $map;
    }
}
