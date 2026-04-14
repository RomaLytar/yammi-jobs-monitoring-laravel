<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;

/**
 * Contract tests every AlertSettingsRepository implementation must pass.
 *
 * Host classes must define:
 *   protected function createRepository(): AlertSettingsRepository
 */
trait AlertSettingsRepositoryContractTests
{
    abstract protected function createRepository(): AlertSettingsRepository;

    public function test_get_on_empty_storage_returns_unconfigured_settings(): void
    {
        $repo = $this->createRepository();

        $settings = $repo->get();

        self::assertNull($settings->isEnabled());
        self::assertNull($settings->sourceName());
        self::assertNull($settings->monitorUrl());
        self::assertTrue($settings->mailRecipients()->isEmpty());
    }

    public function test_save_then_get_round_trips_all_scalar_fields(): void
    {
        $repo = $this->createRepository();
        $original = new AlertSettings(
            enabled: true,
            sourceName: 'Production',
            monitorUrl: new MonitorUrl('https://monitor.example.com'),
            mailRecipients: new EmailRecipientList(['ops@example.com', 'sre@example.com']),
        );

        $repo->save($original);
        $loaded = $repo->get();

        self::assertTrue($loaded->isEnabled());
        self::assertSame('Production', $loaded->sourceName());
        self::assertSame('https://monitor.example.com', $loaded->monitorUrl()?->toString());
        self::assertSame(['ops@example.com', 'sre@example.com'], $loaded->mailRecipients()->toArray());
    }

    public function test_save_overwrites_previous_settings(): void
    {
        $repo = $this->createRepository();
        $repo->save(new AlertSettings(
            enabled: true,
            sourceName: 'Old',
            monitorUrl: new MonitorUrl('https://old.example.com'),
            mailRecipients: new EmailRecipientList(['old@example.com']),
        ));

        $repo->save(new AlertSettings(
            enabled: false,
            sourceName: 'New',
            monitorUrl: new MonitorUrl('https://new.example.com'),
            mailRecipients: new EmailRecipientList(['new@example.com']),
        ));

        $loaded = $repo->get();

        self::assertFalse($loaded->isEnabled());
        self::assertSame('New', $loaded->sourceName());
        self::assertSame('https://new.example.com', $loaded->monitorUrl()?->toString());
        self::assertSame(['new@example.com'], $loaded->mailRecipients()->toArray());
    }

    public function test_save_can_clear_optional_scalar_fields(): void
    {
        $repo = $this->createRepository();
        $repo->save(new AlertSettings(
            enabled: true,
            sourceName: 'Production',
            monitorUrl: new MonitorUrl('https://monitor.example.com'),
            mailRecipients: new EmailRecipientList(['ops@example.com']),
        ));

        $repo->save(new AlertSettings(
            enabled: null,
            sourceName: null,
            monitorUrl: null,
            mailRecipients: new EmailRecipientList([]),
        ));

        $loaded = $repo->get();

        self::assertNull($loaded->isEnabled());
        self::assertNull($loaded->sourceName());
        self::assertNull($loaded->monitorUrl());
        self::assertTrue($loaded->mailRecipients()->isEmpty());
    }

    public function test_save_replaces_recipient_list_completely(): void
    {
        $repo = $this->createRepository();
        $repo->save(new AlertSettings(
            enabled: true,
            sourceName: null,
            monitorUrl: null,
            mailRecipients: new EmailRecipientList(['a@example.com', 'b@example.com']),
        ));

        $repo->save(new AlertSettings(
            enabled: true,
            sourceName: null,
            monitorUrl: null,
            mailRecipients: new EmailRecipientList(['c@example.com']),
        ));

        self::assertSame(['c@example.com'], $repo->get()->mailRecipients()->toArray());
    }

    public function test_disabled_flag_is_distinguishable_from_unset_flag(): void
    {
        $repo = $this->createRepository();
        $repo->save(new AlertSettings(
            enabled: false,
            sourceName: null,
            monitorUrl: null,
            mailRecipients: new EmailRecipientList([]),
        ));

        $loaded = $repo->get();

        self::assertNotNull($loaded->isEnabled());
        self::assertFalse($loaded->isEnabled());
    }
}
