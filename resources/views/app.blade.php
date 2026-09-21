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
            $reverbConfig = [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: request()->getHost(),
                'port' => (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https') === 'https' ? 443 : 80,
                'scheme' => parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https',
            ];
        @endphp
        <script>
            window.__REVERB__ = @json($reverbConfig);
выдает         </script>
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
