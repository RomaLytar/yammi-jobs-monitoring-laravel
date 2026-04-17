<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Playground;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Playground\ArgumentType;
use Yammi\JobsMonitor\Application\Playground\MethodCatalog;
use Yammi\JobsMonitor\Application\Playground\PlaygroundMethod;

final class MethodCatalogTest extends TestCase
{
    private MethodCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new MethodCatalog;
    }

    public function test_all_returns_non_empty_list_of_methods(): void
    {
        $methods = $this->catalog->all();

        self::assertNotEmpty($methods);
        self::assertContainsOnlyInstancesOf(PlaygroundMethod::class, $methods);
    }

    public function test_all_methods_have_unique_keys(): void
    {
        $keys = array_map(static fn (PlaygroundMethod $m) => $m->key, $this->catalog->all());

        self::assertSame(array_values(array_unique($keys)), array_values($keys));
    }

    public function test_find_returns_method_by_key(): void
    {
        $method = $this->catalog->find('YammiJobs::failed');

        self::assertNotNull($method);
        self::assertSame('failed', $method->method);
        self::assertSame('YammiJobs', $method->facade);
        self::assertFalse($method->destructive);
    }

    public function test_find_returns_null_for_unknown_key(): void
    {
        self::assertNull($this->catalog->find('YammiJobs::nuke_world'));
    }

    public function test_destructive_methods_are_flagged(): void
    {
        $retry = $this->catalog->find('YammiJobsManage::retryDlq');
        $delete = $this->catalog->find('YammiJobsManage::deleteDlq');

        self::assertNotNull($retry);
        self::assertTrue($retry->destructive);

        self::assertNotNull($delete);
        self::assertTrue($delete->destructive);
    }

    public function test_read_methods_are_not_destructive(): void
    {
        $jobs = $this->catalog->find('YammiJobs::jobs');

        self::assertNotNull($jobs);
        self::assertFalse($jobs->destructive);
    }

    public function test_method_has_argument_schema(): void
    {
        $failed = $this->catalog->find('YammiJobs::failed');

        self::assertNotNull($failed);
        self::assertNotEmpty($failed->arguments);

        $period = $failed->arguments[0];
        self::assertSame('period', $period->name);
        self::assertSame(ArgumentType::Period, $period->type);
        self::assertFalse($period->required);
        self::assertSame('all', $period->default);
    }

    public function test_method_has_description(): void
    {
        $failed = $this->catalog->find('YammiJobs::failed');

        self::assertNotNull($failed);
        self::assertNotSame('', $failed->description);
    }

    public function test_covers_all_three_facades(): void
    {
        $facades = array_unique(array_map(
            static fn (PlaygroundMethod $m) => $m->facade,
            $this->catalog->all(),
        ));

        sort($facades);
        self::assertSame(['YammiJobs', 'YammiJobsManage', 'YammiJobsSettings'], $facades);
    }

    public function test_grouped_returns_methods_by_facade(): void
    {
        $grouped = $this->catalog->grouped();

        self::assertArrayHasKey('YammiJobs', $grouped);
        self::assertArrayHasKey('YammiJobsManage', $grouped);
        self::assertArrayHasKey('YammiJobsSettings', $grouped);

        foreach ($grouped as $methods) {
            self::assertNotEmpty($methods);
            self::assertContainsOnlyInstancesOf(PlaygroundMethod::class, $methods);
        }
    }
}
