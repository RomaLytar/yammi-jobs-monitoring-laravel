<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Failure\Service;

use Yammi\JobsMonitor\Domain\Failure\Contract\MessageNormalizationRule;
use Yammi\JobsMonitor\Domain\Failure\Contract\TraceNormalizer;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\NormalizedTrace;

final class RuleBasedTraceNormalizer implements TraceNormalizer
{
    private const FRAME_REGEX = '/^#\d+\s+(\S+?)\((\d+)\):\s+(\S+?)(?:->|::)(\w+|\{closure\})\(/';

    private const RELATIVE_PATH_MARKERS = ['/app/', '/src/', '/lib/'];

    /**
     * @param  list<MessageNormalizationRule>  $rules
     */
    public function __construct(private readonly array $rules) {}

    public function normalize(
        string $exceptionClass,
        string $message,
        string $stackTraceAsString,
    ): NormalizedTrace {
        return new NormalizedTrace(
            exceptionClass: $exceptionClass,
            normalizedMessage: $this->applyMessageRules($message),
            firstUserFrame: $this->extractFirstUserFrame($stackTraceAsString),
        );
    }

    private function applyMessageRules(string $message): string
    {
        foreach ($this->rules as $rule) {
            $message = $rule->apply($message);
        }

        return $message;
    }

    private function extractFirstUserFrame(string $stackTrace): string
    {
        $hadVendorFrame = false;

        foreach (preg_split('/\R/', $stackTrace) ?: [] as $line) {
            $parsed = $this->parseFrame($line);
            if ($parsed === null) {
                continue;
            }

            if (str_contains($parsed['path'], '/vendor/')) {
                $hadVendorFrame = true;

                continue;
            }

            return $this->formatUserFrame($parsed['path'], $parsed['class'], $parsed['method']);
        }

        return $hadVendorFrame ? '<vendor>' : '<unknown>';
    }

    /**
     * @return array{path: string, class: string, method: string}|null
     */
    private function parseFrame(string $line): ?array
    {
        if (preg_match(self::FRAME_REGEX, $line, $matches) !== 1) {
            return null;
        }

        return [
            'path' => $matches[1],
            'class' => $matches[3],
            'method' => $matches[4],
        ];
    }

    private function formatUserFrame(string $path, string $class, string $method): string
    {
        return $this->toRelativePath($path).'::'.$class.'@'.$method;
    }

    private function toRelativePath(string $absolutePath): string
    {
        foreach (self::RELATIVE_PATH_MARKERS as $marker) {
            $position = strrpos($absolutePath, $marker);
            if ($position !== false) {
                return substr($absolutePath, $position + 1);
            }
        }

        return basename($absolutePath);
    }
}
