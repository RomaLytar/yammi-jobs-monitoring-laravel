<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Worker\Exception;

use Yammi\JobsMonitor\Domain\Exception\DomainException;

final class InvalidWorkerHeartbeat extends DomainException {}
