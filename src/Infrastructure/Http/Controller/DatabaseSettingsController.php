<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Yammi\JobsMonitor\Application\DTO\DatabaseConnectionStatusData;
use Yammi\JobsMonitor\Infrastructure\Http\Authorization\SettingsGate;
use Yammi\JobsMonitor\Infrastructure\Job\TransferMonitorDataJob;

/** @internal */
final class DatabaseSettingsController extends Controller
{
    public function __construct(
        private readonly ConsoleKernel $artisan,
        private readonly ConnectionResolverInterface $db,
    ) {}

    public function index(SettingsGate $gate): View
    {
        $gate->authorize();

        $transferStatus = TransferMonitorDataJob::readStatus();
        $transferDone = null;
        $transferFailed = null;
        $transferPending = null;

        if ($transferStatus['status'] === 'done') {
            TransferMonitorDataJob::clearStatus();
            $transferDone = 'Data transferred from '.($transferStatus['from'] ?? '').' to '.($transferStatus['to'] ?? '').' successfully.';
        } elseif ($transferStatus['status'] === 'failed') {
            TransferMonitorDataJob::clearStatus();
            $transferFailed = $transferStatus['error'] ?? 'Transfer failed. Check application logs.';
        } elseif (in_array($transferStatus['status'], ['pending', 'running'], true)) {
            $transferPending = $transferStatus;
        }

        $defaultName = config('database.default');
        $monitorName = config('jobs-monitor.database.connection');

        return view('jobs-monitor::settings.database.index', [
            'defaultStatus' => $this->connectionStatus((string) $defaultName),
            'monitorStatus' => $monitorName !== null
                ? $this->connectionStatus((string) $monitorName)
                : null,
            'transferDone' => $transferDone,
            'transferFailed' => $transferFailed,
            'transferPending' => $transferPending,
        ]);
    }

    public function setup(SettingsGate $gate): RedirectResponse
    {
        $gate->authorize();

        if ($this->transferAlreadyRunning()) {
            return back()->with('jobs_monitor_error', 'A transfer is already in progress. Please wait for it to finish.');
        }

        $from = (string) config('database.default');
        $to = (string) config('jobs-monitor.database.connection');

        TransferMonitorDataJob::writeStatus(['status' => 'pending', 'from' => $from, 'to' => $to]);
        dispatch(new TransferMonitorDataJob($from, $to, false));

        $status = TransferMonitorDataJob::readStatus();

        if ($status['status'] === 'done') {
            TransferMonitorDataJob::clearStatus();

            return redirect()
                ->route('jobs-monitor.dashboard')
                ->with('jobs_monitor_status', 'Monitor database set up successfully.');
        }

        if ($status['status'] === 'failed') {
            TransferMonitorDataJob::clearStatus();

            return back()->with(
                'jobs_monitor_error',
                $status['error'] ?? 'Setup failed. Check application logs for details.',
            );
        }

        return redirect()->route('jobs-monitor.settings.database');
    }

    public function transfer(SettingsGate $gate, Request $request): RedirectResponse
    {
        $gate->authorize();

        $validated = $request->validate([
            'from' => 'required|string',
            'to' => 'required|string|different:from',
        ]);

        if ($this->transferAlreadyRunning()) {
            return back()->with('jobs_monitor_error', 'A transfer is already in progress. Please wait for it to finish.');
        }

        $from = $validated['from'];
        $to = $validated['to'];
        $deleteSource = $request->boolean('delete_source');

        TransferMonitorDataJob::writeStatus(['status' => 'pending', 'from' => $from, 'to' => $to]);
        dispatch(new TransferMonitorDataJob($from, $to, $deleteSource));

        $status = TransferMonitorDataJob::readStatus();

        if ($status['status'] === 'done') {
            TransferMonitorDataJob::clearStatus();

            return redirect()
                ->route('jobs-monitor.settings.database')
                ->with('jobs_monitor_status', 'Data transferred successfully.');
        }

        if ($status['status'] === 'failed') {
            TransferMonitorDataJob::clearStatus();

            return back()->with(
                'jobs_monitor_error',
                $status['error'] ?? 'Transfer failed. Check application logs for details.',
            );
        }

        return redirect()->route('jobs-monitor.settings.database');
    }

