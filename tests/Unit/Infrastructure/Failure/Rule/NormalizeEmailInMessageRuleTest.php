<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Infrastructure\Failure\Rule;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeEmailInMessageRule;

final class NormalizeEmailInMessageRuleTest extends TestCase
{
    public function test_replaces_single_email(): void
    {
        $rule = new NormalizeEmailInMessageRule;

        self::assertSame(
            'user <email> not permitted',
            $rule->apply('user john.doe+tag@example.com not permitted'),
        );
    }

    public function test_replaces_every_email_occurrence(): void
    {
        $rule = new NormalizeEmailInMessageRule;

        self::assertSame(
            '<email> vs <email>',
            $rule->apply('a@b.co vs c.d@e-f.io'),
        );
    }

    public function test_leaves_text_without_email_unchanged(): void
    {
        $rule = new NormalizeEmailInMessageRule;

        self::assertSame('no address here', $rule->apply('no address here'));
    }
}
