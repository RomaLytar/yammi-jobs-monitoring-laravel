@php
    /**
     * Per-row kebab actions for a failed job row.
     *
     * Props:
     *   - $job           (array) formatted job row: uuid, short_class, attempt, has_payload
     *   - $retryEnabled  (bool)  config flag: jobs-monitor.store_payload
     */
    $uuid = $job['uuid'];
    $canRetry = $retryEnabled && ! empty($job['has_payload']);

    $actions = [];
    if ($canRetry) {
        $actions[] = [
            'type' => 'form',
            'url' => route('jobs-monitor.dlq.retry', ['uuid' => $uuid]),
            'icon' => 'refresh-cw',
            'iconColor' => 'text-brand',
            'label' => 'Retry',
        ];
        $actions[] = [
            'type' => 'link',
            'url' => route('jobs-monitor.dlq.edit', ['uuid' => $uuid]),
            'icon' => 'pencil',
            'iconColor' => 'text-brand',
            'label' => 'Edit & retry',
        ];
    }
    $actions[] = [
        'type' => 'link',
        'url' => route('jobs-monitor.detail', ['uuid' => $uuid, 'attempt' => $job['attempt']]),
        'icon' => 'eye',
        'iconColor' => 'text-muted-foreground',
        'label' => 'View details',
    ];
    $actions[] = [
        'type' => 'confirm',
        'url' => route('jobs-monitor.dlq.delete', ['uuid' => $uuid]),
        'icon' => 'trash-2',
        'label' => 'Delete',
        'danger' => true,
        'confirm' => [
            'title' => 'Delete job record?',
            'body' => 'Remove '.$job['short_class'].' (attempt '.$job['attempt'].') from the monitor. This cannot be undone.',
            'submitLabel' => 'Delete',
            'variant' => 'danger',
        ],
    ];
@endphp
@include('jobs-monitor::partials.kebab-actions', [
    'actions' => $actions,
    'emptyLabel' => 'no payload',
])
