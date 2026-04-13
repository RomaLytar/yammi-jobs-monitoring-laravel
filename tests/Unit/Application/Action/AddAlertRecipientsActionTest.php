<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\AddAlertRecipientsAction;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\Exception\InvalidEmailRecipient;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Tests\Support\InMemoryAlertSettingsRepository;

final class AddAlertRecipientsActionTest extends TestCase
{
    public function test_adds_single_email_to_empty_list(): void
    {
        $repo = new InMemoryAlertSettingsRepository;

        (new AddAlertRecipientsAction($repo))(['ops@example.com']);

        self::assertSame(['ops@example.com'], $repo->get()->mailRecipients()->toArray());
    }

    public function test_adds_multiple_emails_at_once(): void
    {
        $repo = new InMemoryAlertSettingsRepository;

        (new AddAlertRecipientsAction($repo))(['ops@example.com', 'sre@example.com', 'oncall@example.com']);

        self::assertSame(
            ['ops@example.com', 'sre@example.com', 'oncall@example.com'],
            $repo->get()->mailRecipients()->toArray(),
        );
    }

    public function test_appends_to_existing_recipients(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(
            null, null, null,
            new EmailRecipientList(['ops@example.com']),
        ));

        (new AddAlertRecipientsAction($repo))(['sre@example.com', 'oncall@example.com']);

        self::assertSame(
            ['ops@example.com', 'sre@example.com', 'oncall@example.com'],
            $repo->get()->mailRecipients()->toArray(),
        );
    }

    public function test_duplicate_in_batch_fails_whole_batch(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(
            null, null, null,
            new EmailRecipientList(['ops@example.com']),
        ));

        try {
            (new AddAlertRecipientsAction($repo))(['sre@example.com', 'OPS@example.com']);
            self::fail('Expected InvalidEmailRecipient');
        } catch (InvalidEmailRecipient) {
            // Recipients unchanged — atomic save was not performed.
            self::assertSame(['ops@example.com'], $repo->get()->mailRecipients()->toArray());
        }
    }

    public function test_invalid_email_in_batch_fails_whole_batch(): void
    {
        $repo = new InMemoryAlertSettingsRepository;

        try {
            (new AddAlertRecipientsAction($repo))(['ok@example.com', 'not-an-email']);
            self::fail('Expected InvalidEmailRecipient');
        } catch (InvalidEmailRecipient) {
            self::assertTrue($repo->get()->mailRecipients()->isEmpty());
        }
    }

    public function test_empty_list_is_noop(): void
    {
        $repo = new InMemoryAlertSettingsRepository;
        $repo->save(new AlertSettings(
            null, null, null,
            new EmailRecipientList(['ops@example.com']),
        ));

        (new AddAlertRecipientsAction($repo))([]);

        self::assertSame(['ops@example.com'], $repo->get()->mailRecipients()->toArray());
    }
}
