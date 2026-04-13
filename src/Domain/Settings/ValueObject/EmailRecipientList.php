<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Settings\ValueObject;

use Yammi\JobsMonitor\Domain\Settings\Exception\InvalidEmailRecipient;

final class EmailRecipientList
{
    /** @var list<string> */
    private readonly array $emails;

    /**
     * @param  list<string>  $emails
     */
    public function __construct(array $emails)
    {
        $this->emails = $this->normalize($emails);
    }

    public function isEmpty(): bool
    {
        return $this->emails === [];
    }

    public function count(): int
    {
        return count($this->emails);
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return $this->emails;
    }

    public function add(string $email): self
    {
        return new self([...$this->emails, $email]);
    }

    public function remove(string $email): self
    {
        $needle = $this->canonicalize($email);
        $kept = array_values(array_filter(
            $this->emails,
            static fn (string $existing): bool => $existing !== $needle,
        ));

        return new self($kept);
    }

    public function equals(self $other): bool
    {
        return $this->emails === $other->emails;
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    private function normalize(array $emails): array
    {
        $seen = [];
        $result = [];

        foreach ($emails as $email) {
            $canonical = $this->canonicalize($email);
            $this->assertValidEmail($canonical);
            $this->assertNotDuplicate($canonical, $seen);
            $seen[$canonical] = true;
            $result[] = $canonical;
        }

        return $result;
    }

    private function canonicalize(string $email): string
    {
        return strtolower(trim($email));
    }

    private function assertValidEmail(string $email): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            return;
        }

        throw new InvalidEmailRecipient(sprintf(
            'Email recipient "%s" is not a valid email address.',
            $email,
        ));
    }

    /**
     * @param  array<string, true>  $seen
     */
    private function assertNotDuplicate(string $email, array $seen): void
    {
        if (! isset($seen[$email])) {
            return;
        }

        throw new InvalidEmailRecipient(sprintf(
            'Email recipient "%s" is a duplicate.',
            $email,
        ));
    }
}
