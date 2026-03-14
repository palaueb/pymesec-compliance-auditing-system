<section class="module-screen compact">
    <div class="surface-note">
        {{ __('core.support.intro') }}
    </div>

    @if ($issues !== [])
        <div class="surface-note error">
            <strong>{{ __('core.support.issues_title') }}</strong>
            <ul style="margin:10px 0 0 18px;">
                @foreach ($issues as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="overview-grid" style="grid-template-columns:1fr 1.2fr;">
        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:26px;">{{ __('core.support.index_title') }}</h2>
                    <p class="screen-subtitle">{{ __('core.support.index_subtitle') }}</p>
                </div>
            </div>

            <div class="data-stack">
                @foreach ($concept_index as $concept)
                    <a class="rail-item" href="#concept-{{ $concept['id'] }}" style="text-decoration:none; color:inherit;">
                        <div class="entity-title">{{ $concept['label'] }}</div>
                        <div class="table-note">{{ $concept['summary'] }}</div>
                        <div class="entity-id">{{ $concept['category'] }}</div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="surface-card" style="padding:18px;">
            <div class="eyebrow">{{ __('core.support.how_to_read_title') }}</div>
            <h2 class="screen-title" style="font-size:26px; margin-top:6px;">{{ __('core.support.how_to_read_heading') }}</h2>
            <p class="screen-subtitle">{{ __('core.support.how_to_read_copy') }}</p>
            <div class="data-stack" style="margin-top:14px;">
                <div class="data-item">{{ __('core.support.read_step_organization') }}</div>
                <div class="data-item">{{ __('core.support.read_step_access') }}</div>
                <div class="data-item">{{ __('core.support.read_step_records') }}</div>
                <div class="data-item">{{ __('core.support.read_step_relations') }}</div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:26px;">{{ __('core.support.guide_title') }}</h2>
                <p class="screen-subtitle">{{ __('core.support.guide_subtitle') }}</p>
            </div>
        </div>

        <div class="data-stack">
                @foreach ($guide as $section)
                    <section id="guide-{{ $section['id'] }}" class="surface-card" style="padding:16px;">
                        <div>
                            <div>
                                <div class="entity-title">{{ $section['title'] }}</div>
                                <div class="table-note" style="margin-top:6px;">{{ $section['summary'] }}</div>
                            </div>
                        </div>

                        @if ($section['body'] !== [])
                        <div class="data-stack" style="margin-top:12px;">
                            @foreach ($section['body'] as $paragraph)
                                <div class="data-item">{{ $paragraph }}</div>
                            @endforeach
                        </div>
                    @endif

                    @if ($section['concept_ids'] !== [])
                        <div class="action-cluster" style="margin-top:12px;">
                            @foreach ($section['concept_ids'] as $conceptId)
                                <a class="pill" href="#concept-{{ $conceptId }}">{{ $conceptId }}</a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </div>

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:26px;">{{ __('core.support.reference_title') }}</h2>
                <p class="screen-subtitle">{{ __('core.support.reference_subtitle') }}</p>
            </div>
        </div>

        <div class="data-stack">
            @foreach ($concepts as $concept)
                <section id="concept-{{ $concept['id'] }}" class="surface-card" style="padding:18px;">
                    <div class="row-between" style="align-items:flex-start; gap:16px;">
                        <div>
                            <div class="entity-title">{{ $concept['label'] }}</div>
                            <div class="entity-id">{{ $concept['id'] }} · {{ $concept['category'] }}</div>
                        </div>
                        <a class="button button-ghost" href="#top">{{ __('core.support.back_to_top') }}</a>
                    </div>

                    <p class="body-copy" style="margin-top:12px;">{{ $concept['summary'] }}</p>

                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); margin-top:16px;">
                        <div class="surface-card" style="padding:16px;">
                            <div class="field-label">{{ __('core.support.why_title') }}</div>
                            <div class="body-copy" style="margin-top:8px;">{{ $concept['why_it_exists'] }}</div>
                        </div>
                        <div class="surface-card" style="padding:16px;">
                            <div class="field-label">{{ __('core.support.how_title') }}</div>
                            @if ($concept['how_to_use'] !== [])
                                <ul style="margin:10px 0 0 18px;">
                                    @foreach ($concept['how_to_use'] as $step)
                                        <li class="body-copy" style="margin-bottom:6px;">{{ $step }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="body-copy" style="margin-top:8px;">{{ __('core.support.no_how_copy') }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="surface-card" style="padding:16px; margin-top:16px;">
                        <div class="field-label">{{ __('core.support.related_title') }}</div>
                        @if ($concept['relations'] !== [])
                            <div class="data-stack" style="margin-top:10px;">
                                @foreach ($concept['relations'] as $relation)
                                    <div class="data-item">
                                        <strong>{{ $relation['type'] }}</strong>:
                                        <a href="#concept-{{ $relation['target'] }}">{{ $relation['target_label'] }}</a>
                                        @if ($relation['summary'] !== '')
                                            <div class="table-note" style="margin-top:4px;">{{ $relation['summary'] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="body-copy" style="margin-top:8px;">{{ __('core.support.no_related_copy') }}</div>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:26px;">{{ __('core.support.relationships_title') }}</h2>
                <p class="screen-subtitle">{{ __('core.support.relationships_subtitle') }}</p>
            </div>
        </div>

        <table class="entity-table">
            <thead>
                <tr>
                    <th>{{ __('core.support.relationship_source') }}</th>
                    <th>{{ __('core.support.relationship_type') }}</th>
                    <th>{{ __('core.support.relationship_target') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($relationships as $relationship)
                    <tr>
                        <td><a href="#concept-{{ $relationship['source_id'] }}">{{ $relationship['source_label'] }}</a></td>
                        <td>{{ $relationship['type'] }}</td>
                        <td><a href="#concept-{{ $relationship['target_id'] }}">{{ $relationship['target_label'] }}</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="muted-note">{{ __('core.support.no_relationships_copy') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
