<style>
    .pill-assessing { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-accepted  { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-closed    { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-archived  { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-open      { background: rgba(239,68,68,0.14);  color: #991b1b; }
</style>

<section class="module-screen">
    <div class="surface-card">
        <div class="table-note">Workflow state history for all risks. Open a risk from the Risk register to manage transitions and evidence.</div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Risk</th>
                    <th>Current state</th>
                    <th>Transitions</th>
                    <th>Artifacts</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $row['risk']['title'] }}</div>
                            <div class="entity-id">{{ $row['risk']['id'] }}</div>
                        </td>
                        <td>
                            @php $wfPill = match($row['instance']->currentState) {
                                'assessing' => 'pill-assessing',
                                'accepted'  => 'pill-accepted',
                                'closed'    => 'pill-closed',
                                'archived'  => 'pill-archived',
                                'open'      => 'pill-open',
                                default     => '',
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
                        <td colspan="4"><span class="muted-note">No risks with workflow activity yet.</span></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
