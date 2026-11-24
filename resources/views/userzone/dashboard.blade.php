<x-layout title="Workspace · Portmyfolio">
    <section class="mx-auto max-w-6xl px-6 py-24">
        <p class="text-sm font-semibold uppercase tracking-widest text-orange-700">Your workspace</p>
        <h1 class="mt-5 text-4xl font-semibold tracking-tight">Welcome, {{ auth()->user()->name }}.</h1>
        <p class="mt-6 max-w-xl leading-relaxed text-stone-600">You're signed in. Portfolio creation and editing are coming in the next development stages.</p>
    </section>
</x-layout>
