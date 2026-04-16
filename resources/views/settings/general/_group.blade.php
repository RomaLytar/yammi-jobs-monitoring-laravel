@php
    $input = 'block w-full h-9 rounded-md border border-input bg-card px-3 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring transition-[box-shadow,border-color]';
@endphp

<div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs">
    <div class="flex items-start gap-3 p-5 border-b border-border">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
            <i data-lucide="{{ $group->icon }}" class="text-[16px]"></i>
        </span>
        <div>
            <h2 class="text-base font-semibold tracking-tight">{{ $group->label }}</h2>
            <p class="mt-1 text-sm text-muted-foreground">{{ $group->description }}</p>
        </div>
    </div>

    <div class="divide-y divide-border">
        @foreach($group->settings as $setting)
            @php
                $fieldName = "settings[{$setting->group}][{$setting->key}]";
                $fieldId = "{$setting->group}_{$setting->key}";
                $isBoolean = $setting->type->value === 'boolean';
                $isString = $setting->type->value === 'string';
                $isNumber = in_array($setting->type->value, ['integer', 'float'], true);
                $hasOptions = !empty($setting->options);
            @endphp

            <div class="p-5 {{ $isBoolean ? 'flex items-start justify-between gap-6' : 'space-y-3' }}">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <label for="{{ $fieldId }}" class="text-sm font-medium">
                            {{ $setting->label }}
                        </label>
                        @include('jobs-monitor::settings.partials.source-badge', ['source' => $setting->source->value])
                        @if($setting->suffix)
                            <span class="text-xs text-muted-foreground">({{ $setting->suffix }})</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                        {{ $setting->description }}
                    </p>
                    @if($setting->source->value === 'default')
                        <p class="mt-1.5 text-xs text-muted-foreground/70">
                            Using package default. Save to store in database.
                        </p>
                    @endif
                    @error($fieldName)
                        <p class="mt-1.5 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                </div>

                @if($isBoolean)
                    <div class="flex-shrink-0 pt-0.5">
                        <input type="hidden" name="{{ $fieldName }}" value="0">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox"
                                   name="{{ $fieldName }}"
                                   id="{{ $fieldId }}"
                                   value="1"
                                   {{ $setting->value ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-muted rounded-full peer
                                        peer-checked:bg-brand
                                        peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-card
                                        after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                        after:bg-white after:rounded-full after:h-5 after:w-5
                                        after:shadow-sm after:transition-transform
                                        peer-checked:after:translate-x-5
                                        transition-colors"></div>
                        </label>
                    </div>
                @elseif($isNumber)
                    <div class="max-w-xs">
                        <input type="number"
                               name="{{ $fieldName }}"
                               id="{{ $fieldId }}"
                               value="{{ old("settings.{$setting->group}.{$setting->key}", $setting->value) }}"
                               @if($setting->min !== null) min="{{ $setting->min }}" @endif
                               @if($setting->max !== null) max="{{ $setting->max }}" @endif
                               @if($setting->type->value === 'float') step="0.01" @else step="1" @endif
                               placeholder="{{ $setting->default }}"
                               class="{{ $input }}">
                        @if($setting->min !== null && $setting->max !== null)
                            <p class="mt-1 text-xs text-muted-foreground">
                                Range: {{ $setting->min }} – {{ $setting->max }}
                            </p>
                        @endif
                    </div>
                @elseif($hasOptions)
                    <div class="max-w-xs">
                        @include('jobs-monitor::settings.general._select-with-custom', [
                            'name' => $fieldName,
                            'value' => old("settings.{$setting->group}.{$setting->key}", $setting->value),
                            'fieldId' => $fieldId,
                            'options' => $setting->options,
                            'description' => 'Cron format: minute hour day month weekday. Examples: */3 * * * * (every 3 min), 0 */2 * * * (every 2 hours)',
                        ])
                    </div>
                @elseif($isString)
                    <div class="max-w-md">
                        <input type="text"
                               name="{{ $fieldName }}"
                               id="{{ $fieldId }}"
                               value="{{ old("settings.{$setting->group}.{$setting->key}", $setting->value) }}"
                               maxlength="255"
                               placeholder="{{ $setting->default !== '' ? $setting->default : 'Not set' }}"
                               @if($setting->pattern) pattern="{{ $setting->pattern }}" title="Only letters, numbers, colons, dots, hyphens and underscores" @endif
                               class="{{ $input }}">
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
