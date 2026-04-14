<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;

/**
 * Removes a managed alert rule by id. Returns true when a row was
 * deleted, false when the id was not found (idempotent UI semantics).
 */
final class DeleteManagedAlertRuleAction
{
    public function __construct(
        private readonly ManagedAlertRuleRepository $repo,
    ) {}

    public function __invoke(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
