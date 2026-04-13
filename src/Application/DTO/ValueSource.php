<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

/**
 * Where a resolved settings value came from.
 *
 * Used by the UI to render "from DB" / "from config" badges next to
 * each field, so operators can see at a glance whether their UI edits
 * actually own the value or whether config is still winning.
 */
enum ValueSource: string
{
    case Db = 'db';
    case Config = 'config';
    case Auto = 'auto';
    case Default = 'default';
}
