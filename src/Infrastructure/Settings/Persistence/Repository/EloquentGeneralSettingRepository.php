<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository;

use Yammi\JobsMonitor\Domain\Settings\Repository\GeneralSettingRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent\SettingModel;

/** @internal */
final class EloquentGeneralSettingRepository implements GeneralSettingRepository
{
    /**
     * @return array<string, array<string, string>>
     */
    public function all(): array
    {
        $rows = SettingModel::query()
            ->select(['group', 'key', 'value'])
            ->get();

        $result = [];

        foreach ($rows as $row) {
            /** @var string $group */
            $group = $row->getAttribute('group');
            /** @var string $key */
            $key = $row->getAttribute('key');
            /** @var string|null $value */
            $value = $row->getAttribute('value');

            if ($value !== null) {
                $result[$group][$key] = $value;
            }
        }

        return $result;
    }

    public function get(string $group, string $key): ?string
    {
        /** @var SettingModel|null $row */
        $row = SettingModel::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first(['value']);

        if ($row === null) {
            return null;
        }

        /** @var string|null $value */
        $value = $row->getAttribute('value');

        return $value;
    }

    public function set(string $group, string $key, string $value, string $type): void
    {
        SettingModel::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'type' => $type],
        );
    }

    public function remove(string $group, string $key): void
    {
        SettingModel::query()
            ->where('group', $group)
            ->where('key', $key)
            ->delete();
    }
}
