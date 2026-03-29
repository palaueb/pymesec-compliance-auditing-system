<section class="module-screen compact">
    <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">Linked principals</div><div class="metric-value">{{ $metrics['linked_principals'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Functional actors</div><div class="metric-value">{{ $metrics['functional_actors'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Active assignments</div><div class="metric-value">{{ $metrics['active_assignments'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Governed objects</div><div class="metric-value">{{ $metrics['governed_objects'] }}</div></div>
    </div>

    @if (! $has_organization_context)
        <div class="surface-note">
            Select an organization first. Delegated governance is always resolved inside one organization context.
        </div>
    @else
        <div class="surface-note">
            Use this area for delegated accountability and visibility governance inside the active organization. Platform-wide setup stays in <code>/admin</code>.
        </div>
    @endif

    <div class="overview-grid" style="grid-template-columns:1.1fr 0.9fr;">
        <div class="surface-card" style="padding:16px; display:grid; gap:14px;">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:22px;">Workspace governance entrypoint</h2>
                    <p class="screen-subtitle">These surfaces govern people-to-actor links, ownership, and object-scoped visibility without mixing them into platform administration.</p>
                </div>
            </div>

            <div class="data-stack">
                @foreach ($quick_links as $link)
                    <div class="data-item">
                        <div class="entity-title">{{ $link['label'] }}</div>
                        <div class="table-note">{{ $link['copy'] }}</div>
                        <div class="action-cluster" style="margin-top:10px;">
                            <a class="button button-secondary" href="{{ $link['url'] }}">Open</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="surface-card" style="padding:16px; display:grid; gap:14px;">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:22px;">Boundary rules</h2>
                    <p class="screen-subtitle">Keep the access model explicit so platform operators and delegated workspace owners do not share the same operational surface by accident.</p>
                </div>
            </div>

            @foreach ($boundaries as $boundary)
                <div class="data-item">
                    <div class="entity-title">{{ $boundary['title'] }}</div>
                    <div class="table-note">{{ $boundary['copy'] }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @foreach ($boundary['items'] as $item)
                            <div class="table-note">- {{ $item }}</div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
