<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository;

use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent\BuiltInRuleStateModel;

final class EloquentBuiltInRuleStateRepository implements BuiltInRuleStateRepository
{
    public function findEnabled(string $key): ?bool
    {
        $row = BuiltInRuleStateModel::query()->where('key', $key)->first();

        return $row === null ? null : (bool) $row->enabled;
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        BuiltInRuleStateModel::query()->updateOrCreate(
            ['key' => $key],
            ['enabled' => $enabled, 'updated_at' => now()],
        );
    }

    public function clear(string $key): void
    {
        BuiltInRuleStateModel::query()->where('key', $key)->delete();
    }

    public function all(): array
    {
        return BuiltInRuleStateModel::query()
            ->get(['key', 'enabled'])
            ->mapWithKeys(static fn (BuiltInRuleStateModel $row): array => [
                (string) $row->key => (bool) $row->enabled,
            ])
            ->all();
    }
}
