<style>
    .review-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--line);
        background: rgba(255,255,255,0.56);
    }
    .review-table th,
    .review-table td {
        padding: 12px 14px;
        border-bottom: 1px solid rgba(31,42,34,0.08);
        text-align: left;
        vertical-align: top;
        font-size: 14px;
    }
    .review-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
        background: rgba(255,255,255,0.42);
    }
</style>
<table class="review-table">
    <thead>
        <tr>
            <th>Control</th>
            <th>State</th>
            <th>History</th>
            <th>Notifications</th>
            <th>Artifacts</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['control']['name'] }}</td>
                <td>{{ $row['instance']->currentState }}</td>
                <td>
                    @if ($row['history'] === [])
                        <span style="color:var(--muted);font-size:12px;">No transitions yet</span>
                    @else
                        @foreach ($row['history'] as $history)
                            <div>{{ $history->transitionKey }}: {{ $history->fromState }} -> {{ $history->toState }}</div>
                        @endforeach
                    @endif
                </td>
                <td>
                    @php($matches = collect($row['notifications'])->filter(fn ($notification) => ($notification->metadata['control_id'] ?? null) === $row['control']['id']))
                    @if ($matches->isEmpty())
                        <span style="color:var(--muted);font-size:12px;">No notifications yet</span>
                    @else
                        @foreach ($matches as $notification)
                            <div>{{ $notification->status }}: {{ $notification->title }}</div>
                        @endforeach
                    @endif
                </td>
                <td>
                    @if ($row['artifacts'] === [])
                        <span style="color:var(--muted);font-size:12px;">No artifacts yet</span>
                    @else
                        @foreach ($row['artifacts'] as $artifact)
                            <div>{{ $artifact['label'] }}: {{ $artifact['original_filename'] }}</div>
                        @endforeach
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
