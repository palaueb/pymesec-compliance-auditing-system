<style>
    .pill-draft    { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-review   { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-approved { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-archived { background: rgba(31,42,34,0.06);   color: var(--muted); }
</style>

<section class="module-screen">
    <div class="surface-card">
        <div class="entity-title">Control review board</div>
        <div class="table-note" style="margin-top:6px;">This view stays focused on workflow state, notifications, and review history. Open a control from the Controls Catalog when you need to manage transitions, evidence, or requirement mappings.</div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Control</th>
                    <th>Current state</th>
                    <th>Transitions</th>
                    <th>Notifications</th>
                    <th>Artifacts</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $row['control']['name'] }}</div>
                            <div class="entity-id">{{ $row['control']['id'] }}</div>
                        </td>
                        <td>
                            @php $wfPill = match($row['instance']->currentState) {
                                'draft'    => 'pill-draft',
                                'review'   => 'pill-review',
                                'approved' => 'pill-approved',
                                'archived' => 'pill-archived',
                                default    => '',
                            }; @endphp
                            <span class="pill {{ $wfPill }}">{{ $row['instance']->currentState }}</span>
                        </td>
                        <td>
                            @if ($row['history'] === [])
                                <span class="muted-note">No transitions yet</span>
                            @else
                                <div class="data-stack">
                                    @foreach ($row['history'] as $history)
                                        <div class="data-item">
                                            <span class="table-note">{{ $history->transitionKey }}:</span>
                                            {{ $history->fromState }} → {{ $history->toState }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            @php $matches = collect($row['notifications'])->filter(fn ($notification) => ($notification->metadata['control_id'] ?? null) === $row['control']['id']); @endphp
                            @if ($matches->isEmpty())
                                <span class="muted-note">None</span>
                            @else
                                <div class="data-stack">
                                    @foreach ($matches as $notification)
                                        <div class="data-item">
                                            <span class="tag">{{ $notification->status }}</span>
                                            {{ $notification->title }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($row['artifacts'] === [])
                                <span class="muted-note">None</span>
                            @else
                                <div class="data-stack">
                                    @foreach ($row['artifacts'] as $artifact)
                                        <div class="data-item">
                                            <div class="entity-title">{{ $artifact['label'] }}</div>
                                            <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><span class="muted-note">No controls with workflow activity yet.</span></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
