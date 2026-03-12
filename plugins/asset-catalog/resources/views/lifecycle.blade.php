<style>
    .workflow-stack { display: grid; gap: 14px; }
    .workflow-card {
        background: rgba(255,255,255,0.54);
        border: 1px solid var(--line);
        padding: 16px;
    }
    .workflow-header {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
    }
    .workflow-history {
        margin-top: 14px;
        border-top: 1px solid rgba(31,42,34,0.08);
        padding-top: 10px;
        display: grid;
        gap: 8px;
    }
    .workflow-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        color: var(--muted);
        font-size: 13px;
    }
</style>
<section class="workflow-stack">
    @foreach ($rows as $row)
        <article class="workflow-card">
            <div class="workflow-header">
                <div>
                    <strong>{{ $row['asset']['name'] }}</strong><br>
                    <span style="color:var(--muted);font-size:12px;">{{ $row['asset']['id'] }}</span>
                </div>
                <span class="state-pill">{{ $row['instance']->currentState }}</span>
            </div>

            <div class="workflow-history">
                @forelse ($row['history'] as $history)
                    <div class="workflow-row">
                        <span>{{ $history->transitionKey }}</span>
                        <span>{{ $history->fromState }} -> {{ $history->toState }}</span>
                    </div>
                @empty
                    <div class="workflow-row">
                        <span>No transitions recorded yet.</span>
                    </div>
                @endforelse
            </div>
        </article>
    @endforeach
</section>
