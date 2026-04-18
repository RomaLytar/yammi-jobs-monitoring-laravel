@php
    $isEditing = $editing === $b->key;
    $channelIcon = [
        'slack' => 'slack',
        'mail' => 'mail',
        'pagerduty' => 'siren',
        'opsgenie' => 'shield-alert',
        'webhook' => 'webhook',
    ];

    $rowActions = [
        [
            'type' => 'form',
            'url' => route('jobs-monitor.settings.alerts.built-in.toggle', ['key' => $b->key]),
            'icon' => $b->effectivelyEnabled ? 'bell-off' : 'bell-ring',
            'iconColor' => 'text-brand',
            'label' => $b->effectivelyEnabled ? 'Disable' : 'Enable',
        ],
        [
            'type' => 'link',
            'url' => route('jobs-monitor.settings.alerts', ['editing' => $b->key]).'#rule-'.$b->key,
            'icon' => 'pencil',
            'iconColor' => 'text-brand',
            'label' => 'Edit',
        ],
    ];
    if ($b->hasOverride) {
        $rowActions[] = [
            'type' => 'confirm',
            'url' => route('jobs-monitor.settings.alerts.built-in.reset', ['key' => $b->key]),
            'icon' => 'rotate-ccw',
            'label' => 'Reset to default',
            'danger' => true,
            'confirm' => [
                'title' => 'Reset alert rule?',
                'body' => 'Discard overrides for '.$b->key.' and return to the shipped default.',
                'submitLabel' => 'Reset',
                'variant' => 'danger',
            ],
        ];
    }
@endphp

<tr class="{{ $isEditing ? 'bg-brand/5' : 'hover:bg-muted/40 transition-colors' }}">
    <td class="px-4 py-3 align-top">
        <code class="text-xs bg-muted px-1.5 py-0.5 rounded font-mono">{{ $b->key }}</code>
        @if($b->hasOverride)
            <div class="mt-1 inline-flex items-center gap-1 text-[11px] text-brand">
                <i data-lucide="pencil" class="text-[10px]"></i>
                customized
            </div>
        @endif
    </td>
    <td class="hidden md:table-cell px-4 py-3 align-top">
        <span class="font-medium">{{ $b->trigger->label() }}</span>
        @if($b->triggerValue !== null)
            <span class="text-muted-foreground">: {{ $b->triggerValue }}</span>
        @endif
    </td>
    <td class="hidden lg:table-cell px-4 py-3 align-top tabular-nums">{{ $b->threshold }}</td>
    <td class="hidden lg:table-cell px-4 py-3 align-top tabular-nums text-muted-foreground">{{ $b->window ?? '—' }}</td>
    <td class="hidden xl:table-cell px-4 py-3 align-top">
        <div class="flex flex-wrap gap-1">
            @foreach($b->channels as $ch)
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[11px] rounded-md bg-muted text-muted-foreground ring-1 ring-inset ring-border">
                    <i data-lucide="{{ $channelIcon[$ch] ?? 'radio' }}" class="text-[11px]"></i>
                    {{ $ch }}
                </span>
            @endforeach
        </div>
    </td>
    <td class="px-4 py-3 align-top">
        @if($b->effectivelyEnabled)
            <span class="inline-flex items-center gap-1 rounded-md bg-success/10 text-success ring-1 ring-inset ring-success/25 px-2 py-0.5 text-xs font-medium">
                <i data-lucide="check-circle-2" class="text-[12px]"></i>
                Enabled
            </span>
        @else
            <span class="inline-flex items-center gap-1 rounded-md bg-muted text-muted-foreground ring-1 ring-inset ring-border px-2 py-0.5 text-xs font-medium">
                <i data-lucide="power-off" class="text-[12px]"></i>
                Disabled
            </span>
        @endif
    </td>
    <td class="px-3 py-3 align-top text-right">
        @include('jobs-monitor::partials.kebab-actions', ['actions' => $rowActions])
    </td>
</tr>

@if($isEditing)
<tr class="bg-brand/5" id="rule-{{ $b->key }}">
    <td colspan="7" class="px-4 py-4">
        @include('jobs-monitor::settings.alerts._built-in-edit-form', ['b' => $b])
    </td>
</tr>
@endif
