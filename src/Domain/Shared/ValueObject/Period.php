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
        if ($value === null) {
            return self::none();
        }

        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            return self::last($value);
        }

        throw new InvalidPeriod(sprintf(
            'Period value must be null, string or %s; %s given.',
            self::class,
            get_debug_type($value),
        ));
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
