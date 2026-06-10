<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use DateTimeImmutable;
use Yammi\JobsMonitor\Application\Contract\ConfigReader;
use Yammi\JobsMonitor\Application\DTO\PruneResultData;

/** @internal */
final class PruneMonitorDataAction
{
    /**
     * @param  list<PruneTarget>  $targets
     */
    public function __construct(
        private readonly ConfigReader $config,
        private readonly array $targets,
    ) {}

    public function __invoke(?int $daysOverride = null): PruneResultData
    {
        $now = new DateTimeImmutable('now');
        $deleted = [];

        foreach ($this->targets as $target) {
            $cutoff = $now->modify('-'.$this->resolveDays($target, $daysOverride).' days');
            $deleted[$target->name] = ($target->prune)($cutoff);
        }

        return new PruneResultData($deleted);
    }

    private function resolveDays(PruneTarget $target, ?int $daysOverride): int
    {
        if ($daysOverride !== null && $target->overridableByDays) {
            return max(1, $daysOverride);
        }

        $value = $this->config->get($target->retentionConfigPath, $target->defaultDays);

        return is_numeric($value) ? max(1, (int) $value) : $target->defaultDays;
    }
}
