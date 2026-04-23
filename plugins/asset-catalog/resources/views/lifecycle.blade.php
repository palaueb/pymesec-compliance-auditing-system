<style>
    .pill-active    { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-review    { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-draft     { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-retired   { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-disposed  { background: rgba(239,68,68,0.08);  color: #991b1b; }
</style>

<section class="module-screen compact">
    <div class="surface-card">
        <div class="entity-title">{{ __('Asset lifecycle board') }}</div>
        <div class="table-note" style="margin-top:6px;">{{ __('This board stays focused on lifecycle state and transition history. Open an asset from the catalog when you need to manage ownership, evidence, or asset maintenance.') }}</div>
    </div>

    <div class="workflow-stack">
        @forelse ($rows as $row)
            <article class="workflow-card">
                <div class="workflow-header">
                    <div>
                        <div class="entity-title">{{ $row['asset']['name'] }}</div>
                        <div class="entity-id">{{ $row['asset']['id'] }}</div>
                    </div>
                    @php $lcPill = match($row['instance']->currentState) {
                        'active'   => 'pill-active',
                        'review'   => 'pill-review',
                        'draft'    => 'pill-draft',
                        'retired'  => 'pill-retired',
                        'disposed' => 'pill-disposed',
                        default    => '',
                    }; @endphp
                    <span class="pill {{ $lcPill }}">{{ $row['instance']->currentState }}</span>
                </div>

                <div class="workflow-history">
                    @forelse ($row['history'] as $history)
                        <div class="workflow-row">
                            <span class="table-note">{{ $history->transitionKey }}</span>
                            <span>{{ $history->fromState }} → {{ $history->toState }}</span>
                        </div>
                    @empty
                        <div class="muted-note">{{ __('No transitions recorded yet.') }}</div>
                    @endforelse
                </div>
            </article>
        @empty
            <div class="surface-card">
                <span class="muted-note">{{ __('No assets with lifecycle activity yet.') }}</span>
            </div>
        @endforelse
    </div>
</section>
