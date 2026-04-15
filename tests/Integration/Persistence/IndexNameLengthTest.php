<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Persistence;

use Illuminate\Support\Facades\DB;
use Yammi\JobsMonitor\Tests\TestCase;

/**
 * Regression guard: every index created by the package must fit the safest
 * cross-database identifier limit (PostgreSQL: 63, MySQL: 64). We use 63 so
 * the package stays portable. Auto-generated Laravel names can exceed it for
 * composite indexes on long table names — such indexes must be given an
 * explicit shorter name in the migration.
 */
final class IndexNameLengthTest extends TestCase
{
    private const MAX_IDENTIFIER_LENGTH = 63;

    private const PACKAGE_TABLES = [
        'jobs_monitor',
        'jobs_monitor_alert_settings',
        'jobs_monitor_alert_mail_recipients',
        'jobs_monitor_alert_rules',
        'jobs_monitor_alert_rule_channels',
        'jobs_monitor_built_in_rule_state',
        'jobs_monitor_failure_groups',
    ];

    public function test_all_package_index_names_fit_portable_identifier_limit(): void
    {
        foreach (self::PACKAGE_TABLES as $table) {
            $indexes = DB::select(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = ?",
                [$table]
            );

            foreach ($indexes as $index) {
                if (str_starts_with($index->name, 'sqlite_autoindex_')) {
                    continue;
                }

                self::assertLessThanOrEqual(
                    self::MAX_IDENTIFIER_LENGTH,
                    strlen($index->name),
                    sprintf(
                        'Index "%s" on table "%s" is %d characters — exceeds the 63-char portable limit.',
                        $index->name,
                        $table,
                        strlen($index->name),
                    )
                );
            }
        }
    }
}
