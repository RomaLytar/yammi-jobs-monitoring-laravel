<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Shared\ValueObject;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Shared\Exception\InvalidPeriod;

final class Period
{
    private function __construct(
        private readonly ?DateTimeImmutable $from,
        private readonly ?DateTimeImmutable $to,
    ) {}

    public static function none(): self
    {
        return new self(null, null);
    }

    public static function last(string $expression, ?DateTimeImmutable $now = null): self
    {
        $trimmed = trim($expression);

        if ($trimmed === '') {
            throw new InvalidPeriod('Period expression cannot be empty.');
        }

        if (! preg_match('/^(\d+)([mhd])$/', $trimmed, $m)) {
            throw new InvalidPeriod(sprintf(
                'Period expression "%s" is invalid. Expected format: <int><unit> where unit is m|h|d.',
                $expression,
            ));
        }

        $magnitude = (int) $m[1];

        if ($magnitude <= 0) {
            throw new InvalidPeriod(sprintf('Period magnitude must be positive, got "%s".', $expression));
        }

        $unit = match ($m[2]) {
            'm' => 'minutes',
            'h' => 'hours',
            'd' => 'days',
        };

        $to = $now ?? new DateTimeImmutable;
        $from = $to->modify(sprintf('-%d %s', $magnitude, $unit));

        return new self($from, $to);
    }

    public static function between(DateTimeImmutable $from, DateTimeImmutable $to): self
    {
        if ($from > $to) {
            throw new InvalidPeriod('Period "from" must be less than or equal to "to".');
        }

        return new self($from, $to);
    }

    public static function since(DateTimeImmutable $from): self
    {
        return new self($from, null);
    }

    public static function fromValue(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            $lower = strtolower($trimmed);

            if ($lower === 'all' || $lower === '') {
                return $lower === 'all' ? self::none() : self::last($value);
            }

            if (str_contains($trimmed, '..')) {
                return self::parseRange($trimmed);
            }

            if (str_starts_with($lower, 'since:')) {
                return self::since(self::parseDate(substr($trimmed, 6), $value));
            }

            return self::last($value);
        }

        throw new InvalidPeriod(sprintf(
            'Period must be "all", a <int><unit> string (e.g. "30m", "1h", "7d", "30d"), a "from..to" range, "since:<date>", or a %s instance; %s given.',
            self::class,
            get_debug_type($value),
        ));
    }

    private static function parseRange(string $expression): self
    {
        [$from, $to] = array_map('trim', explode('..', $expression, 2));

        if ($from === '' || $to === '') {
            throw new InvalidPeriod(sprintf('Period range must be "<from>..<to>", got "%s".', $expression));
        }

        return self::between(self::parseDate($from, $expression), self::parseDate($to, $expression));
    }

    private static function parseDate(string $raw, string $originalExpression): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($raw);
        } catch (\Exception $e) {
            throw new InvalidPeriod(sprintf(
                'Period expression "%s" contains an unparseable date "%s": %s',
                $originalExpression,
                $raw,
                $e->getMessage(),
            ));
        }
    }

    public function from(): ?DateTimeImmutable
    {
        return $this->from;
    }

    public function to(): ?DateTimeImmutable
    {
        return $this->to;
    }

    public function isUnbounded(): bool
    {
        return $this->from === null && $this->to === null;
    }
}
