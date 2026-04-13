<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;

/**
 * Removes a single email recipient from the alert mail list.
 *
 * Removal is case-insensitive (handled by EmailRecipientList::remove).
 * Removing an unknown email is a no-op — the action does not fail, the
 * controller does not 404. UI semantics: clicking delete is idempotent.
 */
final class RemoveAlertRecipientAction
{
    public function __construct(
        private readonly AlertSettingsRepository $repo,
    ) {}

    public function __invoke(string $email): void
    {
        $current = $this->repo->get();
        $updated = $current->withMailRecipients(
            $current->mailRecipients()->remove($email),
        );

        $this->repo->save($updated);
    }
}
