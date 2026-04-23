<section class="module-screen compact">
    <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">{{ __('core.governance.metric.linked_principals') }}</div><div class="metric-value">{{ $metrics['linked_principals'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.governance.metric.functional_actors') }}</div><div class="metric-value">{{ $metrics['functional_actors'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.governance.metric.active_assignments') }}</div><div class="metric-value">{{ $metrics['active_assignments'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.governance.metric.governed_objects') }}</div><div class="metric-value">{{ $metrics['governed_objects'] }}</div></div>
    </div>

    @if (! $has_organization_context)
        <div class="surface-note">{{ __('core.governance.no_organization') }}</div>
    @else
        <div class="surface-note">{{ __('core.governance.summary') }}</div>
    @endif

    <div class="overview-grid" style="grid-template-columns:1.1fr 0.9fr;">
        <div class="surface-card" style="padding:16px; display:grid; gap:14px;">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:22px;">{{ __('core.governance.entrypoint.title') }}</h2>
                    <p class="screen-subtitle">{{ __('core.governance.entrypoint.subtitle') }}</p>
                </div>
            </div>

            <div class="data-stack">
                @foreach ($quick_links as $link)
                    <div class="data-item">
                        <div class="entity-title">{{ $link['label'] }}</div>
                        <div class="table-note">{{ $link['copy'] }}</div>
                        <div class="action-cluster" style="margin-top:10px;">
                            <a class="button button-secondary" href="{{ $link['url'] }}">{{ __('core.actions.open') }}</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="surface-card" style="padding:16px; display:grid; gap:14px;">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:22px;">{{ __('core.governance.boundaries.title') }}</h2>
                    <p class="screen-subtitle">{{ __('core.governance.boundaries.subtitle') }}</p>
                </div>
            </div>

            @foreach ($boundaries as $boundary)
                <div class="data-item">
                    <div class="entity-title">{{ $boundary['title'] }}</div>
                    <div class="table-note">{{ $boundary['copy'] }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @foreach ($boundary['items'] as $item)
                            <div class="table-note">{{ $item }}</div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
