<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Failure\Exception;

use Yammi\JobsMonitor\Domain\Exception\DomainException;

final class InvalidFailureFingerprint extends DomainException {}
