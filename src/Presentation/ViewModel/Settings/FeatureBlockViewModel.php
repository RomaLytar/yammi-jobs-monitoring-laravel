<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel\Settings;

/**
 * Read-only descriptor of a single feature block on the settings index page.
 *
 * @internal
 */
final class FeatureBlockViewModel
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $description,
        public readonly bool $enabled,
        public readonly ?string $manageRouteName = null,
    ) {}
}
