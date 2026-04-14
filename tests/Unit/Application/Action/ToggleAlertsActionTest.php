<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\ToggleAlertsAction;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Tests\Support\InMemoryAlertSettingsRepository;

final class ToggleAlertsActionTest extends TestCase
{
    public function test_sets_enabled_true(): void
    {
        $repo = new InMemoryAlertSettingsRepository;

        (new ToggleAlertsAction($repo))(true);

        self::assertTrue($repo->get()->isEnabled());
    }

    public function test_sets_enabled_false(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        (new ToggleAlertsAction($repo))(false);

        self::assertFalse($repo->get()->isEnabled());
    }

    public function test_null_clears_db_override(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        (new ToggleAlertsAction($repo))(null);

        self::assertNull($repo->get()->isEnabled());
    }

    public function test_does_not_touch_other_settings(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(
            enabled: null,
            sourceName: 'Production',
            monitorUrl: null,
            mailRecipients: new EmailRecipientList(['ops@example.com']),
        ));

        (new ToggleAlertsAction($repo))(true);

        $loaded = $repo->get();
        self::assertSame('Production', $loaded->sourceName());
        self::assertSame(['ops@example.com'], $loaded->mailRecipients()->toArray());
    }
}
