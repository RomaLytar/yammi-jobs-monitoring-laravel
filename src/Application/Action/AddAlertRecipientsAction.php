<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;

/**
 * Appends one or more email recipients to the alert mail list atomically.
 *
 * Adding several at once is a single save: validation runs in the
 * EmailRecipientList VO, so a duplicate or malformed entry fails the
 * whole batch (no partial writes).
 */
final class AddAlertRecipientsAction
{
    public function __construct(
        private readonly AlertSettingsRepository $repo,
    ) {}

    /**
     * @param  list<string>  $emails
     */
    public function __invoke(array $emails): void
    {
        $current = $this->repo->get();

        $next = new EmailRecipientList([
            ...$current->mailRecipients()->toArray(),
            ...array_values($emails),
        ]);

        $this->repo->save($current->withMailRecipients($next));
    }
}
