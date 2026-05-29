{{--
    AEOS365 — Branded error/maintenance layout (Phase 0 Task 14).

    Used by 403.blade.php, 404.blade.php, 500.blade.php, 503.blade.php.
    Standalone (no asset pipeline dependency) so it renders cleanly even
    if Vite/JS assets are unavailable during an outage.
--}}
<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Service Unavailable')) — AEOS365</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #1e293b;
        }
        .card {
            max-width: 28rem;
            padding: 2.5rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,.08), 0 8px 10px -6px rgba(0,0,0,.04);
            text-align: center;
        }
        .code {
            font-size: 4rem;
            font-weight: 800;
            color: #0ea5e9;
            letter-spacing: -0.05em;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .title { font-size: 1.5rem; font-weight: 600; margin: 1rem 0 0.5rem; }
        .message { color: #475569; line-height: 1.6; margin-bottom: 1.5rem; }
        .actions a {
            display: inline-block;
            padding: 0.625rem 1.25rem;
            background: #0ea5e9;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.15s ease;
        }
        .actions a:hover { background: #0284c7; }
        .footer { margin-top: 2rem; font-size: 0.875rem; color: #94a3b8; }
    </style>
</head>
<body>
    <main class="card" role="alert" aria-live="polite">
        <div class="code">@yield('code')</div>
        <h1 class="title">@yield('title')</h1>
        <p class="message">@yield('message')</p>
        @hasSection('actions')
            <div class="actions">@yield('actions')</div>
        @endif
        <div class="footer">AEOS365 — @yield('subtitle', 'Enterprise Suite')</div>
    </main>
</body>
</html>
