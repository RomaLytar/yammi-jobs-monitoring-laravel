<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs jobs-monitor:transfer-data in a queue job so that large datasets
 * (millions of rows) do not block the HTTP request.
 *
 * Status is persisted to a JSON file in storage/app so the controller and
 * the polling endpoint can expose progress without a dedicated DB table.
 *
 * @internal
 */
final class TransferMonitorDataJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries   = 1;
    public int $timeout = 3600;

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly bool   $deleteSource,
    ) {}

    public function handle(ConsoleKernel $artisan): void
    {
        set_time_limit(0);

        self::writeStatus(['status' => 'running', 'from' => $this->from, 'to' => $this->to]);

        $args = ['--from' => $this->from, '--to' => $this->to];

        if ($this->deleteSource) {
            $args['--delete-source'] = true;
        }

        try {
            $exitCode = $artisan->call('jobs-monitor:transfer-data', $args);

            if ($exitCode !== 0) {
                $output = trim($artisan->output());
                self::writeStatus([
                    'status' => 'failed',
                    'error'  => $output !== '' ? $output : 'Transfer failed. Check application logs for details.',
                ]);

                return;
            }

            self::writeStatus(['status' => 'done', 'from' => $this->from, 'to' => $this->to]);
        } catch (\Throwable $e) {
            self::writeStatus([
                'status' => 'failed',
                'error'  => $e->getMessage() !== '' ? $e->getMessage() : 'Unexpected error during transfer. Check application logs for details.',
            ]);

            throw $e;
        }
    }

    public static function statusFilePath(): string
    {
        return storage_path('app/.jobs-monitor-transfer-status.json');
    }

    /** @return array<string, mixed> */
    public static function readStatus(): array
    {
        $path = self::statusFilePath();

        if (! file_exists($path)) {
            return ['status' => 'idle'];
        }

        $raw  = file_get_contents($path);
        $data = $raw !== false ? json_decode($raw, true) : null;

        return is_array($data) ? $data : ['status' => 'idle'];
    }

    /** @param array<string, mixed> $data */
    public static function writeStatus(array $data): void
    {
        $encoded = json_encode($data);

        if ($encoded !== false) {
            file_put_contents(self::statusFilePath(), $encoded);
        }
    }

    public static function clearStatus(): void
    {
        $path = self::statusFilePath();

        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
