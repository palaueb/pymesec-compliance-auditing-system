<section class="module-screen">
    @if ($can_manage_continuity)
        <div class="surface-card" id="continuity-service-editor">
            <div class="row-between" style="margin-bottom:14px;">
                <div>
                    <div class="eyebrow">Continuity</div>
                    <div class="screen-title" style="font-size:26px;">New service</div>
                </div>
            </div>

            <form class="upload-form" method="POST" action="{{ $create_route }}">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field">
                        <label class="field-label" for="service-title">Title</label>
                        <input class="field-input" id="service-title" name="title" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="service-impact">Impact tier</label>
                        <input class="field-input" id="service-impact" name="impact_tier" placeholder="critical, high, medium" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="service-rto">RTO hours</label>
                        <input class="field-input" id="service-rto" name="recovery_time_objective_hours" type="number" min="0" max="8760" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="service-rpo">RPO hours</label>
                        <input class="field-input" id="service-rpo" name="recovery_point_objective_hours" type="number" min="0" max="8760" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="service-asset">Linked asset</label>
                        <select class="field-select" id="service-asset" name="linked_asset_id">
                            <option value="">No linked asset</option>
                            @foreach ($asset_options as $asset)
                                <option value="{{ $asset['id'] }}">{{ $asset['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="service-risk">Linked risk</label>
                        <select class="field-select" id="service-risk" name="linked_risk_id">
                            <option value="">No linked risk</option>
                            @foreach ($risk_options as $risk)
                                <option value="{{ $risk['id'] }}">{{ $risk['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="service-scope">Scope</label>
                        <select class="field-select" id="service-scope" name="scope_id">
                            <option value="">Organization-wide</option>
                            @foreach ($scope_options as $scope)
                                <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="service-owner">Owner actor</label>
                        <select class="field-select" id="service-owner" name="owner_actor_id">
                            <option value="">No owner</option>
                            @foreach ($owner_actor_options as $actor)
                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="action-cluster" style="margin-top:14px;">
                    <button class="button button-primary" type="submit">Create service</button>
                </div>
            </form>
        </div>
    @endif

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Services</div><div class="metric-value">{{ count($services) }}</div></div>
        <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ collect($services)->where('state', 'active')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Under Review</div><div class="metric-value">{{ collect($services)->where('state', 'review')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Recovery Plans</div><div class="metric-value">{{ collect($services)->sum('plan_count') }}</div></div>
        <div class="metric-card"><div class="metric-label">Dependencies</div><div class="metric-value">{{ collect($services)->sum(fn ($service) => count($service['dependencies'])) }}</div></div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Recovery Objectives</th>
                    <th>Owner</th>
                    <th>Links</th>
                    <th>Dependencies</th>
                    <th>Documents</th>
                    <th>State</th>
                    <th>{{ $can_manage_continuity ? 'Actions' : 'Access' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($services as $service)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $service['title'] }}</div>
                            <div class="entity-id">{{ $service['id'] }} · {{ $service['impact_tier'] }}</div>
                            <div class="table-note">{{ $service['plan_count'] }} recovery plans</div>
                        </td>
                        <td>
                            <div>RTO: {{ $service['recovery_time_objective_hours'] }}h</div>
                            <div>RPO: {{ $service['recovery_point_objective_hours'] }}h</div>
                        </td>
                        <td>
                            @if ($service['owner_assignment'] !== null)
                                <div>{{ $service['owner_assignment']['display_name'] }}</div>
                                <div class="table-note">{{ $service['owner_assignment']['kind'] }}</div>
                            @else
                                <span class="muted-note">No owner assigned</span>
                            @endif
                        </td>
                        <td>
                            <div>Asset: {{ $service['linked_asset_label'] ?? ($service['linked_asset_id'] !== '' ? $service['linked_asset_id'] : 'None') }}</div>
                            <div>Risk: {{ $service['linked_risk_id'] !== '' ? $service['linked_risk_id'] : 'None' }}</div>
                        </td>
                        <td>
                            <div class="data-stack">
                                @forelse ($service['dependencies'] as $dependency)
                                    <div class="data-item">
                                        <div class="entity-title">{{ $dependency['depends_on_service_title'] }}</div>
                                        <div class="table-note">{{ ucfirst($dependency['dependency_kind']) }} dependency</div>
                                        @if ($dependency['recovery_notes'] !== '')
                                            <div class="table-note">{{ $dependency['recovery_notes'] }}</div>
                                        @endif
                                    </div>
                                @empty
                                    <span class="muted-note">No dependencies mapped</span>
                                @endforelse
                            </div>

                            @if ($can_manage_continuity)
                                <form class="upload-form" method="POST" action="{{ $service['dependency_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <select class="field-select" name="depends_on_service_id" required>
                                        <option value="">Depends on...</option>
                                        @foreach ($service_options as $option)
                                            @if ($option['id'] !== $service['id'])
                                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <select class="field-select" name="dependency_kind">
                                        <option value="critical">Critical</option>
                                        <option value="supporting">Supporting</option>
                                        <option value="external">External</option>
                                    </select>
                                    <input type="text" name="recovery_notes" placeholder="Recovery note">
                                    <button class="button button-secondary" type="submit">Add dependency</button>
                                </form>
                            @endif
                        </td>
                        <td>
                            @forelse ($service['artifacts'] as $artifact)
                                <div class="data-item" style="margin-bottom:8px;">
                                    <div class="entity-title">{{ $artifact['label'] }}</div>
                                    <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                </div>
                            @empty
                                <span class="muted-note">No documents yet</span>
                            @endforelse
                            @if ($can_manage_continuity)
                                <form class="upload-form" method="POST" action="{{ $service['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="continuity-record">
                                    <input type="text" name="label" placeholder="Document label">
                                    <input type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Attach document</button>
                                </form>
                            @endif
                        </td>
                        <td><span class="pill">{{ $service['state'] }}</span></td>
                        <td>
                            @if ($service['transitions'] !== [])
                                <div class="action-cluster">
                                    @foreach ($service['transitions'] as $transition)
                                        <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $service['transition_route']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @else
                                <span class="muted-note">View-only access</span>
                            @endif

                            @if ($can_manage_continuity)
                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                    <form class="upload-form" method="POST" action="{{ $service['update_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Title</label>
                                            <input class="field-input" name="title" value="{{ $service['title'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Impact tier</label>
                                            <input class="field-input" name="impact_tier" value="{{ $service['impact_tier'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">RTO hours</label>
                                            <input class="field-input" name="recovery_time_objective_hours" type="number" min="0" max="8760" value="{{ $service['recovery_time_objective_hours'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">RPO hours</label>
                                            <input class="field-input" name="recovery_point_objective_hours" type="number" min="0" max="8760" value="{{ $service['recovery_point_objective_hours'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked asset</label>
                                            <select class="field-select" name="linked_asset_id">
                                                <option value="">No linked asset</option>
                                                @foreach ($asset_options as $asset)
                                                    <option value="{{ $asset['id'] }}" @selected($service['linked_asset_id'] === $asset['id'])>{{ $asset['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked risk</label>
                                            <select class="field-select" name="linked_risk_id">
                                                <option value="">No linked risk</option>
                                                @foreach ($risk_options as $risk)
                                                    <option value="{{ $risk['id'] }}" @selected($service['linked_risk_id'] === $risk['id'])>{{ $risk['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Scope</label>
                                            <select class="field-select" name="scope_id">
                                                <option value="">Organization-wide</option>
                                                @foreach ($scope_options as $scope)
                                                    <option value="{{ $scope['id'] }}" @selected($service['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner actor</label>
                                            <select class="field-select" name="owner_actor_id">
                                                <option value="">Keep current owner</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}" @selected(($service['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="action-cluster">
                                            <button class="button button-secondary" type="submit">Save changes</button>
                                        </div>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
