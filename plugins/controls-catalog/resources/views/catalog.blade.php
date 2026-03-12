<style>
    .control-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--line);
        background: rgba(255,255,255,0.56);
    }
    .control-table th,
    .control-table td {
        padding: 12px 14px;
        border-bottom: 1px solid rgba(31,42,34,0.08);
        text-align: left;
        vertical-align: top;
        font-size: 14px;
    }
    .control-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
        background: rgba(255,255,255,0.42);
    }
    .control-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .control-actions form { margin: 0; }
    .control-btn {
        border: 1px solid var(--line);
        background: rgba(255,255,255,0.68);
        color: var(--ink);
        padding: 6px 10px;
        border-radius: 4px;
        font-weight: 700;
        cursor: pointer;
    }
    .control-pill {
        display: inline-flex;
        border: 1px solid var(--line);
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        text-transform: capitalize;
        background: rgba(255,255,255,0.55);
    }
    .artifact-list {
        display: grid;
        gap: 6px;
    }
    .artifact-item {
        border: 1px solid var(--line);
        padding: 8px 10px;
        border-radius: 4px;
        background: rgba(255,255,255,0.62);
    }
    .artifact-upload {
        margin-top: 8px;
        display: grid;
        gap: 8px;
    }
    .artifact-upload input[type="text"],
    .artifact-upload input[type="file"] {
        width: 100%;
        border: 1px solid var(--line);
        background: rgba(255,255,255,0.78);
        color: var(--ink);
        padding: 8px 10px;
        border-radius: 4px;
    }
</style>
<table class="control-table">
    <thead>
        <tr>
            <th>Control</th>
            <th>Framework</th>
            <th>Domain</th>
            <th>Owner</th>
            <th>Evidence</th>
            <th>State</th>
            <th>{{ $can_manage_controls ? 'Transitions' : 'Access' }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($controls as $control)
            <tr>
                <td>
                    <strong>{{ $control['name'] }}</strong><br>
                    <span style="color:var(--muted);font-size:12px;">{{ $control['id'] }}</span>
                </td>
                <td>{{ $control['framework'] }}</td>
                <td>{{ $control['domain'] }}</td>
                <td>
                    @if ($control['owner_assignment'] !== null)
                        {{ $control['owner_assignment']['display_name'] }}<br>
                        <span style="color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:0.06em;">{{ $control['owner_assignment']['kind'] }}</span>
                    @else
                        <span style="color:var(--muted);font-size:12px;">No owner assigned</span>
                    @endif
                </td>
                <td>
                    <div>{{ $control['evidence'] }}</div>
                    <div class="artifact-list" style="margin-top:8px;">
                        @forelse ($control['artifacts'] as $artifact)
                            <div class="artifact-item">
                                <strong>{{ $artifact['label'] }}</strong><br>
                                <span style="color:var(--muted);font-size:12px;">{{ $artifact['original_filename'] }} · {{ $artifact['artifact_type'] }}</span>
                            </div>
                        @empty
                            <span style="color:var(--muted);font-size:12px;">No artifacts yet</span>
                        @endforelse
                    </div>
                    @if ($can_manage_controls)
                        <form class="artifact-upload" method="POST" action="{{ $control['artifact_upload_route'] }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                            <input type="hidden" name="artifact_type" value="evidence">
                            <input type="text" name="label" placeholder="Evidence label">
                            <input type="file" name="artifact" required>
                            <button class="control-btn" type="submit">Attach Evidence</button>
                        </form>
                    @endif
                </td>
                <td><span class="control-pill">{{ $control['state'] }}</span></td>
                <td>
                    @if ($control['transitions'] !== [])
                        <div class="control-actions">
                            @foreach ($control['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $control['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="control-btn" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <span style="color:var(--muted);font-size:12px;">View-only access</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
