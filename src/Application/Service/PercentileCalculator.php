<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

final class PercentileCalculator
{
    /**
     * Linear-interpolation percentile (same definition scipy/numpy use by
     * default). `$percentile` is 0..100. For an empty set returns 0.
     *
     * @param  list<int>  $samples
     */
    public function compute(array $samples, float $percentile): int
    {
        if ($samples === []) {
            return 0;
        }

        sort($samples);
        $n = count($samples);

        if ($n === 1) {
            return $samples[0];
        }

        $rank = ($percentile / 100) * ($n - 1);
        $lower = (int) floor($rank);
        $upper = (int) ceil($rank);

        if ($lower === $upper) {
            return $samples[$lower];
        }

        $fraction = $rank - $lower;

        return (int) round($samples[$lower] + ($samples[$upper] - $samples[$lower]) * $fraction);
    }
}
