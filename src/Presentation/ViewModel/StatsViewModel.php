<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;

/** @internal */
final class StatsViewModel
{
    private const PERIODS = [
        '1m' => '1 minute',
        '5m' => '5 minutes',
        '30m' => '30 minutes',
        '1h' => '1 hour',
        '6h' => '6 hours',
        '24h' => '24 hours',
        '7d' => '7 days',
        '30d' => '30 days',
        'all' => null,
    ];

    private const TOP_LIMIT = 10;

    /**
     * @param  list<array<string, mixed>>  $byClass
     * @param  list<array<string, mixed>>  $mostFailing
     * @param  list<array<string, mixed>>  $slowest
     * @param  array{total: int, processed: int, failed: int, retries: int}  $totals
     * @param  array<string, string|null>  $periods
     */
    public function __construct(
        public readonly array $byClass,
        public readonly array $mostFailing,
        public readonly array $slowest,
        public readonly array $totals,
        public readonly string $period,
        public readonly array $periods,
    ) {}

    public static function fromRepository(JobRecordRepository $repository, string $period): self
    {
        $since = self::periodToSince($period);
        $stats = $repository->aggregateStatsByClassMulti($since);

        $enriched = array_map(static function (array $row): array {
            $row['short_class'] = self::shortClass((string) $row['job_class']);
            $row['failure_rate'] = $row['total'] > 0
                ? round(((int) $row['failed']) / ((int) $row['total']), 4)
                : 0.0;
            $row['avg_duration_formatted'] = self::formatDuration($row['avg_duration_ms'] !== null ? (int) $row['avg_duration_ms'] : null);
            $row['max_duration_formatted'] = self::formatDuration($row['max_duration_ms']);

            return $row;
        }, $stats);

        $mostFailing = array_values(array_filter($enriched, static fn (array $r) => $r['failed'] > 0));
        usort($mostFailing, static fn (array $a, array $b) => $b['failed'] <=> $a['failed']);
        $mostFailing = array_slice($mostFailing, 0, self::TOP_LIMIT);

        $slowest = array_values(array_filter($enriched, static fn (array $r) => $r['avg_duration_ms'] !== null));
        usort($slowest, static fn (array $a, array $b) => $b['avg_duration_ms'] <=> $a['avg_duration_ms']);
        $slowest = array_slice($slowest, 0, self::TOP_LIMIT);

        $totals = self::computeTotals($enriched);

        return new self(
            byClass: $enriched,
            mostFailing: $mostFailing,
            slowest: $slowest,
            totals: $totals,
            period: $period,
            periods: self::PERIODS,
        );
    }

    public function overallFailureRate(): string
    {
        if ($this->totals['total'] === 0) {
            return '—';
        }

        return number_format(($this->totals['failed'] / $this->totals['total']) * 100, 1).'%';
    }

    public function overallRetryRate(): string
    {
        if ($this->totals['total'] === 0) {
            return '—';
        }

        return number_format(($this->totals['retries'] / $this->totals['total']) * 100, 1).'%';
    }

    /**
     * @param  list<array<string, mixed>>  $enriched
     * @return array{total: int, processed: int, failed: int, retries: int}
     */
    private static function computeTotals(array $enriched): array
    {
        $total = 0;
        $processed = 0;
        $failed = 0;
        $retries = 0;

        foreach ($enriched as $row) {
            $total += (int) $row['total'];
            $processed += (int) $row['processed'];
            $failed += (int) $row['failed'];
            $retries += (int) $row['retry_count'];
        }

        return [
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'retries' => $retries,
        ];
    }

    private static function periodToSince(string $period): ?\DateTimeImmutable
    {
        if ($period === 'all' || ! isset(self::PERIODS[$period])) {
            return null;
        }

        return new \DateTimeImmutable('-'.self::PERIODS[$period]);
    }

    private static function shortClass(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }

    private static function formatDuration(?int $ms): string
    {
        if ($ms === null) {
            return '—';
        }

        if ($ms < 1000) {
            return number_format($ms).'ms';
        }

        return number_format($ms / 1000, 2).'s';
    }
}
