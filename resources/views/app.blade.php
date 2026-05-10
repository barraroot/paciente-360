<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Sanctum SPA (T050) consome este meta via axios bootstrap. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Paciente 360') }}</title>

    {{-- Entrypoints do Vite (T015 = CSS Tailwind v4 / T014 = Vue 3 + Pinia + Router + i18n). --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-surface text-foreground antialiased">
    {{--
        Alvo único do createApp(App).mount('#app').
        Toda navegação interna acontece via Vue Router (HTML5 mode);
        as rotas em routes/web.php devolvem ESTE shell para qualquer
        caminho da SPA (catch-all em /panel/{any?}).
    --}}
    <div id="app"></div>
</body>
</html>
