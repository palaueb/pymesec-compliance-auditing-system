<section class="module-screen compact">
    <div class="surface-card">
        <div class="entity-title">{{ __('Assignment register') }}</div>
        <div class="table-note" style="margin-top:6px;">{{ __('This supporting view stays focused on accountability records by actor, object, type, and scope. Use functional actor details or the linked workspace item to manage the underlying responsibility.') }}</div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>{{ __('Assignment') }}</th>
                    <th>{{ __('Actor') }}</th>
                    <th>{{ __('Domain object') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Scope') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><div class="entity-title">{{ $row['assignment']->id }}</div></td>
                        <td>
                            @if ($row['actor'] !== null)
                                {{ $row['actor']->displayName }}
                            @else
                                <span class="muted-note">{{ __('Unknown actor') }}</span>
                            @endif
                        </td>
                        <td>{{ $row['assignment']->domainObjectType }}:{{ $row['assignment']->domainObjectId }}</td>
                        <td><span class="tag">{{ $row['assignment']->assignmentType }}</span></td>
                        <td>{{ $row['assignment']->scopeId ?? __('organization-wide') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
