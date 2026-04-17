<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Playground;

use Yammi\JobsMonitor\Application\Exception\InvalidPlaygroundArgument;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Shared\ValueObject\Period;

/**
 * Converts string-typed form inputs into the native PHP values a facade
 * method expects. Throws {@see InvalidPlaygroundArgument} with a
 * human-readable message if a value cannot be coerced — the controller
 * turns that into a 422 response, never a 500.
 */
final class ArgumentCoercer
{
    public function coerce(PlaygroundArgument $arg, mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            if ($arg->required) {
                throw new InvalidPlaygroundArgument(sprintf('Argument "%s" is required.', $arg->name));
            }

            return $arg->default;
        }

        return match ($arg->type) {
            ArgumentType::StringText => $this->coerceString($arg, $raw),
            ArgumentType::Integer => $this->coerceInteger($arg, $raw),
            ArgumentType::Boolean => $this->coerceBoolean($arg, $raw),
            ArgumentType::NullableBoolean => $this->coerceNullableBoolean($arg, $raw),
            ArgumentType::Period => $this->coercePeriod($raw),
            ArgumentType::Uuid => $this->coerceUuid($arg, $raw),
            ArgumentType::UuidList => $this->coerceUuidList($arg, $raw),
            ArgumentType::Fingerprint => $this->coerceFingerprint($arg, $raw),
            ArgumentType::FingerprintList => $this->coerceFingerprintList($arg, $raw),
            ArgumentType::JsonObject => $this->coerceJsonObject($arg, $raw),
            ArgumentType::EmailList => $this->coerceEmailList($arg, $raw),
            ArgumentType::Email => $this->coerceEmail($arg, $raw),
            ArgumentType::JobStatus => $this->coerceJobStatus($arg, $raw),
        };
    }

    private function coerceString(PlaygroundArgument $arg, mixed $raw): string
    {
        if (! is_string($raw)) {
            throw new InvalidPlaygroundArgument(sprintf('Argument "%s" must be a string.', $arg->name));
        }

        return $raw;
    }

    private function coerceInteger(PlaygroundArgument $arg, mixed $raw): int
    {
        if (is_int($raw)) {
            return $raw;
        }

        if (is_string($raw) && preg_match('/^-?\d+$/', $raw) === 1) {
            return (int) $raw;
        }

        throw new InvalidPlaygroundArgument(sprintf('Argument "%s" must be an integer.', $arg->name));
    }

    private function coerceBoolean(PlaygroundArgument $arg, mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        $normalized = is_string($raw) ? strtolower(trim($raw)) : null;
        if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['false', '0', 'no', 'off'], true)) {
            return false;
        }

        throw new InvalidPlaygroundArgument(sprintf('Argument "%s" must be a boolean (true/false).', $arg->name));
    }

    private function coerceNullableBoolean(PlaygroundArgument $arg, mixed $raw): ?bool
    {
        if (is_string($raw) && strtolower(trim($raw)) === 'null') {
            return null;
        }

        return $this->coerceBoolean($arg, $raw);
    }

    private function coercePeriod(mixed $raw): Period
    {
        return Period::fromValue($raw);
    }

    private function coerceUuid(PlaygroundArgument $arg, mixed $raw): string
    {
        if (! is_string($raw) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $raw) !== 1) {
            throw new InvalidPlaygroundArgument(sprintf('Argument "%s" must be a UUID.', $arg->name));
        }

        return strtolower($raw);
    }

    /**
     * @return list<string>
     */
    private function coerceUuidList(PlaygroundArgument $arg, mixed $raw): array
    {
        $values = $this->splitList($arg, $raw);

        return array_map(fn (string $uuid) => $this->coerceUuid($arg, $uuid), $values);
    }

    private function coerceFingerprint(PlaygroundArgument $arg, mixed $raw): string
    {
        if (! is_string($raw) || preg_match('/^[0-9a-f]{16}$/', $raw) !== 1) {
            throw new InvalidPlaygroundArgument(sprintf('Argument "%s" must be a 16-char lowercase hex fingerprint.', $arg->name));
        }

        return $raw;
    }

    /**
     * @return list<string>
     */
    private function coerceFingerprintList(PlaygroundArgument $arg, mixed $raw): array
    {
        $values = $this->splitList($arg, $raw);

        return array_map(fn (string $fp) => $this->coerceFingerprint($arg, $fp), $values);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function coerceJsonObject(PlaygroundArgument $arg, mixed $raw): array
    {
        if (! is_string($raw)) {
            throw new InvalidPlaygroundArgument(sprintf('Argument "%s" must be a JSON string.', $arg->name));
        }

        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidPlaygroundArgument(sprintf('Argument "%s": %s.', $arg->name, $e->getMessage()));
        }

        if (! is_array($decoded)) {
            throw new InvalidPlaygroundArgument(sprintf('Argument "%s" must be a JSON object.', $arg->name));
        }

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private function coerceEmailList(PlaygroundArgument $arg, mixed $raw): array
    {
        $values = $this->splitList($arg, $raw);

        return array_map(fn (string $email) => $this->coerceEmail($arg, $email), $values);
    }

    private function coerceEmail(PlaygroundArgument $arg, mixed $raw): string
    {
        if (! is_string($raw) || filter_var($raw, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidPlaygroundArgument(sprintf('Argument "%s" must be a valid email.', $arg->name));
        }

        return $raw;
    }

    private function coerceJobStatus(PlaygroundArgument $arg, mixed $raw): JobStatus
    {
        if (! is_string($raw)) {
            throw new InvalidPlaygroundArgument(sprintf('Argument "%s" must be a string.', $arg->name));
        }

        $status = JobStatus::tryFrom($raw);
        if ($status === null) {
            throw new InvalidPlaygroundArgument(sprintf(
                'Argument "%s" must be one of: %s.',
                $arg->name,
                implode(', ', array_map(static fn (JobStatus $s) => $s->value, JobStatus::cases())),
            ));
        }

        return $status;
    }

    /**
     * @return list<string>
     */
    private function splitList(PlaygroundArgument $arg, mixed $raw): array
    {
        if (! is_string($raw)) {
            throw new InvalidPlaygroundArgument(sprintf('Argument "%s" must be a string list.', $arg->name));
        }

        $parts = preg_split('/[\s,]+/', trim($raw));
        $clean = array_values(array_filter((array) $parts, static fn ($v) => is_string($v) && $v !== ''));

        if ($clean === []) {
            throw new InvalidPlaygroundArgument(sprintf('Argument "%s" must contain at least one value.', $arg->name));
        }

        return $clean;
    }
}
