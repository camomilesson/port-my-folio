<x-layout title="Portfolio not found · Portmyfolio">
    <section class="mx-auto max-w-3xl px-6 py-24">
        <p class="text-sm font-semibold uppercase tracking-widest text-orange-700">404 · Portfolio not found</p>
        <h1 class="mt-5 text-4xl font-semibold tracking-tight">No public portfolio at this address.</h1>
        <p class="mt-6 text-lg leading-relaxed text-stone-600">We couldn't find a public portfolio at this address.</p>
        @guest
            <p class="mt-4 leading-relaxed text-stone-600">If this is your portfolio, sign in to find it in your workspace.</p>
            <a href="{{ route('login') }}" class="mt-8 inline-flex rounded-full bg-stone-900 px-6 py-3 font-semibold text-white">Sign in with Google →</a>
        @else
            <p class="mt-4 leading-relaxed text-stone-600">If this is your portfolio, you can find your drafts in your workspace.</p>
            <a href="{{ route('dashboard') }}" class="mt-8 inline-flex rounded-full bg-stone-900 px-6 py-3 font-semibold text-white">Go to workspace →</a>
        @endguest
    </section>
</x-layout>
