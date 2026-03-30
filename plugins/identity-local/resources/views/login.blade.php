<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in</title>
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
        .login-card {
            width: min(560px, 100%);
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
        .field { display: grid; gap: 8px; margin-top: 18px; }
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
    </style>
</head>
<body>
    <main class="login-card">
        <div class="eyebrow">Identity</div>
        <h1>Sign in</h1>
        <p>Use your username or work email. Local and directory-backed sign-in can require an email verification code, and some organizations also allow a cached email sign-in link.</p>

        <form method="POST" action="{{ route('plugin.identity-local.auth.request') }}">
            @csrf
            <div class="field">
                <label class="field-label" for="login-identifier">Username or email</label>
                <input class="field-input" id="login-identifier" name="login" required autofocus>
            </div>
            <div class="field">
                <label class="field-label" for="login-password">Password</label>
                <input class="field-input" id="login-password" name="password" type="password">
            </div>
            <label class="field-label" style="display:flex; gap:10px; align-items:center; margin-top:16px;">
                <input type="checkbox" name="use_email_link" value="1">
                Sign in with email link instead
            </label>
            <button class="button button-primary" type="submit">Continue</button>
        </form>

        @if (session('status'))
            <div class="note">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="note">{{ session('error') }}</div>
        @endif
    </main>
</body>
</html>
