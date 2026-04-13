<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\UpdateAlertScalarSettingsAction;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;
use Yammi\JobsMonitor\Tests\Support\InMemoryAlertSettingsRepository;

final class UpdateAlertScalarSettingsActionTest extends TestCase
{
    public function test_persists_source_name_and_monitor_url(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $action = new UpdateAlertScalarSettingsAction($repo);

        $action(
            sourceName: 'Production',
            monitorUrl: new MonitorUrl('https://monitor.example.com'),
        );

        $loaded = $repo->get();
        self::assertSame('Production', $loaded->sourceName());
        self::assertSame('https://monitor.example.com', $loaded->monitorUrl()?->toString());
    }

    public function test_preserves_enabled_flag_and_recipients(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(
            enabled: true,
            sourceName: null,
            monitorUrl: null,
            mailRecipients: new EmailRecipientList(['ops@example.com']),
        ));

        $action = new UpdateAlertScalarSettingsAction($repo);
        $action(sourceName: 'Staging', monitorUrl: null);

        $loaded = $repo->get();
        self::assertTrue($loaded->isEnabled());
        self::assertSame('Staging', $loaded->sourceName());
        self::assertNull($loaded->monitorUrl());
        self::assertSame(['ops@example.com'], $loaded->mailRecipients()->toArray());
    }

    public function test_null_values_clear_db_overrides(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(
            enabled: true,
            sourceName: 'Old',
            monitorUrl: new MonitorUrl('https://old.example.com'),
            mailRecipients: new EmailRecipientList([]),
        ));

        $action = new UpdateAlertScalarSettingsAction($repo);
        $action(sourceName: null, monitorUrl: null);

        $loaded = $repo->get();
        self::assertTrue($loaded->isEnabled()); // toggle untouched
        self::assertNull($loaded->sourceName());
        self::assertNull($loaded->monitorUrl());
    }
}
