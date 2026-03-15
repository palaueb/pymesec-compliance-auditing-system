<section class="module-screen">
    @if (is_array($selected_policy))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Policy</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_policy['title'] }}</h2>
                    <div class="table-note">{{ $selected_policy['id'] }} · {{ $selected_policy['version_label'] }}</div>
                    <div class="table-note">{{ $selected_policy['area'] }}</div>
                </div>
                <div class="action-cluster">
                    <a class="button button-ghost" href="{{ $policies_list_url }}">Back to policies</a>
                    <span class="pill">{{ $selected_policy['state'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Exceptions</div><div class="metric-value">{{ $selected_policy['exception_count'] }}</div></div>
                <div class="metric-card"><div class="metric-label">Approved exceptions</div><div class="metric-value">{{ $selected_policy['active_exception_count'] }}</div></div>
                <div class="metric-card"><div class="metric-label">Documents</div><div class="metric-value">{{ count($selected_policy['artifacts']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Review due</div><div class="metric-value" style="font-size:20px;">{{ $selected_policy['review_due_on'] !== '' ? $selected_policy['review_due_on'] : 'No date' }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Overview</div>
                    <div class="table-note" style="margin-top:10px;">{{ $selected_policy['statement'] }}</div>
                    <div class="table-note">Owner: {{ $selected_policy['owner_assignment']['display_name'] ?? 'No owner assigned' }}</div>
                    <div class="table-note">
                        Control:
                        @if ($selected_policy['linked_control_url'] !== null)
                            <a href="{{ $selected_policy['linked_control_url'] }}">{{ $selected_policy['linked_control_label'] ?? $selected_policy['linked_control_id'] }}</a>
                        @else
                            {{ $selected_policy['linked_control_id'] !== '' ? $selected_policy['linked_control_id'] : 'None' }}
                        @endif
                    </div>
                    <div class="table-note">Scope: {{ $selected_policy['scope_id'] !== '' ? $selected_policy['scope_id'] : 'Organization-wide' }}</div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Workflow</div>
                    @if ($selected_policy['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($selected_policy['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_policy['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                                    <input type="hidden" name="policy_id" value="{{ $selected_policy['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">View-only access</div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($selected_policy['history'] as $history)
                            <div class="data-item">
                                <div class="entity-title">{{ $history->transitionKey }}</div>
                                <div class="table-note">{{ $history->fromState }} -> {{ $history->toState }}</div>
                            </div>
                        @empty
                            <span class="muted-note">No transitions recorded yet</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">Documents</div>
                        @if ($can_manage_policies)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Attach document</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_policy['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                                    <input type="hidden" name="policy_id" value="{{ $selected_policy['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="document">
                                    <input class="field-input" type="text" name="label" placeholder="Document label">
                                    <input class="field-input" type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Upload document</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_policy['artifacts'] as $artifact)
                            <div class="data-item">
                                <div class="entity-title">{{ $artifact['label'] }}</div>
                                <div class="table-note">{{ $artifact['original_filename'] }}</div>
                            </div>
                        @empty
                            <span class="muted-note">No documents yet</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">Exceptions</div>
                        @if ($can_manage_policies)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Add exception</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_policy['exception_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                                    <input type="hidden" name="policy_id" value="{{ $selected_policy['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <div class="field">
                                        <label class="field-label">Exception title</label>
                                        <input class="field-input" name="title" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Linked finding</label>
                                        <select class="field-select" name="linked_finding_id">
                                            <option value="">No linked finding</option>
                                            @foreach ($finding_options as $finding)
                                                <option value="{{ $finding['id'] }}">{{ $finding['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Expires on</label>
                                        <input class="field-input" name="expires_on" type="date">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Owner actor</label>
                                        <select class="field-select" name="owner_actor_id">
                                            <option value="">No owner</option>
                                            @foreach ($owner_actor_options as $actor)
                                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Rationale</label>
                                        <textarea class="field-input" name="rationale" rows="2" required></textarea>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Compensating control</label>
                                        <textarea class="field-input" name="compensating_control" rows="2"></textarea>
                                    </div>
                                    <div class="action-cluster">
                                        <button class="button button-secondary" type="submit">Create exception</button>
                                    </div>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_policy['exceptions'] as $exception)
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start;">
                                    <div>
                                        <div class="entity-title">{{ $exception['title'] }}</div>
                                        <div class="table-note">{{ ucfirst($exception['state']) }}{{ $exception['expires_on'] !== '' ? ' · expires '.$exception['expires_on'] : '' }}</div>
                                        <div class="table-note">{{ $exception['owner_assignment']['display_name'] ?? 'No owner assigned' }}</div>
                                    </div>
                                    <span class="pill">{{ $exception['state'] }}</span>
                                </div>
                                @if ($exception['linked_finding_url'] !== null)
                                    <div class="table-note" style="margin-top:6px;">Finding: <a href="{{ $exception['linked_finding_url'] }}">{{ $exception['linked_finding_label'] ?? $exception['linked_finding_id'] }}</a></div>
                                @endif
                                <div class="action-cluster" style="margin-top:10px;">
                                    <a class="button button-secondary" href="{{ $exception['open_url'] }}">Edit details</a>
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">No exceptions yet</span>
                        @endforelse
                    </div>
                </div>
            </div>

            @if ($can_manage_policies)
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Edit policy</div>
                    <form class="upload-form" method="POST" action="{{ $selected_policy['update_route'] }}" style="margin-top:10px;">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                        <input type="hidden" name="policy_id" value="{{ $selected_policy['id'] }}">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                            <div class="field">
                                <label class="field-label">Title</label>
                                <input class="field-input" name="title" value="{{ $selected_policy['title'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Area</label>
                                <input class="field-input" name="area" value="{{ $selected_policy['area'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Version</label>
                                <input class="field-input" name="version_label" value="{{ $selected_policy['version_label'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Review due</label>
                                <input class="field-input" name="review_due_on" type="date" value="{{ $selected_policy['review_due_on'] }}">
                            </div>
                            <div class="field">
                                <label class="field-label">Linked control</label>
                                <select class="field-select" name="linked_control_id">
                                    <option value="">No linked control</option>
                                    @foreach ($control_options as $control)
                                        <option value="{{ $control['id'] }}" @selected($selected_policy['linked_control_id'] === $control['id'])>{{ $control['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">Scope</label>
                                <select class="field-select" name="scope_id">
                                    <option value="">Organization-wide</option>
                                    @foreach ($scope_options as $scope)
                                        <option value="{{ $scope['id'] }}" @selected($selected_policy['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">Owner actor</label>
                                <select class="field-select" name="owner_actor_id">
                                    <option value="">Keep current owner</option>
                                    @foreach ($owner_actor_options as $actor)
                                        <option value="{{ $actor['id'] }}" @selected(($selected_policy['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">Statement</label>
                                <textarea class="field-input" name="statement" rows="4" required>{{ $selected_policy['statement'] }}</textarea>
                            </div>
                        </div>
                        <div class="action-cluster" style="margin-top:14px;">
                            <button class="button button-secondary" type="submit">Save changes</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    @else
        @if ($can_manage_policies)
            <div class="surface-card" id="policy-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">Create</div>
                        <div class="entity-title" style="font-size:24px;">New policy</div>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $create_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label" for="policy-title">Title</label>
                            <input class="field-input" id="policy-title" name="title" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-area">Area</label>
                            <input class="field-input" id="policy-area" name="area" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-version">Version</label>
                            <input class="field-input" id="policy-version" name="version_label" value="v1.0" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-review-due">Review due</label>
                            <input class="field-input" id="policy-review-due" name="review_due_on" type="date">
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-control">Linked control</label>
                            <select class="field-select" id="policy-control" name="linked_control_id">
                                <option value="">No linked control</option>
                                @foreach ($control_options as $control)
                                    <option value="{{ $control['id'] }}">{{ $control['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-scope">Scope</label>
                            <select class="field-select" id="policy-scope" name="scope_id">
                                <option value="">Organization-wide</option>
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-owner">Owner actor</label>
                            <select class="field-select" id="policy-owner" name="owner_actor_id">
                                <option value="">No owner</option>
                                @foreach ($owner_actor_options as $actor)
                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label" for="policy-statement">Statement</label>
                            <textarea class="field-input" id="policy-statement" name="statement" rows="4" required></textarea>
                        </div>
                    </div>

                    <div class="action-cluster" style="margin-top:14px;">
                        <button class="button button-primary" type="submit">Create policy</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">Policies</div><div class="metric-value">{{ count($policies) }}</div></div>
            <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ collect($policies)->where('state', 'active')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Under review</div><div class="metric-value">{{ collect($policies)->where('state', 'review')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Approved exceptions</div><div class="metric-value">{{ collect($policies)->sum('active_exception_count') }}</div></div>
        </div>

        <div class="surface-card">
            <div class="table-note">Open a policy to manage workflow, linked controls, documents and approved exceptions.</div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Policy</th>
                        <th>Area</th>
                        <th>Owner</th>
                        <th>Linked control</th>
                        <th>Review due</th>
                        <th>State</th>
                        <th>{{ $can_manage_policies ? 'Actions' : 'Access' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($policies as $policy)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $policy['title'] }}</div>
                                <div class="entity-id">{{ $policy['id'] }} · {{ $policy['version_label'] }}</div>
                                <div class="table-note">{{ $policy['statement'] }}</div>
                                <div class="table-note">{{ $policy['exception_count'] }} exceptions</div>
                            </td>
                            <td>{{ $policy['area'] }}</td>
                            <td>
                                @if ($policy['owner_assignment'] !== null)
                                    <div>{{ $policy['owner_assignment']['display_name'] }}</div>
                                    <div class="table-note">{{ $policy['owner_assignment']['kind'] }}</div>
                                @else
                                    <span class="muted-note">No owner assigned</span>
                                @endif
                            </td>
                            <td>
                                @if ($policy['linked_control_url'] !== null)
                                    <a href="{{ $policy['linked_control_url'] }}">{{ $policy['linked_control_label'] ?? $policy['linked_control_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $policy['linked_control_id'] !== '' ? $policy['linked_control_id'] : 'None' }}</span>
                                @endif
                            </td>
                            <td>{{ $policy['review_due_on'] !== '' ? $policy['review_due_on'] : 'No review date' }}</td>
                            <td><span class="pill">{{ $policy['state'] }}</span></td>
                            <td>
                                <a class="button button-secondary" href="{{ $policy['open_url'] }}">Edit details</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
