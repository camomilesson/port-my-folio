<x-layout title="Choose your username · Portmyfolio">
    <section class="mx-auto max-w-xl px-6 py-20">
        <p class="text-sm font-semibold uppercase tracking-widest text-orange-700">One more thing</p>
        <h1 class="mt-5 text-4xl font-semibold tracking-tight">Choose your address.</h1>
        <p class="mt-5 leading-relaxed text-stone-600">Your username will identify your portfolios. Use 3–30 letters, numbers, or hyphens, starting with a letter. Choose carefully: username changes aren't available yet.</p>
        <form action="{{ route('userzone.username.store') }}" method="POST" class="mt-8 space-y-6">
            @csrf
            <x-form-input name="username" label="Username" required minlength="3" maxlength="30" autocomplete="username" placeholder="sam-example" />
            <button class="rounded-full bg-stone-900 px-6 py-3 font-medium text-white">Continue to workspace →</button>
        </form>
    </section>
</x-layout>
