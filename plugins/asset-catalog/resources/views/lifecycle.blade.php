<section class="module-screen compact">
    <div class="workflow-stack">
        @foreach ($rows as $row)
            <article class="workflow-card">
                <div class="workflow-header">
                    <div>
                        <div class="entity-title">{{ $row['asset']['name'] }}</div>
                        <div class="entity-id">{{ $row['asset']['id'] }}</div>
                    </div>
                    <span class="pill">{{ $row['instance']->currentState }}</span>
                </div>

                <div class="workflow-history">
                    @forelse ($row['history'] as $history)
                        <div class="workflow-row">
                            <span>{{ $history->transitionKey }}</span>
                            <span>{{ $history->fromState }} -> {{ $history->toState }}</span>
                        </div>
                    @empty
                        <div class="muted-note">No transitions recorded yet.</div>
                    @endforelse
                </div>
            </article>
        @endforeach
    </div>
</section>
