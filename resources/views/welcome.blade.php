<x-layout>
    <section class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
        <p class="mb-6 text-sm font-semibold uppercase tracking-widest text-orange-700">Your work, clearly presented</p>
        <h1 class="max-w-4xl text-5xl font-semibold leading-tight tracking-tight sm:text-7xl">A portfolio for your<br class="hidden sm:block"> next opportunity.</h1>
        <p class="mt-8 max-w-2xl text-lg leading-relaxed text-stone-600">Bring your CV and GitHub READMEs. Shape a portfolio around the role you want, make it yours, and share your work.</p>
        <div class="mt-10">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-block rounded-full bg-stone-900 px-7 py-4 font-medium text-white">Open your workspace →</a>
            @else
                <a href="{{ route('login') }}" class="inline-block rounded-full bg-stone-900 px-7 py-4 font-medium text-white">Continue with Google →</a>
            @endauth
        </div>
        @guest
            @if(app()->environment(['local', 'testing']) && config('portmyfolio.demo_login_enabled'))
                <form method="POST" action="{{ route('demo.login') }}" class="mt-5">
                    @csrf
                    <button class="text-sm font-medium underline underline-offset-4">Explore the local demo</button>
                </form>
            @endif
        @endguest
        <p class="mt-4 text-sm text-stone-500">Generate once from your evidence, refine your draft, then publish when you’re ready.</p>
    </section>
    <section aria-label="How it works" class="mx-auto grid max-w-6xl gap-10 px-6 pb-24 md:grid-cols-3">
        @foreach ([['01', 'Bring the evidence', 'A PDF CV, up to five public repositories.'], ['02', 'Make it yours', 'Edit every page, add your images, and choose a preset and accent color.'], ['03', 'Publish with purpose', 'Create separate portfolios for different audiences, with one default address.']] as [$number, $heading, $description])
            <article class="border-t border-stone-300 pt-6">
                <p class="text-sm text-orange-700">{{ $number }}</p>
                <h2 class="mt-4 text-xl font-semibold">{{ $heading }}</h2>
                <p class="mt-3 leading-relaxed text-stone-600">{{ $description }}</p>
            </article>
        @endforeach
    </section>
</x-layout>
