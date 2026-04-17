<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Illuminate\Database\ConnectionResolverInterface;
use Yammi\JobsMonitor\Application\DTO\TransferResultData;

final class TransferMonitorDataAction
{
    /**
     * Insert order: parents before children so insertOrIgnore never hits a missing FK row.
     * DROP order is reversed — see __invoke.
     *
     * @var list<string>
     */
    private const TABLES = [
        'jobs_monitor_settings',
        'jobs_monitor_alert_settings',
        'jobs_monitor_alert_mail_recipients',
        'jobs_monitor_built_in_rule_state',
        'jobs_monitor_alert_rules',
        'jobs_monitor_alert_rule_channels',
        'jobs_monitor_failure_groups',
        'jobs_monitor',
        'jobs_monitor_scheduled_runs',
        'jobs_monitor_duration_baselines',
        'jobs_monitor_duration_anomalies',
        'jobs_monitor_worker_heartbeats',
    ];

    public function __construct(
        private readonly ConnectionResolverInterface $db,
    ) {}

    public function __invoke(string $from, string $to, bool $deleteSource): TransferResultData
    {
        $rowsMoved = 0;

        foreach (self::TABLES as $table) {
            $this->db->connection($from)
                ->table($table)
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($to, $table, &$rowsMoved): void {
                    $data = array_map(static fn ($row) => (array) $row, $rows->all());

                    if ($data === []) {
                        return;
                    }

                    $this->db->connection($to)->table($table)->insertOrIgnore($data);
                    $rowsMoved += count($data);
                });
        }

        if ($deleteSource) {
            /** @var \Illuminate\Database\Connection $fromConn */
            $fromConn = $this->db->connection($from);
            $grammar = $fromConn->getQueryGrammar();

            foreach (array_reverse(self::TABLES) as $table) {
                $fromConn->statement('DROP TABLE IF EXISTS '.$grammar->wrapTable($table));
            }

            // Remove migration records so the tables can be re-created if data is ever transferred back.
            if ($fromConn->getSchemaBuilder()->hasTable('migrations')) {
                $fromConn->table('migrations')
                    ->where('migration', 'like', '%jobs_monitor%')
                    ->delete();
            }
        }

        return new TransferResultData(
            rowsMoved: $rowsMoved,
            tablesProcessed: count(self::TABLES),
        );
    }
}
