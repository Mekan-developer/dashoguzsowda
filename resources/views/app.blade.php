<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="/icons/logo-64.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=Golos+Text:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @php
            // Echo ходит через тот же nginx, что и админка (location /app → reverb:8080),
            // поэтому хост, схема и порт — из APP_URL, включая нестандартный порт (:8000 в dev).
            $appUrl = parse_url((string) config('app.url'));
            $reverbScheme = $appUrl['scheme'] ?? 'https';
            $reverbConfig = [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => ($appUrl['host'] ?? null) ?: request()->getHost(),
                'port' => $appUrl['port'] ?? ($reverbScheme === 'https' ? 443 : 80),
                'scheme' => $reverbScheme,
            ];
        @endphp
        <script>
            window.__REVERB__ = @json($reverbConfig);
        </script>
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
