<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('code') — {{ config('app.name') }}</title>
        <style>
            :root { color-scheme: light dark; }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: #0a0a0a;
                color: #ededec;
                text-align: center;
                padding: 1.5rem;
            }
            .code {
                font-size: 0.875rem;
                font-weight: 600;
                letter-spacing: 0.05em;
                color: #706f6c;
                text-transform: uppercase;
                margin: 0 0 0.5rem;
            }
            h1 {
                font-size: 1.5rem;
                font-weight: 600;
                margin: 0 0 0.5rem;
            }
            p {
                color: #a1a09a;
                margin: 0;
            }
        </style>
    </head>
    <body>
        <div>
            <p class="code">Error @yield('code')</p>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
        </div>
    </body>
</html>
