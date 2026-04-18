<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Contract;

/**
 * Persists the set of "currently alerting" worker identifiers and queue
 * keys between watchdog ticks. Needed so we can emit resolve events on
 * the tick *after* a condition clears — without state we cannot tell
 * "first time alerting" from "still alerting" from "just recovered".
 *
 * Keys are opaque strings from the caller's perspective. Implementations
 * must be atomic enough that concurrent ticks do not lose or duplicate
 * transitions (cache CAS, DB unique row, etc).
 */
interface WorkerAlertStateStore
{
    /**
     * Return the currently-active alert keys of the given type.
     *
     * @return list<string>
     */
    public function active(string $category): array;

    /**
     * Replace the active set for the category with the given keys.
     *
     * @param  list<string>  $keys
     */
    public function replace(string $category, array $keys): void;
}
