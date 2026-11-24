<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Turn your CV and GitHub projects into a portfolio for your next opportunity.">
    <title>{{ $title ?? 'Portmyfolio' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased">
    <header class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-7">
        <a href="{{ route('home') }}" class="text-xl font-bold tracking-tight">portmyfolio<span class="text-orange-600">.</span></a>
        <nav aria-label="Account" class="flex items-center gap-5">
            @auth
                <a href="{{ route('dashboard') }}" class="text-sm font-semibold">Workspace</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm underline underline-offset-4">Sign out</button></form>
            @else
                <a href="{{ route('login') }}" class="text-sm font-semibold underline underline-offset-4">Sign in with Google</a>
            @endauth
        </nav>
    </header>
    @if (session('status'))
        <div role="status" class="mx-auto max-w-6xl border border-orange-200 bg-orange-50 px-6 py-4">{{ session('status') }}</div>
    @endif
    <main>{{ $slot }}</main>
    <footer class="mx-auto max-w-6xl border-t border-stone-200 px-6 py-8 text-sm text-stone-500">Portmyfolio · An experimental portfolio builder. Currently in development.</footer>
</body>
</html>
