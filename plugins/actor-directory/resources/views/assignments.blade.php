<section class="module-screen compact">
    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Assignment</th>
                    <th>Actor</th>
                    <th>Domain object</th>
                    <th>Type</th>
                    <th>Scope</th>
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
                                <span class="muted-note">Unknown actor</span>
                            @endif
                        </td>
                        <td>{{ $row['assignment']->domainObjectType }}:{{ $row['assignment']->domainObjectId }}</td>
                        <td><span class="tag">{{ $row['assignment']->assignmentType }}</span></td>
                        <td>{{ $row['assignment']->scopeId ?? 'organization-wide' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
