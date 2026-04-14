<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\GetAlertSettingsAction;
use Yammi\JobsMonitor\Application\DTO\ValueSource;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;
use Yammi\JobsMonitor\Tests\Support\InMemoryAlertSettingsRepository;

final class GetAlertSettingsActionTest extends TestCase
{
    public function test_unset_everywhere_yields_default_sources(): void
    {
        $data = $this->buildAction()();

        self::assertFalse($data->enabled);
        self::assertSame(ValueSource::Default, $data->enabledSource);
        self::assertNull($data->sourceName);
        self::assertSame(ValueSource::Default, $data->sourceNameSource);
        self::assertNull($data->monitorUrl);
        self::assertSame(ValueSource::Default, $data->monitorUrlSource);
        self::assertSame([], $data->recipients);
        self::assertSame(ValueSource::Default, $data->recipientsSource);
    }

    public function test_auto_derived_values_are_marked_as_auto(): void
    {
        $data = $this->buildAction(
            autoSourceName: 'YammiMonitor',
            autoMonitorUrl: 'https://app.example.com/jobs-monitor',
        )();

        self::assertSame('YammiMonitor', $data->sourceName);
        self::assertSame(ValueSource::Auto, $data->sourceNameSource);
        self::assertSame('https://app.example.com/jobs-monitor', $data->monitorUrl);
        self::assertSame(ValueSource::Auto, $data->monitorUrlSource);
    }

    public function test_explicit_config_beats_auto_derivation(): void
    {
        $data = $this->buildAction(
            configSourceName: 'Production',
            autoSourceName: 'YammiMonitor',
            configMonitorUrl: 'https://monitor.example.com',
            autoMonitorUrl: 'https://app.example.com/jobs-monitor',
        )();

        self::assertSame('Production', $data->sourceName);
        self::assertSame(ValueSource::Config, $data->sourceNameSource);
        self::assertSame('https://monitor.example.com', $data->monitorUrl);
        self::assertSame(ValueSource::Config, $data->monitorUrlSource);
    }

    public function test_db_overrides_both_config_and_auto(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(
            enabled: false,
            sourceName: 'Staging',
            monitorUrl: new MonitorUrl('https://staging.example.com'),
            mailRecipients: new EmailRecipientList(['db@example.com']),
        ));

        $data = $this->buildAction(
            repo: $repo,
            configEnabled: true,
            configSourceName: 'Production',
            autoSourceName: 'YammiMonitor',
            configMonitorUrl: 'https://prod.example.com',
            autoMonitorUrl: 'https://app.example.com/jobs-monitor',
            configRecipients: ['config@example.com'],
        )();

        self::assertFalse($data->enabled);
        self::assertSame(ValueSource::Db, $data->enabledSource);
        self::assertSame('Staging', $data->sourceName);
        self::assertSame(ValueSource::Db, $data->sourceNameSource);
        self::assertSame('https://staging.example.com', $data->monitorUrl);
        self::assertSame(ValueSource::Db, $data->monitorUrlSource);
        self::assertSame(['db@example.com'], $data->recipients);
        self::assertSame(ValueSource::Db, $data->recipientsSource);
    }

    public function test_resolution_chain_db_then_config_then_auto_then_default(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(
            enabled: null,
            sourceName: 'OnlyInDb',
            monitorUrl: null,
            mailRecipients: new EmailRecipientList([]),
        ));

        $data = $this->buildAction(
            repo: $repo,
            configMonitorUrl: 'https://from-config.example.com',
            autoMonitorUrl: 'https://app.example.com/jobs-monitor',
        )();

        self::assertSame('OnlyInDb', $data->sourceName);
        self::assertSame(ValueSource::Db, $data->sourceNameSource);
        self::assertSame('https://from-config.example.com', $data->monitorUrl);
        self::assertSame(ValueSource::Config, $data->monitorUrlSource);
    }

    /**
     * @param  list<string>  $configRecipients
     */
    private function buildAction(
        ?InMemoryAlertSettingsRepository $repo = null,
        ?bool $configEnabled = null,
        ?string $configSourceName = null,
        ?string $autoSourceName = null,
        ?string $configMonitorUrl = null,
        ?string $autoMonitorUrl = null,
        array $configRecipients = [],
    ): GetAlertSettingsAction {
        return new GetAlertSettingsAction(
            repo: $repo ?? new InMemoryAlertSettingsRepository,
            configEnabled: $configEnabled,
            configSourceName: $configSourceName,
            autoSourceName: $autoSourceName,
            configMonitorUrl: $configMonitorUrl,
            autoMonitorUrl: $autoMonitorUrl,
            configRecipients: $configRecipients,
        );
    }
}
