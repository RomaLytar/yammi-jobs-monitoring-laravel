<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Settings\Aggregate;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;

final class AlertSettingsTest extends TestCase
{
    public function test_unconfigured_aggregate_reports_all_fields_as_unset(): void
    {
        $settings = new AlertSettings(
            enabled: null,
            sourceName: null,
            monitorUrl: null,
            mailRecipients: new EmailRecipientList([]),
        );

        self::assertNull($settings->isEnabled());
        self::assertNull($settings->sourceName());
        self::assertNull($settings->monitorUrl());
        self::assertTrue($settings->mailRecipients()->isEmpty());
        self::assertFalse($settings->hasEnabledFlag());
        self::assertFalse($settings->hasSourceName());
        self::assertFalse($settings->hasMonitorUrl());
    }

    public function test_aggregate_holds_provided_scalar_settings(): void
    {
        $settings = new AlertSettings(
            enabled: true,
            sourceName: 'Production',
            monitorUrl: new MonitorUrl('https://monitor.example.com'),
            mailRecipients: new EmailRecipientList(['ops@example.com']),
        );

        self::assertTrue($settings->isEnabled());
        self::assertTrue($settings->hasEnabledFlag());
        self::assertSame('Production', $settings->sourceName());
        self::assertTrue($settings->hasSourceName());
        self::assertNotNull($settings->monitorUrl());
        self::assertSame('https://monitor.example.com', $settings->monitorUrl()->toString());
        self::assertTrue($settings->hasMonitorUrl());
        self::assertSame(['ops@example.com'], $settings->mailRecipients()->toArray());
    }

    public function test_with_enabled_returns_new_instance_with_flag_changed(): void
    {
        $original = $this->emptySettings();
        $enabled = $original->withEnabled(true);
        $disabled = $original->withEnabled(false);

        self::assertNull($original->isEnabled());
        self::assertTrue($enabled->isEnabled());
        self::assertFalse($disabled->isEnabled());
    }

    public function test_with_source_name_returns_new_instance_with_value_set(): void
    {
        $original = $this->emptySettings();
        $named = $original->withSourceName('Staging');

        self::assertNull($original->sourceName());
        self::assertSame('Staging', $named->sourceName());
        self::assertTrue($named->hasSourceName());
    }

    public function test_with_source_name_null_clears_value(): void
    {
        $named = $this->emptySettings()->withSourceName('Staging');
        $cleared = $named->withSourceName(null);

        self::assertNull($cleared->sourceName());
        self::assertFalse($cleared->hasSourceName());
    }

    public function test_with_monitor_url_returns_new_instance(): void
    {
        $original = $this->emptySettings();
        $url = new MonitorUrl('https://monitor.example.com');
        $next = $original->withMonitorUrl($url);

        self::assertNull($original->monitorUrl());
        self::assertSame('https://monitor.example.com', $next->monitorUrl()?->toString());
    }

    public function test_with_mail_recipients_replaces_list(): void
    {
        $original = new AlertSettings(
            enabled: null,
            sourceName: null,
            monitorUrl: null,
            mailRecipients: new EmailRecipientList(['a@example.com']),
        );

        $next = $original->withMailRecipients(new EmailRecipientList(['b@example.com', 'c@example.com']));

        self::assertSame(['a@example.com'], $original->mailRecipients()->toArray());
        self::assertSame(['b@example.com', 'c@example.com'], $next->mailRecipients()->toArray());
    }

    private function emptySettings(): AlertSettings
    {
        return new AlertSettings(
            enabled: null,
            sourceName: null,
            monitorUrl: null,
            mailRecipients: new EmailRecipientList([]),
        );
    }
}
