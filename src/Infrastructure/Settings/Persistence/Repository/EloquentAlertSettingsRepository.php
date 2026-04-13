<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository;

use Illuminate\Database\ConnectionInterface;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent\AlertMailRecipientModel;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent\AlertSettingsModel;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Mapper\AlertSettingsMapper;

final class EloquentAlertSettingsRepository implements AlertSettingsRepository
{
    private readonly AlertSettingsMapper $mapper;

    public function __construct(?AlertSettingsMapper $mapper = null)
    {
        $this->mapper = $mapper ?? new AlertSettingsMapper;
    }

    public function get(): AlertSettings
    {
        $row = AlertSettingsModel::query()->find(AlertSettingsModel::SINGLETON_ID);
        $recipients = AlertMailRecipientModel::query()
            ->orderBy('id')
            ->pluck('email')
            ->all();

        return $this->mapper->toAggregate($row, array_values($recipients));
    }

    public function save(AlertSettings $settings): void
    {
        $row = $this->mapper->toScalarRow($settings);

        $this->connection()->transaction(function () use ($settings, $row): void {
            $model = AlertSettingsModel::query()->find(AlertSettingsModel::SINGLETON_ID)
                ?? new AlertSettingsModel(['id' => AlertSettingsModel::SINGLETON_ID]);

            $model->forceFill($row);
            $model->save();

            AlertMailRecipientModel::query()->delete();

            foreach ($settings->mailRecipients()->toArray() as $email) {
                AlertMailRecipientModel::query()->create(['email' => $email]);
            }
        });
    }

    private function connection(): ConnectionInterface
    {
        return AlertSettingsModel::query()->getConnection();
    }
}
