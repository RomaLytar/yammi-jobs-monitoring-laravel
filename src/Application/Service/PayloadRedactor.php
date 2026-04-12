<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

final class PayloadRedactor
{
    private const SENSITIVE_PATTERNS = [
        'password',
        'token',
        'secret',
        'api_key',
        'apikey',
        'api-key',
        'authorization',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
    ];

    /**
     * Humanize serialized values and redact sensitive keys.
     *
     * @param  array<string|int, mixed>  $payload
     * @return array<string|int, mixed>
     */
    public function redact(array $payload): array
    {
        return $this->processRecursive($payload);
    }

    /**
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>
     */
    private function processRecursive(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $result[$key] = '********';

                continue;
            }

            if (is_string($value) && $this->looksLikePhpSerialized($value)) {
                $parsed = $this->parseSerializedProperties($value);

                if ($parsed !== null) {
                    $result[$key] = $this->processRecursive($parsed);

                    continue;
                }
            }

            if (is_array($value)) {
                $result[$key] = $this->processRecursive($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function looksLikePhpSerialized(string $value): bool
    {
        return (bool) preg_match('/^[OaCis]:\d+/', $value);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseSerializedProperties(string $serialized): ?array
    {
        $properties = [];

        $pattern = '/s:\d+:"([^"]+)";(s:\d+:"[^"]*"|i:-?\d+|d:[\d.E+-]+|b:[01]|N);/';

        if (! preg_match_all($pattern, $serialized, $matches, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            $key = $match[1];
            $rawValue = $match[2];

            if (str_starts_with($rawValue, 's:')) {
                preg_match('/s:\d+:"(.*)"/s', $rawValue, $strMatch);
                $properties[$key] = $strMatch[1] ?? $rawValue;
            } elseif (str_starts_with($rawValue, 'i:')) {
                $properties[$key] = (int) substr($rawValue, 2);
            } elseif (str_starts_with($rawValue, 'd:')) {
                $properties[$key] = (float) substr($rawValue, 2);
            } elseif (str_starts_with($rawValue, 'b:')) {
                $properties[$key] = substr($rawValue, 2) === '1';
            } elseif ($rawValue === 'N') {
                $properties[$key] = null;
            }
        }

        return $properties !== [] ? $properties : null;
    }

    private function isSensitive(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
