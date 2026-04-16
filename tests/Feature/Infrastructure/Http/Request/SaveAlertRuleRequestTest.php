<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Request;

use Illuminate\Support\Facades\Validator;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Infrastructure\Http\Request\SaveAlertRuleRequest;
use Yammi\JobsMonitor\Tests\TestCase;

final class SaveAlertRuleRequestTest extends TestCase
{
    public function test_valid_minimal_payload_passes(): void
    {
        $validator = $this->validate($this->validPayload());

        self::assertTrue($validator->passes());
    }

    public function test_valid_payload_with_all_optional_fields_passes(): void
    {
        $data = array_merge($this->validPayload(), [
            'trigger_value' => 'critical',
            'window' => '10m',
            'min_attempt' => 2,
            'overrides_built_in' => 'critical_failure',
            'position' => 5,
        ]);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_missing_key_fails(): void
    {
        $data = $this->validPayload();
        unset($data['key']);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('key', $validator->errors()->toArray());
    }

    public function test_empty_key_fails(): void
    {
        $data = $this->validPayload(['key' => '']);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_key_exceeding_max_length_fails(): void
    {
        $data = $this->validPayload(['key' => str_repeat('a', 101)]);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_key_at_max_length_passes(): void
    {
        $data = $this->validPayload(['key' => str_repeat('a', 100)]);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_key_with_special_characters_fails(): void
    {
        $data = $this->validPayload(['key' => 'my rule!']);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_key_with_allowed_characters_passes(): void
    {
        $data = $this->validPayload(['key' => 'my_rule-123']);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_missing_trigger_fails(): void
    {
        $data = $this->validPayload();
        unset($data['trigger']);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('trigger', $validator->errors()->toArray());
    }

    public function test_invalid_trigger_value_fails(): void
    {
        $data = $this->validPayload(['trigger' => 'nonexistent_trigger']);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    /** @dataProvider validTriggerProvider */
    public function test_each_alert_trigger_value_passes(string $trigger): void
    {
        $data = $this->validPayload(['trigger' => $trigger]);

        $validator = $this->validate($data);

        self::assertFalse($validator->errors()->has('trigger'), "Trigger '{$trigger}' should be valid");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validTriggerProvider(): iterable
    {
        foreach (AlertTrigger::cases() as $case) {
            yield $case->name => [$case->value];
        }
    }

    public function test_missing_threshold_fails(): void
    {
        $data = $this->validPayload();
        unset($data['threshold']);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('threshold', $validator->errors()->toArray());
    }

    public function test_zero_threshold_fails(): void
    {
        $data = $this->validPayload(['threshold' => 0]);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_negative_threshold_fails(): void
    {
        $data = $this->validPayload(['threshold' => -1]);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_threshold_of_one_passes(): void
    {
        $data = $this->validPayload(['threshold' => 1]);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_missing_cooldown_minutes_fails(): void
    {
        $data = $this->validPayload();
        unset($data['cooldown_minutes']);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('cooldown_minutes', $validator->errors()->toArray());
    }

    public function test_zero_cooldown_minutes_fails(): void
    {
        $data = $this->validPayload(['cooldown_minutes' => 0]);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_cooldown_minutes_of_one_passes(): void
    {
        $data = $this->validPayload(['cooldown_minutes' => 1]);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_missing_channels_fails(): void
    {
        $data = $this->validPayload();
        unset($data['channels']);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('channels', $validator->errors()->toArray());
    }

    public function test_empty_channels_fails(): void
    {
        $data = $this->validPayload(['channels' => []]);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_unknown_channel_fails(): void
    {
        $data = $this->validPayload(['channels' => ['discord']]);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    /** @dataProvider validChannelProvider */
    public function test_each_valid_channel_passes(string $channel): void
    {
        $data = $this->validPayload(['channels' => [$channel]]);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes(), "Channel '{$channel}' should be valid");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validChannelProvider(): iterable
    {
        foreach (['slack', 'mail', 'pagerduty', 'opsgenie', 'webhook'] as $channel) {
            yield $channel => [$channel];
        }
    }

    public function test_multiple_channels_pass(): void
    {
        $data = $this->validPayload(['channels' => ['slack', 'mail', 'pagerduty', 'opsgenie', 'webhook']]);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_missing_enabled_fails(): void
    {
        $data = $this->validPayload();
        unset($data['enabled']);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('enabled', $validator->errors()->toArray());
    }

    public function test_non_boolean_enabled_fails(): void
    {
        $data = $this->validPayload(['enabled' => 'yes']);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_valid_window_formats_pass(): void
    {
        foreach (['5s', '10m', '1h', '7d', '120s', '999m'] as $window) {
            $data = $this->validPayload(['window' => $window]);

            $validator = $this->validate($data);

            self::assertTrue($validator->passes(), "Window '{$window}' should be valid");
        }
    }

    public function test_invalid_window_format_fails(): void
    {
        foreach (['5x', 'abc', '10', 'm5'] as $window) {
            $data = $this->validPayload(['window' => $window]);

            $validator = $this->validate($data);

            self::assertTrue(
                $validator->fails(),
                "Window '{$window}' should be invalid",
            );
        }
    }

    public function test_null_window_passes(): void
    {
        $data = $this->validPayload(['window' => null]);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_trigger_value_exceeding_max_length_fails(): void
    {
        $data = $this->validPayload(['trigger_value' => str_repeat('a', 256)]);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_min_attempt_below_one_fails(): void
    {
        $data = $this->validPayload(['min_attempt' => 0]);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_min_attempt_of_one_passes(): void
    {
        $data = $this->validPayload(['min_attempt' => 1]);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_null_min_attempt_passes(): void
    {
        $data = $this->validPayload(['min_attempt' => null]);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_overrides_built_in_with_valid_key_passes(): void
    {
        $data = $this->validPayload(['overrides_built_in' => 'critical_failure']);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_overrides_built_in_with_unknown_key_fails(): void
    {
        $data = $this->validPayload(['overrides_built_in' => 'nonexistent_rule']);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_null_overrides_built_in_passes(): void
    {
        $data = $this->validPayload(['overrides_built_in' => null]);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_negative_position_fails(): void
    {
        $data = $this->validPayload(['position' => -1]);

        $validator = $this->validate($data);

        self::assertTrue($validator->fails());
    }

    public function test_zero_position_passes(): void
    {
        $data = $this->validPayload(['position' => 0]);

        $validator = $this->validate($data);

        self::assertTrue($validator->passes());
    }

    public function test_build_entity_returns_managed_alert_rule(): void
    {
        $data = array_merge($this->validPayload([
            'trigger' => AlertTrigger::FailureCategory->value,
        ]), [
            'trigger_value' => 'critical',
            'window' => '10m',
            'min_attempt' => 3,
        ]);

        $request = SaveAlertRuleRequest::create('/test', 'POST', $data);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(\Illuminate\Routing\Redirector::class));
        $request->validateResolved();

        $entity = $request->buildEntity(42);

        self::assertSame(42, $entity->id());
        self::assertSame('my_custom_rule', $entity->key());
        self::assertTrue($entity->isEnabled());
        self::assertSame(AlertTrigger::FailureCategory, $entity->rule()->trigger);
        self::assertSame(5, $entity->rule()->threshold);
        self::assertSame(10, $entity->rule()->cooldownMinutes);
        self::assertSame('critical', $entity->rule()->triggerValue);
        self::assertSame('10m', $entity->rule()->window);
        self::assertSame(3, $entity->rule()->minAttempt);
        self::assertSame(['slack'], $entity->rule()->channels);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'key' => 'my_custom_rule',
            'trigger' => AlertTrigger::FailureRate->value,
            'threshold' => 5,
            'cooldown_minutes' => 10,
            'channels' => ['slack'],
            'enabled' => true,
        ], $overrides);
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = SaveAlertRuleRequest::create('/test', 'POST', $data);
        $request->setContainer($this->app);

        return Validator::make($data, $request->rules());
    }
}
