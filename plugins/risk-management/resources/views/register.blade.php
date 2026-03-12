<section class="module-screen">
    @if ($can_manage_risks)
        <div class="surface-card" id="risk-editor">
            <div class="row-between" style="margin-bottom:14px;">
                <div>
                    <div class="eyebrow">Create</div>
                    <div class="screen-title" style="font-size:26px;">New Risk</div>
                </div>
            </div>

            <form class="upload-form" method="POST" action="{{ $create_route }}">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="plugin.risk-management.root">
                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field">
                        <label class="field-label" for="risk-title">Title</label>
                        <input class="field-input" id="risk-title" name="title" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="risk-category">Category</label>
                        <input class="field-input" id="risk-category" name="category" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="risk-inherent">Inherent score</label>
                        <input class="field-input" id="risk-inherent" name="inherent_score" type="number" min="0" max="100" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="risk-residual">Residual score</label>
                        <input class="field-input" id="risk-residual" name="residual_score" type="number" min="0" max="100" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="risk-asset">Linked asset</label>
                        <select class="field-select" id="risk-asset" name="linked_asset_id">
                            <option value="">No linked asset</option>
                            @foreach ($asset_options as $asset)
                                <option value="{{ $asset['id'] }}">{{ $asset['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="risk-control">Linked control</label>
                        <input class="field-input" id="risk-control" name="linked_control_id">
                    </div>
                    <div class="field">
                        <label class="field-label" for="risk-scope">Scope</label>
                        <select class="field-select" id="risk-scope" name="scope_id">
                            <option value="">Organization-wide</option>
                            @foreach ($scope_options as $scope)
                                <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="risk-owner">Owner actor</label>
                        <select class="field-select" id="risk-owner" name="owner_actor_id">
                            <option value="">No owner</option>
                            @foreach ($owner_actor_options as $actor)
                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label" for="risk-treatment">Treatment summary</label>
                        <input class="field-input" id="risk-treatment" name="treatment" required>
                    </div>
                </div>

                <div class="action-cluster" style="margin-top:14px;">
                    <button class="button button-primary" type="submit">Create Risk</button>
                </div>
            </form>
        </div>
    @endif

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Risks</div><div class="metric-value">{{ count($risks) }}</div></div>
        <div class="metric-card"><div class="metric-label">Assessing</div><div class="metric-value">{{ collect($risks)->where('state', 'assessing')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Accepted</div><div class="metric-value">{{ collect($risks)->where('state', 'accepted')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Artifacts</div><div class="metric-value">{{ collect($risks)->sum(fn ($risk) => count($risk['artifacts'])) }}</div></div>
    </div>

    <div class="table-card">
        <table class="entity-table">
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
                            <div class="entity-title">{{ $risk['title'] }}</div>
                            <div class="entity-id">{{ $risk['id'] }}</div>
                            <div class="table-note">{{ $risk['treatment'] }}</div>
                        </td>
                        <td>{{ $risk['category'] }}</td>
                        <td>
                            <div><strong>Inherent:</strong> {{ $risk['inherent_score'] }}</div>
                            <div><strong>Residual:</strong> {{ $risk['residual_score'] }}</div>
                        </td>
                        <td>
                            @if ($risk['owner_assignment'] !== null)
                                <div>{{ $risk['owner_assignment']['display_name'] }}</div>
                                <div class="table-note">{{ $risk['owner_assignment']['kind'] }}</div>
                            @else
                                <span class="muted-note">No owner assigned</span>
                            @endif
                        </td>
                        <td>
                            <div>Asset: {{ $risk['linked_asset_label'] ?? ($risk['linked_asset_id'] !== '' ? $risk['linked_asset_id'] : 'None') }}</div>
                            <div>Control: {{ $risk['linked_control_id'] }}</div>
                        </td>
                        <td>
                            @forelse ($risk['artifacts'] as $artifact)
                                <div class="data-item" style="margin-bottom:8px;">
                                    <div class="entity-title">{{ $artifact['label'] }}</div>
                                    <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                </div>
                            @empty
                                <span class="muted-note">No artifacts yet</span>
                            @endforelse
                            @if ($can_manage_risks)
                                <form class="upload-form" method="POST" action="{{ $risk['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.risk-management.root">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="evidence">
                                    <input type="text" name="label" placeholder="Evidence label">
                                    <input type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Attach Evidence</button>
                                </form>
                            @endif
                        </td>
                        <td><span class="pill">{{ $risk['state'] }}</span></td>
                        <td>
                            @if ($risk['transitions'] !== [])
                                <div class="action-cluster">
                                    @foreach ($risk['transitions'] as $transition)
                                        <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $risk['transition_route']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.risk-management.root">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @else
                                <span class="muted-note">View-only access</span>
                            @endif

                            @if ($can_manage_risks)
                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                    <form class="upload-form" method="POST" action="{{ $risk['update_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.risk-management.root">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Title</label>
                                            <input class="field-input" name="title" value="{{ $risk['title'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Category</label>
                                            <input class="field-input" name="category" value="{{ $risk['category'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Inherent score</label>
                                            <input class="field-input" name="inherent_score" type="number" min="0" max="100" value="{{ $risk['inherent_score'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Residual score</label>
                                            <input class="field-input" name="residual_score" type="number" min="0" max="100" value="{{ $risk['residual_score'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked asset</label>
                                            <select class="field-select" name="linked_asset_id">
                                                <option value="">No linked asset</option>
                                                @foreach ($asset_options as $asset)
                                                    <option value="{{ $asset['id'] }}" @selected($risk['linked_asset_id'] === $asset['id'])>{{ $asset['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked control</label>
                                            <input class="field-input" name="linked_control_id" value="{{ $risk['linked_control_id'] }}">
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Scope</label>
                                            <select class="field-select" name="scope_id">
                                                <option value="">Organization-wide</option>
                                                @foreach ($scope_options as $scope)
                                                    <option value="{{ $scope['id'] }}" @selected($risk['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner actor</label>
                                            <select class="field-select" name="owner_actor_id">
                                                <option value="">Keep current owner</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}" @selected(($risk['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Treatment summary</label>
                                            <input class="field-input" name="treatment" value="{{ $risk['treatment'] }}" required>
                                        </div>
                                        <div class="action-cluster">
                                            <button class="button button-secondary" type="submit">Save Changes</button>
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
