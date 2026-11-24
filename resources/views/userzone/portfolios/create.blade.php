<x-layout title="New portfolio · Portmyfolio">
    <section class="mx-auto max-w-2xl px-6 py-16">
        <a href="{{ route('dashboard') }}" class="text-sm underline underline-offset-4">← Workspace</a>
        <h1 class="mt-8 text-4xl font-semibold tracking-tight">Start a portfolio.</h1>
        <p class="mt-5 leading-relaxed text-stone-600">Give this version a purpose. Your draft stays private until publishing becomes available.</p>
        <form method="POST" action="{{ route('userzone.portfolios.store') }}" class="mt-8 space-y-6">
            @csrf
            <x-form-input name="title" label="Portfolio title" required maxlength="120" placeholder="Backend developer" />
            <x-form-input name="slug" label="Portfolio address" required maxlength="64" placeholder="backend-developer" autocomplete="off" />
            <p class="text-sm text-stone-500">Your future address: /{{ auth()->user()->username }}/your-chosen-address. Use letters, numbers, and single hyphens.</p>
            <x-form-textarea name="focus" label="Portfolio focus (optional for now)" rows="5" maxlength="5000" placeholder="Who is this portfolio for? Add a target role, industry, or job description." />
            <button class="rounded-full bg-stone-900 px-6 py-3 font-medium text-white">Create private draft →</button>
        </form>
    </section>
</x-layout>
