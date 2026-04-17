<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Failure\Service;

use Yammi\JobsMonitor\Domain\Failure\Contract\TraceRedactor;

/**
 * Strips host-specific absolute paths and redacts common secret patterns
 * so that stored/rendered exception data does not leak environment
 * details or credentials.
 */
final class DefaultTraceRedactor implements TraceRedactor
{
    // When an absolute path contains one of these markers, the prefix
    // (deployment root) is dropped so only the project-relative tail
    // leaks into the UI or storage.
    private const PATH_MARKERS = ['/vendor/', '/src/', '/app/', '/lib/', '/tests/', '/database/', '/resources/', '/routes/', '/bootstrap/', '/config/'];

    /** @var list<array{0: string, 1: string}> */
    private const SECRET_PATTERNS = [
        // Authorization: Bearer <token>  (scheme stays, credential goes)
        ['/(authorization\s*:\s*(?:Bearer|Basic|Token|Digest)\s+)([^\s,;"\'<>]+)/i', '$1[REDACTED]'],
        // JWT-looking tokens (three dot-separated base64url segments starting with eyJ)
        ['/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/', '[REDACTED_JWT]'],
        // key=value / key: value with credentials/tokens
        ['/(?<![A-Za-z0-9_])(password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key|authorization|auth)(\s*[:=]\s*)(["\']?)([^\s"\',;]+)\3/i', '$1$2$3[REDACTED]$3'],
        // URL-embedded credentials: scheme://user:pass@host
        ['/([a-z][a-z0-9+\-.]*:\/\/[^:\/\s]+):([^@\/\s]+)@/i', '$1:[REDACTED]@'],
        // AWS-style access keys (AKIA… / ASIA…)
        ['/\b(AKIA|ASIA)[A-Z0-9]{16}\b/', '[REDACTED_AWS_KEY]'],
    ];

    public function redact(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        $text = $this->stripAbsolutePaths($text);

        foreach (self::SECRET_PATTERNS as [$pattern, $replacement]) {
            $replaced = preg_replace($pattern, $replacement, $text);
            if (is_string($replaced)) {
                $text = $replaced;
            }
        }

        return $text;
    }

    private function stripAbsolutePaths(string $text): string
    {
        // Matches Unix and Windows absolute paths (including paths with
        // spaces not containing whitespace after the last path segment).
        $pattern = '/(?<![A-Za-z0-9])(?:\/|[A-Za-z]:\\\\)[^\s()\[\]"\'<>]+/';

        $replaced = preg_replace_callback(
            $pattern,
            fn (array $m): string => $this->toRelativePath($m[0]),
            $text,
        );

        return is_string($replaced) ? $replaced : $text;
    }

    private function toRelativePath(string $path): string
    {
        $normalised = str_replace('\\', '/', $path);

        foreach (self::PATH_MARKERS as $marker) {
            $position = strrpos($normalised, $marker);
            if ($position !== false) {
                return substr($normalised, $position + 1);
            }
        }

        return $path;
    }
}
