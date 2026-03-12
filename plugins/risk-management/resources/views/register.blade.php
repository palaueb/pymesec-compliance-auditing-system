<style>
    .risk-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--line);
        background: rgba(255,255,255,0.56);
    }
    .risk-table th,
    .risk-table td {
        padding: 12px 14px;
        border-bottom: 1px solid rgba(31,42,34,0.08);
        text-align: left;
        vertical-align: top;
        font-size: 14px;
    }
    .risk-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
        background: rgba(255,255,255,0.42);
    }
    .risk-pill {
        display: inline-flex;
        border: 1px solid var(--line);
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        text-transform: capitalize;
        background: rgba(255,255,255,0.55);
    }
    .risk-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .risk-actions form { margin: 0; }
    .risk-btn {
        border: 1px solid var(--line);
        background: rgba(255,255,255,0.68);
        color: var(--ink);
        padding: 6px 10px;
        border-radius: 4px;
        font-weight: 700;
        cursor: pointer;
    }
    .risk-upload {
        margin-top: 8px;
        display: grid;
        gap: 8px;
    }
    .risk-upload input[type="text"],
    .risk-upload input[type="file"] {
        width: 100%;
        border: 1px solid var(--line);
        background: rgba(255,255,255,0.78);
        color: var(--ink);
        padding: 8px 10px;
        border-radius: 4px;
    }
</style>
<table class="risk-table">
    <thead>
        <tr>
            <th>Risk</th>
            <th>Category</th>
            <th>Scores</th>
            <th>Owner</th>
            <th>Links</th>
            <th>Evidence</th>
            <th>State</th>
            <th>{{ $can_manage_risks ? 'Transitions' : 'Access' }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($risks as $risk)
            <tr>
                <td>
                    <strong>{{ $risk['title'] }}</strong><br>
                    <span style="color:var(--muted);font-size:12px;">{{ $risk['id'] }}</span><br>
                    <span style="color:var(--muted);font-size:12px;">{{ $risk['treatment'] }}</span>
                </td>
                <td>{{ $risk['category'] }}</td>
                <td>
                    <strong>Inherent:</strong> {{ $risk['inherent_score'] }}<br>
                    <strong>Residual:</strong> {{ $risk['residual_score'] }}
                </td>
                <td>
                    @if ($risk['owner_assignment'] !== null)
                        {{ $risk['owner_assignment']['display_name'] }}<br>
                        <span style="color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:0.06em;">{{ $risk['owner_assignment']['kind'] }}</span>
                    @else
                        <span style="color:var(--muted);font-size:12px;">No owner assigned</span>
                    @endif
                </td>
                <td>
                    Asset: {{ $risk['linked_asset_id'] }}<br>
                    Control: {{ $risk['linked_control_id'] }}
                </td>
                <td>
                    @forelse ($risk['artifacts'] as $artifact)
                        <div><strong>{{ $artifact['label'] }}</strong><br><span style="color:var(--muted);font-size:12px;">{{ $artifact['original_filename'] }}</span></div>
                    @empty
                        <span style="color:var(--muted);font-size:12px;">No artifacts yet</span>
                    @endforelse
                    @if ($can_manage_risks)
                        <form class="risk-upload" method="POST" action="{{ $risk['artifact_upload_route'] }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.risk-management.root">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                            <input type="hidden" name="artifact_type" value="evidence">
                            <input type="text" name="label" placeholder="Evidence label">
                            <input type="file" name="artifact" required>
                            <button class="risk-btn" type="submit">Attach Evidence</button>
                        </form>
                    @endif
                </td>
                <td><span class="risk-pill">{{ $risk['state'] }}</span></td>
                <td>
                    @if ($risk['transitions'] !== [])
                        <div class="risk-actions">
                            @foreach ($risk['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $risk['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.risk-management.root">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="risk-btn" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
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
