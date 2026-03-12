<section class="module-screen compact">
    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Risk</th>
                    <th>State</th>
                    <th>History</th>
                    <th>Artifacts</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['risk']['title'] }}</td>
                        <td><span class="pill">{{ $row['instance']->currentState }}</span></td>
                        <td>
                            @if ($row['history'] === [])
                                <span class="muted-note">No transitions yet</span>
                            @else
                                <div class="data-stack">
                                    @foreach ($row['history'] as $history)
                                        <div class="data-item">{{ $history->transitionKey }}: {{ $history->fromState }} -> {{ $history->toState }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($row['artifacts'] === [])
                                <span class="muted-note">No artifacts yet</span>
                            @else
                                <div class="data-stack">
                                    @foreach ($row['artifacts'] as $artifact)
                                        <div class="data-item">{{ $artifact['label'] }}: {{ $artifact['original_filename'] }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
