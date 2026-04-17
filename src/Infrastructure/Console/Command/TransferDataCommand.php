<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console\Command;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use PDO;
use Yammi\JobsMonitor\Application\Action\TransferMonitorDataAction;
use Yammi\JobsMonitor\Infrastructure\Job\TransferMonitorDataJob;

final class TransferDataCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:transfer-data
                            {--from=         : Source connection name (defaults to Laravel default connection)}
                            {--to=           : Destination connection name (defaults to jobs-monitor.database.connection)}
                            {--delete-source : Drop source tables after a successful transfer}';

    /** @var string */
    protected $description = 'Move all jobs-monitor data between database connections.';

    public function handle(TransferMonitorDataAction $action, DatabaseManager $db): int
    {
        $from = $this->option('from') ?? config('database.default');
        $to = $this->option('to') ?? config('jobs-monitor.database.connection');

        if (! $to) {
            $this->error('No target connection. Set JOBS_MONITOR_DB_CONNECTION or pass --to=<connection>.');

            return self::FAILURE;
        }

        if ($from === $to) {
            $this->error("Source and destination are the same connection: \"{$from}\".");

            return self::FAILURE;
        }

        $lockPath = TransferMonitorDataJob::lockFilePath();
        $lock = fopen($lockPath, 'c');

        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->error('A transfer is already running. Wait for it to finish.');
            if ($lock) {
                fclose($lock);
            }

            return self::FAILURE;
        }

        try {
            return $this->runTransfer($action, $db, $from, $to);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lockPath);
        }
    }

    private function runTransfer(
        TransferMonitorDataAction $action,
        DatabaseManager $db,
        string $from,
        string $to,
    ): int {
        $this->info("Preparing destination connection \"{$to}\"...");
        $this->tryCreateDatabase($to);

        try {
            $db->connection($to)->getPdo();
        } catch (\Exception $e) {
            $this->error("Cannot connect to \"{$to}\": {$e->getMessage()}");
            $this->warn('Nothing was changed. Data stays on the current connection.');

            return self::FAILURE;
        }

        $migrationsPath = realpath(__DIR__.'/../../../../database/migrations');

        if ($migrationsPath === false) {
            $this->error('Package migrations directory not found.');

            return self::FAILURE;
        }

        $this->info('Running package migrations on destination...');

        $exitCode = $this->call('migrate', [
            '--database' => $to,
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            $this->error('Migration failed on destination. Nothing was transferred.');

            return self::FAILURE;
        }

        $deleteSource = (bool) $this->option('delete-source');

        $this->info(sprintf('Transferring data from "%s" to "%s"...', $from, $to));

        $result = $action($from, $to, $deleteSource);

        $this->info(sprintf(
            'Done. %d row(s) moved across %d table(s).',
            $result->rowsMoved,
            $result->tablesProcessed,
        ));

        if ($deleteSource) {
            $this->info("Source tables on \"{$from}\" have been dropped.");
        }

        return self::SUCCESS;
    }

    private function tryCreateDatabase(string $connectionName): void
    {
        /** @var array<string, mixed>|null $config */
        $config = config("database.connections.{$connectionName}");

        if (! $config) {
            return;
        }

        try {
            match ($config['driver']) {
                'mysql' => $this->createMysqlDatabase($config),
                'pgsql' => $this->createPgsqlDatabase($config),
                'sqlite' => $this->createSqliteDatabase($config),
                default => null,
            };
        } catch (\Exception $e) {
            $this->warn("Could not auto-create database (proceeding anyway): {$e->getMessage()}");
        }
    }

    /** @param array<string, mixed> $config */
    private function createMysqlDatabase(array $config): void
    {
        $name = str_replace('`', '', (string) $config['database']);
        $pdo = new PDO(
            "mysql:host={$config['host']};port={$config['port']}",
            (string) $config['username'],
            (string) $config['password'],
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    /** @param array<string, mixed> $config */
    private function createPgsqlDatabase(array $config): void
    {
        $pdo = new PDO(
            "pgsql:host={$config['host']};port={$config['port']};dbname=postgres",
            (string) $config['username'],
            (string) $config['password'],
        );
        $dbName = str_replace('"', '', (string) $config['database']);
        $stmt = $pdo->query('SELECT 1 FROM pg_database WHERE datname = '.$pdo->quote($dbName));
        $exists = $stmt !== false ? $stmt->fetch() : false;

        if (! $exists) {
            $pdo->exec("CREATE DATABASE \"{$dbName}\"");
        }
    }

    /** @param array<string, mixed> $config */
    private function createSqliteDatabase(array $config): void
    {
        $path = (string) $config['database'];

        if ($path !== ':memory:') {
            touch($path);
        }
    }
}
