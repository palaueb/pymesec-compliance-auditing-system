<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set up your first administrator</title>
    <style>
        :root {
            --bg: #f4f0e8;
            --ink: #1f2a22;
            --muted: #5c695f;
            --line: rgba(31,42,34,0.12);
            --panel: rgba(255,255,255,0.82);
            --accent: #9c4f2f;
            --accent-soft: #efd9c6;
            --font-heading: "Fraunces", Georgia, serif;
            --font-body: "IBM Plex Sans", "Segoe UI", sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at top left, rgba(156,79,47,0.16), transparent 28%),
                linear-gradient(135deg, #f7f1e8, var(--bg));
            color: var(--ink);
            font-family: var(--font-body);
        }
        .setup-card {
            width: min(680px, 100%);
            border: 1px solid var(--line);
            background: var(--panel);
            border-radius: 10px;
            padding: 28px;
            box-shadow: 0 18px 54px rgba(31,42,34,0.08);
        }
        .eyebrow, .field-label {
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
        }
        h1 {
            margin: 6px 0 10px;
            font-family: var(--font-heading);
            font-size: clamp(32px, 5vw, 46px);
            line-height: 0.95;
        }
        p { color: var(--muted); line-height: 1.5; }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        .field { display: grid; gap: 8px; }
        .field-wide { grid-column: 1 / -1; }
        .field-input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 12px 14px;
            font: inherit;
            background: #fff;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 6px;
            padding: 12px 16px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            margin-top: 16px;
        }
        .button-primary {
            background: linear-gradient(90deg, var(--accent-soft), #fff);
            color: var(--ink);
            box-shadow: inset 4px 0 0 var(--accent);
        }
        .note {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 6px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.7);
            color: var(--muted);
            font-size: 14px;
        }
        @media (max-width: 720px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="setup-card">
        <div class="eyebrow">First run</div>
        <h1>Create the first administrator</h1>
        <p>This account becomes the local administrative fallback. Use a password if you want password sign-in with an email verification step, or leave it empty and start with email sign-in only.</p>

        <form method="POST" action="{{ route('plugin.identity-local.setup.store') }}">
            @csrf
            <div class="grid">
                <div class="field field-wide">
                    <label class="field-label" for="setup-display-name">Full name</label>
                    <input class="field-input" id="setup-display-name" name="display_name" required autofocus>
                </div>
                <div class="field">
                    <label class="field-label" for="setup-username">Username</label>
                    <input class="field-input" id="setup-username" name="username" required>
                </div>
                <div class="field">
                    <label class="field-label" for="setup-email">Work email</label>
                    <input class="field-input" id="setup-email" name="email" type="email" required>
                </div>
                <div class="field">
                    <label class="field-label" for="setup-password">Password</label>
                    <input class="field-input" id="setup-password" name="password" type="password">
                </div>
                <div class="field">
                    <label class="field-label" for="setup-password-confirmation">Confirm password</label>
                    <input class="field-input" id="setup-password-confirmation" name="password_confirmation" type="password">
                </div>
            </div>
            <button class="button button-primary" type="submit">Create administrator</button>
        </form>
    </main>
</body>
</html>
