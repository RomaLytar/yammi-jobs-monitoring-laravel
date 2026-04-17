<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Application\Action;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Yammi\JobsMonitor\Application\Action\TransferMonitorDataAction;
use Yammi\JobsMonitor\Application\DTO\TransferResultData;
use Yammi\JobsMonitor\Tests\TestCase;

final class TransferMonitorDataActionTest extends TestCase
{
    private string $altDbPath;

    protected function setUp(): void
    {
        $this->altDbPath = sys_get_temp_dir() . '/jm_action_test_' . uniqid() . '.sqlite';
        parent::setUp();
        $this->seedDestination();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->altDbPath)) {
            unlink($this->altDbPath);
        }
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.jm_alt', [
            'driver'   => 'sqlite',
            'database' => $this->altDbPath,
            'prefix'   => '',
        ]);
    }

    public function test_returns_transfer_result_data(): void
    {
        $result = $this->invoke(from: 'testing', to: 'jm_alt', deleteSource: false);

        self::assertInstanceOf(TransferResultData::class, $result);
        self::assertSame(12, $result->tablesProcessed);
    }

    public function test_copies_rows_to_destination(): void
    {
        DB::table('jobs_monitor')->insert([
            'uuid'       => 'aaaaaaaa-0000-0000-0000-aaaaaaaaaaaa',
            'job_class'  => 'App\\Jobs\\TestJob',
            'connection' => 'sync',
            'queue'      => 'default',
            'status'     => 'completed',
            'attempt'    => 1,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->invoke(from: 'testing', to: 'jm_alt', deleteSource: false);

        self::assertSame(1, $result->rowsMoved);
        self::assertSame(1, DB::connection('jm_alt')->table('jobs_monitor')->count());
    }

    public function test_insertOrIgnore_skips_duplicate_rows(): void
    {
        DB::table('jobs_monitor')->insert([
            'uuid'       => 'bbbbbbbb-0000-0000-0000-bbbbbbbbbbbb',
            'job_class'  => 'App\\Jobs\\TestJob',
            'connection' => 'sync',
            'queue'      => 'default',
            'status'     => 'completed',
            'attempt'    => 1,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->invoke(from: 'testing', to: 'jm_alt', deleteSource: false);
        $this->invoke(from: 'testing', to: 'jm_alt', deleteSource: false);

        self::assertSame(1, DB::connection('jm_alt')->table('jobs_monitor')->count());
    }

    public function test_drops_source_tables_when_delete_source_is_true(): void
    {
        $this->invoke(from: 'testing', to: 'jm_alt', deleteSource: true);

        self::assertFalse(
            DB::connection('testing')->getSchemaBuilder()->hasTable('jobs_monitor'),
        );
    }

    public function test_does_not_drop_source_tables_when_delete_source_is_false(): void
    {
        $this->invoke(from: 'testing', to: 'jm_alt', deleteSource: false);

        self::assertTrue(
            DB::connection('testing')->getSchemaBuilder()->hasTable('jobs_monitor'),
        );
    }

    public function test_removes_migration_records_from_source_when_delete_source_is_true(): void
    {
        $this->invoke(from: 'testing', to: 'jm_alt', deleteSource: true);

        $remaining = DB::connection('testing')
            ->table('migrations')
            ->where('migration', 'like', '%jobs_monitor%')
            ->count();

        self::assertSame(0, $remaining);
    }

    public function test_does_not_remove_migration_records_when_delete_source_is_false(): void
    {
        $before = DB::connection('testing')
            ->table('migrations')
            ->where('migration', 'like', '%jobs_monitor%')
            ->count();

        $this->invoke(from: 'testing', to: 'jm_alt', deleteSource: false);

        $after = DB::connection('testing')
            ->table('migrations')
            ->where('migration', 'like', '%jobs_monitor%')
            ->count();

        self::assertSame($before, $after);
    }

    public function test_row_count_matches_rows_moved(): void
    {
        DB::table('jobs_monitor')->insert([
            ['uuid' => 'cccccccc-0000-0000-0000-000000000001', 'job_class' => 'A', 'connection' => 's', 'queue' => 'q', 'status' => 'completed', 'attempt' => 1, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => 'cccccccc-0000-0000-0000-000000000002', 'job_class' => 'A', 'connection' => 's', 'queue' => 'q', 'status' => 'completed', 'attempt' => 1, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => 'cccccccc-0000-0000-0000-000000000003', 'job_class' => 'A', 'connection' => 's', 'queue' => 'q', 'status' => 'completed', 'attempt' => 1, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $result = $this->invoke(from: 'testing', to: 'jm_alt', deleteSource: false);

        self::assertSame(3, $result->rowsMoved);
    }

    private function seedDestination(): void
    {
        touch($this->altDbPath);

        Artisan::call('migrate', [
            '--database' => 'jm_alt',
            '--path'     => realpath(__DIR__ . '/../../../../database/migrations'),
            '--realpath' => true,
            '--force'    => true,
        ]);
    }

    private function invoke(string $from, string $to, bool $deleteSource): TransferResultData
    {
        return ($this->app->make(TransferMonitorDataAction::class))($from, $to, $deleteSource);
    }
}
