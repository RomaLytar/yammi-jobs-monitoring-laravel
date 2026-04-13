<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $subject }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; color: #1f2937; line-height: 1.5; padding: 24px; background: #f9fafb;">
    <div style="max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px;">
        <h2 style="margin: 0 0 8px 0; color: #111827;">{{ $subject }}</h2>

        @if(! empty($sourceName))
            <p style="margin: 0 0 4px 0; font-size: 13px; color: #4b5563;">
                <strong>Site:</strong> {{ $sourceName }}
            </p>
        @endif
        <p style="margin: 0 0 16px 0; font-size: 13px; color: #6b7280;">
            <strong>Trigger:</strong> {{ $triggerLabel }} &middot;
            <strong>Fired at:</strong> {{ $triggeredAt->format('Y-m-d H:i:s T') }}
        </p>

        <p style="margin: 0 0 20px 0; font-size: 15px;">{{ $body }}</p>

        @if(! empty($recentFailures))
            @php
                $totalCount = $context['count'] ?? null;
                $shown = count($recentFailures);
                $moreCount = is_int($totalCount) && $totalCount > $shown ? $totalCount - $shown : 0;
            @endphp
            <h3 style="margin: 24px 0 8px 0; font-size: 14px; color: #111827; text-transform: uppercase; letter-spacing: 0.05em;">
                Recent failures
                @if($moreCount > 0)
                    <span style="color: #9ca3af; font-size: 11px; font-weight: normal; text-transform: none; letter-spacing: 0;">
                        (showing {{ $shown }} of {{ $totalCount }})
                    </span>
                @endif
            </h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 13px;">
                <thead>
                    <tr style="background: #f3f4f6; text-align: left;">
                        <th style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #4b5563;">Job</th>
                        <th style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #4b5563;">Attempt</th>
                        <th style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #4b5563;">Exception</th>
                        <th style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #4b5563;">When</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentFailures as $sample)
                        @php($detailUrl = $detailUrlBuilder($sample))
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-family: monospace;">
                                @if($detailUrl)
                                    <a href="{{ $detailUrl }}" style="color: #4f46e5; text-decoration: none;">
                                        {{ $sample->shortClass() }}
                                    </a>
                                @else
                                    {{ $sample->shortClass() }}
                                @endif
                            </td>
                            <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; color: #6b7280;">
                                #{{ $sample->attempt }}
                            </td>
                            <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; color: #374151; max-width: 280px; overflow-wrap: anywhere;">
                                {{ $sample->shortException() ?? '—' }}
                            </td>
                            <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; color: #6b7280; white-space: nowrap;">
                                {{ $sample->failedAt->format('H:i:s') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($moreCount > 0 && $dashboardUrl)
                <p style="margin: -8px 0 16px 0; font-size: 12px; color: #6b7280; text-align: right;">
                    <a href="{{ $dashboardUrl }}" style="color: #4f46e5; text-decoration: none;">
                        + {{ $moreCount }} more — open dashboard →
                    </a>
                </p>
            @endif
        @endif

        @if($dashboardUrl)
            <p style="margin: 24px 0 8px 0;">
                <a href="{{ $dashboardUrl }}" style="display: inline-block; background: #4f46e5; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 14px;">
                    Open dashboard
                </a>
            </p>
        @endif

        @if(! empty($context))
            <details style="margin-top: 24px;">
                <summary style="cursor: pointer; color: #6b7280; font-size: 12px;">Full context</summary>
                <table style="border-collapse: collapse; margin-top: 8px;">
                    @foreach($context as $key => $value)
                        <tr>
                            <td style="padding: 4px 12px 4px 0; color: #6b7280; font-size: 12px; text-transform: uppercase;">
                                {{ $key }}
                            </td>
                            <td style="padding: 4px 0; font-family: monospace; font-size: 12px;">
                                {{ is_scalar($value) ? $value : json_encode($value) }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </details>
        @endif

        <p style="margin: 32px 0 0 0; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 12px;">
            Generated by jobs-monitor. Adjust thresholds or channels in <code>config/jobs-monitor.php</code>.
        </p>
    </div>
</body>
</html>
