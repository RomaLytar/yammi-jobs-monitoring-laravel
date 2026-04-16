<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Request;

use Illuminate\Support\Facades\Validator;
use Yammi\JobsMonitor\Application\DTO\SettingType;
use Yammi\JobsMonitor\Application\Service\SettingRegistry;
use Yammi\JobsMonitor\Infrastructure\Http\Request\UpdateGeneralSettingsRequest;
use Yammi\JobsMonitor\Tests\TestCase;

final class UpdateGeneralSettingsRequestTest extends TestCase
{
    public function test_complete_valid_payload_passes(): void
    {
        $validator = $this->validate(['settings' => $this->allDefaults()]);

        self::assertTrue($validator->passes(), implode('; ', $validator->errors()->all()));
    }

    public function test_boolean_setting_accepts_zero_and_one(): void
    {
        foreach (['0', '1', 0, 1, true, false] as $value) {
            $data = $this->payloadWith('general', 'store_payload', $value);

            $validator = $this->validate($data);

            self::assertFalse(
                $validator->errors()->has('settings.general.store_payload'),
                'Boolean setting should accept: ' . var_export($value, true),
            );
        }
    }

    public function test_boolean_setting_rejects_non_boolean(): void
    {
        $data = $this->payloadWith('general', 'store_payload', 'yes');

        $validator = $this->validate($data);

        self::assertTrue($validator->errors()->has('settings.general.store_payload'));
    }

    public function test_integer_setting_below_min_fails(): void
    {
        $data = $this->payloadWith('general', 'retention_days', 0);

        $validator = $this->validate($data);

        self::assertTrue($validator->errors()->has('settings.general.retention_days'));
    }

    public function test_integer_setting_above_max_fails(): void
    {
        $data = $this->payloadWith('general', 'retention_days', 366);

        $validator = $this->validate($data);

        self::assertTrue($validator->errors()->has('settings.general.retention_days'));
    }

    public function test_integer_setting_at_min_boundary_passes(): void
    {
        $data = $this->payloadWith('general', 'retention_days', 1);

        $validator = $this->validate($data);

        self::assertFalse($validator->errors()->has('settings.general.retention_days'));
    }

    public function test_integer_setting_at_max_boundary_passes(): void
    {
        $data = $this->payloadWith('general', 'retention_days', 365);

        $validator = $this->validate($data);

        self::assertFalse($validator->errors()->has('settings.general.retention_days'));
    }

    public function test_float_setting_accepts_valid_decimal(): void
    {
        $data = $this->payloadWith('duration_anomaly', 'short_factor', '0.1');

        $validator = $this->validate($data);

        self::assertFalse($validator->errors()->has('settings.duration_anomaly.short_factor'));
    }

    public function test_float_setting_below_min_fails(): void
    {
        $data = $this->payloadWith('duration_anomaly', 'short_factor', '0.001');

        $validator = $this->validate($data);

        self::assertTrue($validator->errors()->has('settings.duration_anomaly.short_factor'));
    }

    public function test_float_setting_above_max_fails(): void
    {
        $data = $this->payloadWith('duration_anomaly', 'long_factor', '101.0');

        $validator = $this->validate($data);

        self::assertTrue($validator->errors()->has('settings.duration_anomaly.long_factor'));
    }

    public function test_string_setting_with_valid_cron_pattern_passes(): void
    {
        $data = $this->payloadWith('workers', 'schedule_cron', '*/5 * * * *');

        $validator = $this->validate($data);

        self::assertFalse($validator->errors()->has('settings.workers.schedule_cron'));
    }

    public function test_string_setting_with_invalid_cron_pattern_fails(): void
    {
        $data = $this->payloadWith('workers', 'schedule_cron', 'not a cron');

        $validator = $this->validate($data);

        self::assertTrue($validator->errors()->has('settings.workers.schedule_cron'));
    }

    public function test_string_setting_with_regex_pattern_validates(): void
    {
        $data = $this->payloadWith('alerts_schedule', 'schedule_queue', 'monitoring');

        $validator = $this->validate($data);

        self::assertFalse($validator->errors()->has('settings.alerts_schedule.schedule_queue'));
    }

    public function test_string_setting_with_invalid_pattern_fails(): void
    {
        $data = $this->payloadWith('alerts_schedule', 'schedule_queue', 'invalid queue name!');

        $validator = $this->validate($data);

        self::assertTrue($validator->errors()->has('settings.alerts_schedule.schedule_queue'));
    }

    public function test_nullable_string_setting_accepts_null(): void
    {
        $data = $this->payloadWith('alerts_schedule', 'schedule_queue', null);

        $validator = $this->validate($data);

        self::assertFalse($validator->errors()->has('settings.alerts_schedule.schedule_queue'));
    }

