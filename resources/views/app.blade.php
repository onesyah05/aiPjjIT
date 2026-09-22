<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'PJJ AI') }}</title>

        <meta name="theme-color" content="#061226">
        <meta name="application-name" content="PJJ AI SIBERMU">
        <meta name="description" content="Asisten belajar komunitas Informatika Universitas Siber Muhammadiyah.">
        <meta property="og:image" content="{{ url('/images/sibermu-logo.png') }}">
        <link rel="icon" type="image/png" href="/images/sibermu-logo.png">
        <link rel="apple-touch-icon" href="/images/sibermu-logo.png">
        <link rel="manifest" href="/site.webmanifest">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
