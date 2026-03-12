<style>
    .assignment-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--line);
        background: rgba(255,255,255,0.56);
    }
    .assignment-table th,
    .assignment-table td {
        padding: 12px 14px;
        border-bottom: 1px solid rgba(31,42,34,0.08);
        text-align: left;
        vertical-align: top;
        font-size: 14px;
    }
    .assignment-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
        background: rgba(255,255,255,0.42);
    }
</style>
<table class="assignment-table">
    <thead>
        <tr>
            <th>Assignment</th>
            <th>Actor</th>
            <th>Domain object</th>
            <th>Type</th>
            <th>Scope</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>
                    <strong>{{ $row['assignment']->id }}</strong>
                </td>
                <td>
                    @if ($row['actor'] !== null)
                        {{ $row['actor']->displayName }}
                    @else
                        <span style="color:var(--muted);font-size:12px;">Unknown actor</span>
                    @endif
                </td>
                <td>{{ $row['assignment']->domainObjectType }}:{{ $row['assignment']->domainObjectId }}</td>
                <td>{{ $row['assignment']->assignmentType }}</td>
                <td>{{ $row['assignment']->scopeId ?? 'organization-wide' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
