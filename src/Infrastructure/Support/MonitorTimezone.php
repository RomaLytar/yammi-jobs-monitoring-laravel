<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Support;

use DateTimeZone;
use Yammi\JobsMonitor\Application\Contract\ConfigReader;

/**
 * Resolves the timezone used to bucket and label time-series data.
 *
 * Job timestamps are persisted as application-local wall-clock time, so the
 * chart window and bucket labels must be built in that same zone — otherwise
 * an app running ahead of UTC renders an empty chart. The zone comes from
 * `jobs-monitor.timezone` (which defaults to `config('app.timezone')`); an
 * empty or invalid value falls back to UTC so a typo never throws.
 *
 * @internal
 */
final class MonitorTimezone
{
    public function __construct(private readonly ConfigReader $config) {}

    public function toDateTimeZone(): DateTimeZone
    {
        return new DateTimeZone($this->name());
    }

    public function name(): string
    {
        $configured = $this->config->get('jobs-monitor.timezone');

        if (is_string($configured) && $configured !== '' && $this->isValid($configured)) {
            return $configured;
        }

        return 'UTC';
    }

    private function isValid(string $timezone): bool
    {
        try {
            new DateTimeZone($timezone);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