    public function runMigrations(SettingsGate $gate, Request $request): RedirectResponse
    {
        $gate->authorize();

        if ($this->transferAlreadyRunning()) {
            return back()->with('jobs_monitor_error', 'A transfer is already in progress. Please wait for it to finish.');
        }

        $from = (string) config('database.default');
        $to = (string) (config('jobs-monitor.database.connection') ?? $from);

        if ($to !== $from) {
            TransferMonitorDataJob::writeStatus(['status' => 'pending', 'from' => $from, 'to' => $to]);
            dispatch(new TransferMonitorDataJob($from, $to, true));

            $status = TransferMonitorDataJob::readStatus();

            if ($status['status'] === 'done') {
                TransferMonitorDataJob::clearStatus();

                return redirect()
                    ->route('jobs-monitor.settings.database')
                    ->with('jobs_monitor_status', 'Migrations applied and data transferred successfully.');
            }

            if ($status['status'] === 'failed') {
                TransferMonitorDataJob::clearStatus();

                return back()->with(
                    'jobs_monitor_error',
                    $status['error'] ?? 'Migration failed. Check application logs for details.',
                );
            }

            return redirect()->route('jobs-monitor.settings.database');
        }

        $this->clearStaleMigrationRecords($to);

        $exitCode = $this->callMigrateArtisan($to);

        if ($exitCode !== 0) {
            return back()->with('jobs_monitor_error', 'Migration failed. Check application logs for details.');
        }

        return redirect()
            ->route('jobs-monitor.settings.database')
            ->with('jobs_monitor_status', 'Migrations applied successfully.');
    }

    public function transferStatus(SettingsGate $gate): JsonResponse
    {
        $gate->authorize();

        return response()->json(TransferMonitorDataJob::readStatus());
    }

    private function transferAlreadyRunning(): bool
    {
        $status = TransferMonitorDataJob::readStatus()['status'] ?? 'idle';

        return in_array($status, ['pending', 'running'], true);
    }

    private function clearStaleMigrationRecords(string $conn): void
    {
        try {
            /** @var \Illuminate\Database\Connection $connection */
            $connection = $this->db->connection($conn);
            if (
                $connection->getSchemaBuilder()->hasTable('migrations') &&
                ! $connection->getSchemaBuilder()->hasTable('jobs_monitor')
            ) {
                $connection->table('migrations')
                    ->where('migration', 'like', '%jobs_monitor%')
                    ->delete();
            }
        } catch (\Exception) {
            // Non-fatal: migrate will surface a clearer error if the connection is broken.
        }
    }

    private function callMigrateArtisan(string $connection): int
    {
        $migrationsPath = realpath(__DIR__.'/../../../../database/migrations');

        if ($migrationsPath === false) {
            return 1;
        }

        return $this->artisan->call('migrate', [
            '--database' => $connection,
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    private function connectionStatus(string $name): DatabaseConnectionStatusData
    {
        /** @var array<string, mixed>|null $config */
        $config = config("database.connections.{$name}");

        $driver = (string) ($config['driver'] ?? 'unknown');
        $database = match ($driver) {
            'sqlite' => basename((string) ($config['database'] ?? 'unknown')),
            default => (string) ($config['database'] ?? 'unknown'),
        };

        try {
            $db = DB::connection($name);
            $db->getPdo();

            $migrated = $db->getSchemaBuilder()->hasTable('jobs_monitor');
            $rowCount = $migrated ? (int) $db->table('jobs_monitor')->count() : 0;

            return new DatabaseConnectionStatusData(
                name: $name,
                driver: $driver,
                database: $database,
                reachable: true,
                migrated: $migrated,
                rowCount: $rowCount,
            );
        } catch (\Exception) {
            return new DatabaseConnectionStatusData(
                name: $name,
                driver: $driver,
                database: $database,
                reachable: false,
                migrated: false,
                rowCount: 0,
            );
        }
    }
}
