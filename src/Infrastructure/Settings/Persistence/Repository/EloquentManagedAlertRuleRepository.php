<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository;

use Illuminate\Database\ConnectionInterface;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent\AlertRuleChannelModel;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent\AlertRuleModel;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Mapper\ManagedAlertRuleMapper;

final class EloquentManagedAlertRuleRepository implements ManagedAlertRuleRepository
{
    private readonly ManagedAlertRuleMapper $mapper;

    public function __construct(?ManagedAlertRuleMapper $mapper = null)
    {
        $this->mapper = $mapper ?? new ManagedAlertRuleMapper;
    }

    public function all(): array
    {
        $rows = AlertRuleModel::query()
            ->with('channels')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return $rows->map(fn (AlertRuleModel $row): ManagedAlertRule => $this->mapper->toEntity(
            $row,
            $this->extractChannelNames($row),
        ))->all();
    }

    public function findById(int $id): ?ManagedAlertRule
    {
        $row = AlertRuleModel::query()->with('channels')->find($id);

        return $row === null
            ? null
            : $this->mapper->toEntity($row, $this->extractChannelNames($row));
    }

    public function findByKey(string $key): ?ManagedAlertRule
    {
        $row = AlertRuleModel::query()->with('channels')->where('key', $key)->first();

        return $row === null
            ? null
            : $this->mapper->toEntity($row, $this->extractChannelNames($row));
    }

    public function save(ManagedAlertRule $rule): ManagedAlertRule
    {
        $payload = $this->mapper->toRow($rule);

        return $this->connection()->transaction(function () use ($rule, $payload): ManagedAlertRule {
            $row = AlertRuleModel::query()->where('key', $rule->key())->first()
                ?? new AlertRuleModel;

            $row->forceFill($payload);
            $row->save();

            $this->syncChannels((int) $row->id, $rule->rule()->channels);

            return $rule->withId((int) $row->id);
        });
    }

    public function delete(int $id): bool
    {
        return AlertRuleModel::query()->whereKey($id)->delete() > 0;
    }

    /**
     * @param  list<string>  $channels
     */
    private function syncChannels(int $ruleId, array $channels): void
    {
        AlertRuleChannelModel::query()->where('alert_rule_id', $ruleId)->delete();

        foreach (array_values($channels) as $position => $channel) {
            AlertRuleChannelModel::query()->create([
                'alert_rule_id' => $ruleId,
                'channel_name' => $channel,
                'position' => $position,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function extractChannelNames(AlertRuleModel $row): array
    {
        /** @var iterable<AlertRuleChannelModel> $channels */
        $channels = $row->channels;

        $names = [];
        foreach ($channels as $channel) {
            $names[] = (string) $channel->channel_name;
        }

        return $names;
    }

    private function connection(): ConnectionInterface
    {
        return AlertRuleModel::query()->getConnection();
    }
}
