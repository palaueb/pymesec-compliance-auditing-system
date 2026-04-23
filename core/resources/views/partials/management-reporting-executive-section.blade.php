<div class="table-card">
    <div class="screen-header">
        <div>
            <h2 class="screen-title" style="font-size:24px;">{{ $title }}</h2>
            <p class="screen-subtitle">{{ $subtitle }}</p>
        </div>
        @if (is_string($section['section_url'] ?? null))
            <a class="button button-secondary" href="{{ $section['section_url'] }}">{{ __('core.actions.open') }}</a>
        @endif
    </div>
    <div class="summary-grid">
        @forelse ($section['summary_metrics'] as $metric)
            <div class="summary-item"><span>{{ $metric['label'] }}</span><strong>{{ $metric['value'] }}</strong></div>
        @empty
            <div class="table-note">{{ $section['empty_copy'] }}</div>
        @endforelse
    </div>
    <div class="overview-grid" style="grid-template-columns:repeat({{ max(count($section['breakdowns'] ?? []), 1) }}, minmax(0, 1fr)); margin-top:16px;">
        @foreach ($section['breakdowns'] as $breakdown)
            <div class="surface-card" style="padding:14px;">
                <div class="eyebrow">{{ $breakdown['title'] }}</div>
                <div class="breakdown-list" style="margin-top:10px;">
                    @forelse ($breakdown['rows'] as $row)
                        <div class="breakdown-row"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                    @empty
                        <div class="table-note">{{ $section['empty_copy'] }}</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
