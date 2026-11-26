<x-layout>
    <section class="mx-auto grid max-w-6xl items-center gap-14 px-6 pt-12 pb-16 sm:pt-20 sm:pb-24 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:gap-16">
        <div>
            <h1 class="max-w-xl text-5xl font-semibold leading-[1.08] tracking-tight sm:text-6xl">Your next portfolio starts with your CV.</h1>
            <p class="mt-6 max-w-md text-lg leading-relaxed text-stone-600">Add your CV and GitHub projects. Customize the result and publish when you’re ready.</p>
            <div class="mt-8">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-4 rounded-full bg-stone-900 px-7 py-4 font-medium text-white transition-colors hover:bg-stone-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-orange-700">Open your workspace <span aria-hidden="true">→</span></a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-4 rounded-full bg-stone-900 px-7 py-4 font-medium text-white transition-colors hover:bg-stone-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-orange-700">Continue with Google <span aria-hidden="true">→</span></a>
                @endauth
            </div>
            <p class="mt-4 text-sm text-stone-500">Drafts stay private until you publish.</p>
            @guest
                @if(app()->environment(['local', 'testing']) && config('portmyfolio.demo_login_enabled'))
                    <form method="POST" action="{{ route('demo.login') }}" class="mt-5">
                        @csrf
                        <button class="text-sm font-medium underline underline-offset-4">Explore the local demo</button>
                    </form>
                @endif
            @endguest
        </div>
        <figure class="min-w-0" data-welcome-preview data-presets='@json(config('portmyfolio.presets'))' data-accents='@json(array_values(config('portmyfolio.accent_swatches')))'>
            <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl shadow-stone-900/5">
                <div aria-hidden="true" class="flex items-center gap-1.5 border-b border-stone-100 bg-stone-50 px-5 py-3">
                    <span class="size-2 rounded-full bg-stone-300"></span>
                    <span class="size-2 rounded-full bg-stone-300"></span>
                    <span class="size-2 rounded-full bg-stone-300"></span>
                </div>
                <div id="welcome-portfolio" class="welcome-portfolio" data-portfolio-preset="minimal" style="--portfolio-accent: {{ array_values(config('portmyfolio.accent_swatches'))[0] }}">
                    <div class="portfolio-content">
                        <div class="portfolio-nav flex items-center justify-between gap-3 border-b border-stone-200 pb-5 text-xs">
                            <p class="border-l-2 border-[var(--portfolio-accent)] pl-3 font-semibold">Alex Morgan</p>
                            <p class="text-stone-500">Portfolio / CV</p>
                        </div>
                        <div class="welcome-profile">
                            <div>
                                <p class="portfolio-eyebrow text-xs font-semibold uppercase tracking-widest text-[var(--portfolio-accent)]">Alex Morgan</p>
                                <h2 class="welcome-headline">Web developer.<br>Thoughtful builder.</h2>
                                <p class="mt-4 text-sm leading-relaxed text-stone-600">I build useful tools for everyday life, from neighborhood maps to reading lists.</p>
                            </div>
                            <img src="{{ asset('images/welcome-portrait.jpg') }}" alt="Alex Morgan, the example portfolio owner" width="447" height="447" class="portfolio-photo aspect-[4/5] w-full rounded-2xl object-cover">
                        </div>
                        <div class="border-t border-stone-200 pt-5">
                            <h3 class="font-semibold text-[var(--portfolio-accent)]">My work</h3>
                            <div class="portfolio-project-grid mt-4 grid grid-cols-2 gap-3">
                                <article class="portfolio-card overflow-hidden rounded-xl border border-stone-200 bg-white">
                                    <img src="{{ asset('images/welcome-neighborhood.webp') }}" alt="Illustrated neighborhood with a park and cafés" width="768" height="512" class="welcome-project-image aspect-[3/2] w-full object-cover">
                                    <div class="portfolio-card-copy p-4">
                                        <h4 class="font-semibold">Neighborhood map</h4>
                                        <p class="mt-2 text-xs leading-relaxed text-stone-600">A guide to local parks, cafés and shared spaces.</p>
                                        <ul class="portfolio-tags mt-4 flex flex-wrap gap-2 text-xs text-stone-600" aria-label="Neighborhood map technologies">
                                            <li class="rounded-full bg-stone-100 px-2 py-1">Laravel</li>
                                            <li class="rounded-full bg-stone-100 px-2 py-1">SQLite</li>
                                        </ul>
                                    </div>
                                </article>
                                <article class="portfolio-card overflow-hidden rounded-xl border border-stone-200 bg-white">
                                    <img src="{{ asset('images/welcome-reading.webp') }}" alt="Illustrated books and an open reading journal" width="768" height="512" class="welcome-project-image aspect-[3/2] w-full object-cover">
                                    <div class="portfolio-card-copy p-4">
                                        <h4 class="font-semibold">Reading shelf</h4>
                                        <p class="mt-2 text-xs leading-relaxed text-stone-600">A small app for books, notes and recommendations.</p>
                                        <ul class="portfolio-tags mt-4 flex flex-wrap gap-2 text-xs text-stone-600" aria-label="Reading shelf technologies">
                                            <li class="rounded-full bg-stone-100 px-2 py-1">JavaScript</li>
                                            <li class="rounded-full bg-stone-100 px-2 py-1">CSS</li>
                                        </ul>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <figcaption class="mt-4 space-y-3 text-center">
                <p class="text-xs text-stone-500">Example portfolio · <span data-preview-label>Minimal</span></p>
                <div class="flex flex-wrap items-center justify-center gap-2" role="group" aria-label="Example portfolio styles" data-preview-controls hidden>
                    @foreach(config('portmyfolio.presets') as $key => $preset)
                        <button type="button" data-preview-preset="{{ $key }}" aria-controls="welcome-portfolio" aria-pressed="{{ $key === 'minimal' ? 'true' : 'false' }}" class="welcome-style-button rounded-full border border-stone-300 px-3 py-2 text-xs">{{ $preset['label'] }}</button>
                    @endforeach
                    <button type="button" data-preview-pause aria-controls="welcome-portfolio" class="rounded-full px-3 py-2 text-xs underline underline-offset-4">Pause</button>
                </div>
            </figcaption>
        </figure>
    </section>
</x-layout>
