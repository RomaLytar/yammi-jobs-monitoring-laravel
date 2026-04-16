<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Yammi\JobsMonitor\Application\DTO\SettingType;
use Yammi\JobsMonitor\Application\Service\SettingRegistry;

/**
 * Validates the general settings update payload.
 *
 * The form sends fields as `settings[group][key]` — flat HTML inputs
 * that PHP parses into a nested array. Boolean fields use a hidden
 * `0` + checkbox `1` pattern so unchecked = `"0"`.
 *
 * @internal
 */
final class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SettingRegistry $registry */
        $registry = $this->container->make(SettingRegistry::class);

        $rules = [];

        foreach ($registry->groups() as $groupKey => $group) {
            foreach ($group['settings'] as $def) {
                $field = "settings.{$groupKey}.{$def->key}";

                $fieldRules = match ($def->type) {
                    SettingType::Boolean => ['required', 'boolean'],
                    SettingType::Integer => ['required', 'integer', "min:{$def->min}", "max:{$def->max}"],
                    SettingType::Float => ['required', 'numeric', "min:{$def->min}", "max:{$def->max}"],
                    SettingType::String => ['nullable', 'string', 'max:255'],
                };

                $fieldRules = array_filter($fieldRules, static fn ($r): bool => $r !== 'min:' && $r !== 'max:');

                if ($def->options !== null) {
                    $fieldRules[] = 'regex:/^([*0-9\/,-]+\s+){4}[*0-9\/,-]+$/';
                }

                if ($def->pattern !== null) {
                    $fieldRules[] = 'regex:/^'.$def->pattern.'$/';
                }

                $rules[$field] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, array<string, bool|int|float|string>>
     */
    public function settings(): array
    {
        /** @var SettingRegistry $registry */
        $registry = $this->container->make(SettingRegistry::class);

        /** @var array<string, array<string, mixed>> $raw */
        $raw = (array) $this->input('settings', []);

        $result = [];

        foreach ($registry->groups() as $groupKey => $group) {
            foreach ($group['settings'] as $def) {
                $value = $raw[$groupKey][$def->key] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                $result[$groupKey][$def->key] = match ($def->type) {
                    SettingType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    SettingType::Integer => (int) $value,
                    SettingType::Float => (float) $value,
                    SettingType::String => (string) $value,
                };
            }
        }

        return $result;
    }
}
