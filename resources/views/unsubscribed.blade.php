<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed — {{ config('app.name', 'Black Gallery') }}</title>
    <style>
        :root {
            color-scheme: light dark;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #fafafa;
            color: #18181b;
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: #0a0a0a;
                color: #fafafa;
            }
        }

        .card {
            max-width: 420px;
            text-align: center;
        }

        h1 {
            font-size: 1.25rem;
            margin: 0 0 8px;
        }

        p {
            margin: 0;
            line-height: 1.5;
            opacity: 0.7;
        }

        a {
            color: inherit;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>You're unsubscribed</h1>
        <p>
            You won't receive {{ $category->label() }} emails from
            {{ config('app.name', 'Black Gallery') }} anymore. You can turn
            this back on any time from your
            <a href="{{ url('/settings/profile') }}">notification settings</a>.
        </p>
    </div>
</body>

</html>
