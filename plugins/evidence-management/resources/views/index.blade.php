<section class="module-screen">
    @if (is_array($selected_evidence))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Evidence</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_evidence['title'] }}</h2>
                    <div class="table-note">{{ ucfirst(str_replace('-', ' ', $selected_evidence['evidence_kind'])) }} · {{ ucfirst($selected_evidence['status']) }}</div>
                </div>
                <div class="action-cluster">
                    <a class="button button-ghost" href="{{ $evidence_list_url }}">Back to evidence</a>
                    <span class="pill">{{ $selected_evidence['status'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Linked records</div><div class="metric-value">{{ count($selected_evidence['links']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Validity end</div><div class="metric-value" style="font-size:18px;">{{ $selected_evidence['valid_until'] !== '' ? $selected_evidence['valid_until'] : 'Open' }}</div></div>
                <div class="metric-card"><div class="metric-label">Review due</div><div class="metric-value" style="font-size:18px;">{{ $selected_evidence['review_due_on'] !== '' ? $selected_evidence['review_due_on'] : 'Not set' }}</div></div>
                <div class="metric-card"><div class="metric-label">Artifact</div><div class="metric-value" style="font-size:18px;">{{ $selected_evidence['artifact']['original_filename'] ?? 'Missing' }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Evidence summary</div>
                    <div class="body-copy" style="margin-top:10px;">{{ $selected_evidence['summary'] !== '' ? $selected_evidence['summary'] : 'No summary yet.' }}</div>
                    @if (is_array($selected_evidence['artifact']))
                        <div class="data-stack" style="margin-top:12px;">
                            <div class="data-item">
                                <div class="entity-title">{{ $selected_evidence['artifact']['label'] }}</div>
                                <div class="table-note">{{ $selected_evidence['artifact']['original_filename'] }} · {{ $selected_evidence['artifact']['artifact_type'] }}</div>
                                <div class="table-note">{{ $selected_evidence['artifact']['media_type'] }} · {{ number_format($selected_evidence['artifact']['size_bytes']) }} bytes</div>
                                <div class="table-note">SHA-256 {{ $selected_evidence['artifact']['sha256'] }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Validation</div>
                    <div class="data-stack" style="margin-top:10px;">
                        <div class="data-item">
                            <div class="entity-title">{{ $selected_evidence['validated_at'] !== '' ? $selected_evidence['validated_at'] : 'Not validated yet' }}</div>
                            <div class="table-note">{{ $selected_evidence['validated_by_principal_id'] !== '' ? $selected_evidence['validated_by_principal_id'] : 'No validator recorded' }}</div>
                        </div>
                        <div class="data-item">
                            <div class="entity-title">Notes</div>
                            <div class="table-note">{{ $selected_evidence['validation_notes'] !== '' ? $selected_evidence['validation_notes'] : 'No validation notes yet.' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="surface-card" style="padding:14px;">
                <div class="metric-label">Linked records</div>
                <div class="data-stack" style="margin-top:10px;">
                    @forelse ($selected_evidence['link_rows'] as $link)
                        <div class="data-item">
                            <div class="row-between">
                                <div>
                                    <div class="entity-title">{{ $link['domain_label'] }}</div>
                                    <div class="table-note">{{ ucfirst(str_replace('-', ' ', $link['domain_type'])) }}</div>
                                </div>
                                @if ($link['open_url'] !== null)
                                    <a class="button button-secondary" href="{{ $link['open_url'] }}">Open record</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <span class="muted-note">This evidence is not linked to any workspace record yet.</span>
                    @endforelse
                </div>
            </div>

            @if ($can_manage_evidence)
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Update evidence</div>
                    <form class="upload-form" method="POST" action="{{ route('plugin.evidence-management.update', ['evidenceId' => $selected_evidence['id']]) }}" enctype="multipart/form-data" style="margin-top:10px;">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                        <input type="hidden" name="organization_id" value="{{ $selected_evidence['organization_id'] }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.evidence-management.root">
                        <input type="hidden" name="evidence_id" value="{{ $selected_evidence['id'] }}">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? '' }}">

                        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                            <div class="field">
                                <label class="field-label">Title</label>
                                <input class="field-input" name="title" value="{{ $selected_evidence['title'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Scope</label>
                                <select class="field-select" name="scope_id">
                                    <option value="">All organization scopes</option>
                                    @foreach ($scope_options as $scope)
                                        <option value="{{ $scope['id'] }}" @selected($selected_evidence['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">Summary</label>
                                <textarea class="field-input" name="summary" rows="4">{{ $selected_evidence['summary'] }}</textarea>
                            </div>
                            <div class="field">
                                <label class="field-label">Kind</label>
                                <select class="field-select" name="evidence_kind" required>
                                    @foreach ($kind_options as $key => $label)
                                        <option value="{{ $key }}" @selected($selected_evidence['evidence_kind'] === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">Status</label>
                                <select class="field-select" name="status" required>
                                    @foreach ($status_options as $key => $label)
                                        <option value="{{ $key }}" @selected($selected_evidence['status'] === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">Valid from</label>
                                <input class="field-input" type="date" name="valid_from" value="{{ $selected_evidence['valid_from'] }}">
                            </div>
                            <div class="field">
                                <label class="field-label">Valid until</label>
                                <input class="field-input" type="date" name="valid_until" value="{{ $selected_evidence['valid_until'] }}">
                            </div>
                            <div class="field">
                                <label class="field-label">Review due</label>
                                <input class="field-input" type="date" name="review_due_on" value="{{ $selected_evidence['review_due_on'] }}">
                            </div>
                            <div class="field">
                                <label class="field-label">Validated on</label>
                                <input class="field-input" type="date" name="validated_at" value="{{ $selected_evidence['validated_at'] }}">
                            </div>
                            <div class="field">
                                <label class="field-label">Validated by principal</label>
                                <input class="field-input" name="validated_by_principal_id" value="{{ $selected_evidence['validated_by_principal_id'] }}">
                            </div>
                            <div class="field">
                                <label class="field-label">Replace with existing artifact</label>
                                <select class="field-select" name="existing_artifact_id">
                                    <option value="">Keep current artifact</option>
                                    @foreach ($artifact_options as $artifact)
                                        <option value="{{ $artifact['id'] }}">{{ $artifact['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">Or upload replacement file</label>
                                <input class="field-input" type="file" name="artifact">
                            </div>
                            <div class="field">
                                <label class="field-label">Linked records</label>
                                <select class="field-select" name="link_targets[]" multiple size="12">
                                    @foreach ($link_option_groups as $group)
                                        <optgroup label="{{ $group['label'] }}">
                                            @foreach ($group['items'] as $item)
                                                <option value="{{ $item['value'] }}" @selected(collect($selected_evidence['links'])->contains(fn ($link) => $item['value'] === $link['domain_type'].':'.$link['domain_id']))>{{ $item['label'] }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">Validation notes</label>
                                <textarea class="field-input" name="validation_notes" rows="4">{{ $selected_evidence['validation_notes'] }}</textarea>
                            </div>
                        </div>
                        <div class="action-cluster" style="margin-top:14px;">
                            <button class="button button-secondary" type="submit">Save evidence</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    @else
        @if ($can_manage_evidence)
            <div class="surface-card" id="evidence-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">Evidence</div>
                        <div class="entity-title" style="font-size:24px;">New evidence record</div>
                    </div>
                </div>
                <form class="upload-form" method="POST" action="{{ $create_route }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.evidence-management.root">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? '' }}">
                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">Title</label>
                            <input class="field-input" name="title" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Scope</label>
                            <select class="field-select" name="scope_id">
                                <option value="">All organization scopes</option>
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}">{{ $scope['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label">Summary</label>
                            <textarea class="field-input" name="summary" rows="4"></textarea>
                        </div>
                        <div class="field">
                            <label class="field-label">Kind</label>
                            <select class="field-select" name="evidence_kind" required>
                                @foreach ($kind_options as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Status</label>
                            <select class="field-select" name="status" required>
                                @foreach ($status_options as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Valid from</label>
                            <input class="field-input" type="date" name="valid_from">
                        </div>
                        <div class="field">
                            <label class="field-label">Valid until</label>
                            <input class="field-input" type="date" name="valid_until">
                        </div>
                        <div class="field">
                            <label class="field-label">Review due</label>
                            <input class="field-input" type="date" name="review_due_on">
                        </div>
                        <div class="field">
                            <label class="field-label">Validated on</label>
                            <input class="field-input" type="date" name="validated_at">
                        </div>
                        <div class="field">
                            <label class="field-label">Validated by principal</label>
                            <input class="field-input" name="validated_by_principal_id">
                        </div>
                        <div class="field">
                            <label class="field-label">Use existing artifact</label>
                            <select class="field-select" name="existing_artifact_id">
                                <option value="">Upload a new file instead</option>
                                @foreach ($artifact_options as $artifact)
                                    <option value="{{ $artifact['id'] }}">{{ $artifact['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Upload file</label>
                            <input class="field-input" type="file" name="artifact">
                        </div>
                        <div class="field">
                            <label class="field-label">Linked records</label>
                            <select class="field-select" name="link_targets[]" multiple size="12">
                                @foreach ($link_option_groups as $group)
                                    <optgroup label="{{ $group['label'] }}">
                                        @foreach ($group['items'] as $item)
                                            <option value="{{ $item['value'] }}">{{ $item['label'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label">Validation notes</label>
                            <textarea class="field-input" name="validation_notes" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="action-cluster" style="margin-top:14px;">
                        <button class="button button-primary" type="submit">Save evidence</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">Evidence records</div><div class="metric-value">{{ $metrics['records'] }}</div></div>
            <div class="metric-card"><div class="metric-label">Approved</div><div class="metric-value">{{ $metrics['approved'] }}</div></div>
            <div class="metric-card"><div class="metric-label">Expiring soon</div><div class="metric-value">{{ $metrics['expiring'] }}</div></div>
            <div class="metric-card"><div class="metric-label">Linked</div><div class="metric-value">{{ $metrics['linked'] }}</div></div>
        </div>

        <div class="surface-note">
            Use the evidence library to promote uploaded artifacts into governed evidence, set validity windows, and reuse them across assessments, controls, findings, and plans.
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Evidence</th>
                        <th>Status</th>
                        <th>Validity</th>
                        <th>Artifact</th>
                        <th>Linked records</th>
                        <th>{{ $can_manage_evidence ? 'Actions' : 'Details' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $record)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $record['title'] }}</div>
                                <div class="table-note">{{ ucfirst(str_replace('-', ' ', $record['evidence_kind'])) }}</div>
                            </td>
                            <td>
                                <span class="pill">{{ $record['status'] }}</span>
                            </td>
                            <td>
                                <div class="table-note">{{ $record['valid_from'] !== '' ? $record['valid_from'] : 'Open start' }}</div>
                                <div class="table-note">{{ $record['valid_until'] !== '' ? $record['valid_until'] : 'Open end' }}</div>
                            </td>
                            <td>
                                @if (is_array($record['artifact']))
                                    <div class="entity-title">{{ $record['artifact']['label'] }}</div>
                                    <div class="table-note">{{ $record['artifact']['original_filename'] }}</div>
                                @else
                                    <span class="muted-note">Missing artifact</span>
                                @endif
                            </td>
                            <td>
                                @if ($record['links'] === [])
                                    <span class="muted-note">Unlinked</span>
                                @else
                                    <div class="table-note">{{ count($record['links']) }} records</div>
                                @endif
                            </td>
                            <td><a class="button button-secondary" href="{{ $record['open_url'] }}">Edit details</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
