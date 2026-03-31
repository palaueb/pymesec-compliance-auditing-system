<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vendor Review Portal</title>
    <style>
        :root {
            --ink: #1f2a22;
            --muted: #667267;
            --line: rgba(31, 42, 34, 0.12);
            --paper: #f4efe4;
            --card: rgba(255, 255, 255, 0.86);
            --accent: #0f766e;
            --accent-ink: #134e4a;
            --danger: #991b1b;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--ink);
            background: linear-gradient(180deg, #f7f2e8 0%, #efe6d4 100%);
        }
        .page {
            max-width: 1040px;
            margin: 0 auto;
            padding: 32px 20px 48px;
            display: grid;
            gap: 18px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 18px;
            display: grid;
            gap: 12px;
        }
        .hero {
            display: grid;
            gap: 10px;
        }
        .eyebrow {
            font-size: 12px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--muted);
        }
        h1, h2, h3, p { margin: 0; }
        h1 { font-size: 34px; line-height: 1.1; }
        h2 { font-size: 22px; line-height: 1.2; }
        .muted { color: var(--muted); }
        .grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .metric-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        .metric {
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: rgba(255,255,255,0.55);
        }
        .metric-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }
        .metric-value {
            margin-top: 8px;
            font-size: 22px;
        }
        .field {
            display: grid;
            gap: 6px;
        }
        .field label {
            font-size: 13px;
            color: var(--muted);
        }
        .field input,
        .field select,
        .field textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px 12px;
            font: inherit;
            color: inherit;
            background: #fff;
        }
        .field textarea { min-height: 110px; resize: vertical; }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        button {
            appearance: none;
            border: 1px solid var(--accent);
            background: var(--accent);
            color: #fff;
            border-radius: 6px;
            padding: 10px 14px;
            font: inherit;
            cursor: pointer;
        }
        .button-ghost {
            background: transparent;
            color: var(--accent-ink);
        }
        .item {
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 14px;
            display: grid;
            gap: 10px;
            background: rgba(255,255,255,0.5);
        }
        .status {
            display: inline-flex;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.65);
            font-size: 13px;
        }
        .flash {
            padding: 12px 14px;
            border-radius: 6px;
            border: 1px solid rgba(15,118,110,0.18);
            background: rgba(15,118,110,0.08);
            color: var(--accent-ink);
        }
        .error-list {
            padding: 12px 14px;
            border-radius: 6px;
            border: 1px solid rgba(153,27,27,0.18);
            background: rgba(153,27,27,0.08);
            color: var(--danger);
        }
        @media (max-width: 860px) {
            .grid,
            .metric-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="card hero">
            <div class="eyebrow">External Review Portal</div>
            <h1>{{ $vendor['legal_name'] }}</h1>
            <p class="muted">{{ $review['title'] }}</p>
            <p>{{ $review['review_summary'] }}</p>
            <p class="muted">
                Shared with {{ $link['contact_name'] !== '' ? $link['contact_name'] : $link['contact_email'] }}.
                {{ $link['expires_at'] !== '' ? 'This access expires on '.$link['expires_at'].'.' : 'This access has no explicit expiry date.' }}
            </p>
        </section>

        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="error-list">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="metric-grid">
            <div class="metric">
                <div class="metric-label">Vendor tier</div>
                <div class="metric-value">{{ ucfirst($vendor['tier']) }}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Inherent risk</div>
                <div class="metric-value">{{ ucfirst($review['inherent_risk']) }}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Questionnaire access</div>
                <div class="metric-value">{{ $link['can_answer_questionnaire'] === '1' ? 'Enabled' : 'Read only' }}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Evidence upload</div>
                <div class="metric-value">{{ $link['can_upload_artifacts'] === '1' ? 'Enabled' : 'Not enabled' }}</div>
            </div>
        </section>

        <section class="grid">
            <div class="card">
                <h2>Questionnaire</h2>
                <p class="muted">Only the questions explicitly shared in this review are available here.</p>

                @forelse ($questionnaire_items as $item)
                    <article class="item">
                        <div>
                            <div class="eyebrow">Question {{ $item['position'] }}</div>
                            <h3>{{ $item['prompt'] }}</h3>
                        </div>
                        <div class="actions">
                            <span class="status">{{ ucwords(str_replace('-', ' ', $item['response_status'])) }}</span>
                            <span class="status">{{ ucwords(str_replace('-', ' ', $item['response_type'])) }}</span>
                        </div>
                        @if ($link['can_answer_questionnaire'] === '1' && $item['response_status'] !== 'accepted')
                            <form method="POST" action="{{ route('plugin.third-party-risk.external.questionnaire-items.update', ['token' => $token, 'itemId' => $item['id']]) }}">
                                @csrf
                                <div class="field">
                                    <label>Answer</label>
                                    @if ($item['response_type'] === 'yes-no')
                                        <select name="answer_text" required>
                                            <option value="">Choose an answer</option>
                                            @foreach (['yes' => 'Yes', 'no' => 'No', 'not-applicable' => 'Not applicable'] as $value => $label)
                                                <option value="{{ $value }}" @selected($item['answer_text'] === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($item['response_type'] === 'date')
                                        <input type="date" name="answer_text" value="{{ $item['answer_text'] }}">
                                    @else
                                        <textarea name="answer_text" required>{{ $item['answer_text'] }}</textarea>
                                    @endif
                                </div>
                                <div class="actions">
                                    <button type="submit">Submit answer</button>
                                </div>
                            </form>
                        @else
                            <div class="field">
                                <label>Current answer</label>
                                <div>{{ $item['answer_text'] !== '' ? $item['answer_text'] : 'No answer recorded yet.' }}</div>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="muted">No questionnaire items have been shared on this review yet.</div>
                @endforelse
            </div>

            <div class="card">
                <h2>Evidence Upload</h2>
                <p class="muted">Upload only the documents requested for this review. Files are attached directly to the review workspace.</p>

                @if ($link['can_upload_artifacts'] === '1')
                    <form method="POST" action="{{ route('plugin.third-party-risk.external.artifacts.store', ['token' => $token]) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="field">
                            <label>Document label</label>
                            <input type="text" name="label" required>
                        </div>
                        <div class="field">
                            <label>File</label>
                            <input type="file" name="artifact" required>
                        </div>
                        <div class="actions">
                            <button type="submit">Upload evidence</button>
                        </div>
                    </form>
                @else
                    <div class="muted">This collaboration link does not include artifact upload permission.</div>
                @endif
            </div>
        </section>
    </main>
</body>
</html>