    public function test_settings_accessor_casts_boolean_values(): void
    {
        $request = UpdateGeneralSettingsRequest::create('/test', 'POST', [
            'settings' => $this->allDefaults(),
        ]);
        $request->setContainer($this->app);

        $settings = $request->settings();

        self::assertTrue($settings['general']['store_payload']);
    }

    public function test_settings_accessor_casts_integer_values(): void
    {
        $request = UpdateGeneralSettingsRequest::create('/test', 'POST', [
            'settings' => $this->allDefaults(),
        ]);
        $request->setContainer($this->app);

        $settings = $request->settings();

        self::assertSame(30, $settings['general']['retention_days']);
        self::assertSame(3, $settings['general']['max_tries']);
    }

    public function test_settings_accessor_casts_float_values(): void
    {
        $request = UpdateGeneralSettingsRequest::create('/test', 'POST', [
            'settings' => $this->allDefaults(),
        ]);
        $request->setContainer($this->app);

        $settings = $request->settings();

        self::assertSame(0.1, $settings['duration_anomaly']['short_factor']);
        self::assertSame(3.0, $settings['duration_anomaly']['long_factor']);
    }

    public function test_settings_accessor_skips_empty_string_values(): void
    {
        $defaults = $this->allDefaults();
        $defaults['alerts_schedule']['schedule_queue'] = '';

        $request = UpdateGeneralSettingsRequest::create('/test', 'POST', [
            'settings' => $defaults,
        ]);
        $request->setContainer($this->app);

        $settings = $request->settings();

        self::assertArrayNotHasKey('schedule_queue', $settings['alerts_schedule'] ?? []);
    }

    public function test_rules_are_generated_for_all_registry_groups(): void
    {
        $request = UpdateGeneralSettingsRequest::create('/test', 'POST');
        $request->setContainer($this->app);

        $rules = $request->rules();

        /** @var SettingRegistry $registry */
        $registry = $this->app->make(SettingRegistry::class);

        foreach ($registry->groups() as $groupKey => $group) {
            foreach ($group['settings'] as $def) {
                $field = "settings.{$groupKey}.{$def->key}";
                self::assertArrayHasKey($field, $rules, "Missing rule for {$field}");
            }
        }
    }

    public function test_missing_required_integer_fails(): void
    {
        $defaults = $this->allDefaults();
        unset($defaults['general']['retention_days']);
        $data = ['settings' => $defaults];

        $validator = $this->validate($data);

        self::assertTrue($validator->errors()->has('settings.general.retention_days'));
    }

    public function test_non_numeric_integer_setting_fails(): void
    {
        $data = $this->payloadWith('general', 'retention_days', 'abc');

        $validator = $this->validate($data);

        self::assertTrue($validator->errors()->has('settings.general.retention_days'));
    }

    public function test_missing_required_boolean_fails(): void
    {
        $defaults = $this->allDefaults();
        unset($defaults['general']['store_payload']);
        $data = ['settings' => $defaults];

        $validator = $this->validate($data);

        self::assertTrue($validator->errors()->has('settings.general.store_payload'));
    }

    public function test_max_tries_at_min_boundary_passes(): void
    {
        $data = $this->payloadWith('general', 'max_tries', 1);

        $validator = $this->validate($data);

        self::assertFalse($validator->errors()->has('settings.general.max_tries'));
    }

    public function test_max_tries_at_max_boundary_passes(): void
    {
        $data = $this->payloadWith('general', 'max_tries', 100);

        $validator = $this->validate($data);

        self::assertFalse($validator->errors()->has('settings.general.max_tries'));
    }

    public function test_max_tries_above_max_fails(): void
    {
        $data = $this->payloadWith('general', 'max_tries', 101);

        $validator = $this->validate($data);

        self::assertTrue($validator->errors()->has('settings.general.max_tries'));
    }

    /**
     * Build a complete defaults payload from the registry.
     *
     * @return array<string, array<string, mixed>>
     */
    private function allDefaults(): array
    {
        /** @var SettingRegistry $registry */
        $registry = $this->app->make(SettingRegistry::class);

        $payload = [];

        foreach ($registry->groups() as $groupKey => $group) {
            foreach ($group['settings'] as $def) {
                $payload[$groupKey][$def->key] = match ($def->type) {
                    SettingType::Boolean => $def->default ? '1' : '0',
                    default => (string) $def->default,
                };
            }
        }

        return $payload;
    }

    /**
     * Build a complete payload with one setting overridden.
     *
     * @return array<string, mixed>
     */
    private function payloadWith(string $group, string $key, mixed $value): array
    {
        $defaults = $this->allDefaults();
        $defaults[$group][$key] = $value;

        return ['settings' => $defaults];
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = UpdateGeneralSettingsRequest::create('/test', 'POST', $data);
        $request->setContainer($this->app);

        return Validator::make($data, $request->rules());
    }
}
