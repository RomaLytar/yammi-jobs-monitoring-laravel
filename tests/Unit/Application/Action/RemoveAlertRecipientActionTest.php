<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\RemoveAlertRecipientAction;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Tests\Support\InMemoryAlertSettingsRepository;

final class RemoveAlertRecipientActionTest extends TestCase
{
    public function test_removes_email_from_list(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(
            null, null, null,
            new EmailRecipientList(['ops@example.com', 'sre@example.com']),
        ));

        (new RemoveAlertRecipientAction($repo))('ops@example.com');

        self::assertSame(['sre@example.com'], $repo->get()->mailRecipients()->toArray());
    }

    public function test_removes_case_insensitively(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(
            null, null, null,
            new EmailRecipientList(['ops@example.com']),
        ));

        (new RemoveAlertRecipientAction($repo))('OPS@example.com');

        self::assertTrue($repo->get()->mailRecipients()->isEmpty());
    }

    public function test_unknown_email_is_noop(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(
            null, null, null,
            new EmailRecipientList(['ops@example.com']),
        ));

        (new RemoveAlertRecipientAction($repo))('unknown@example.com');

        self::assertSame(['ops@example.com'], $repo->get()->mailRecipients()->toArray());
    }
}
