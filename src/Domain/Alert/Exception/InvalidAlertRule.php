<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Alert\Exception;

use Yammi\JobsMonitor\Domain\Exception\DomainException;

/**
 * Raised when an alert rule definition cannot be validated.
 *
 * The specific failure mode is communicated through the message,
 * composed at the throw site. No named-constructor factories —
 * error construction stays co-located with the decision that
 * produced it.
 */
final class InvalidAlertRule extends DomainException {}
