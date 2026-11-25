@php($cv = $page['cv_content'])
<x-portfolio-frame :page="$page">
    <section class="max-w-3xl py-16">
        <x-inline-edit :page="$page" scope="home" field="name" :value="$cv['name']" label="Name"><p class="text-sm font-semibold uppercase tracking-widest text-[var(--portfolio-accent)]">{{ $cv['name'] }}</p></x-inline-edit>
        <x-inline-edit :page="$page" scope="home" field="headline" :value="$cv['headline']" label="Title"><h1 class="mt-5 break-words text-4xl font-semibold tracking-tight sm:text-6xl">{{ ($page['generation']['has_extraction'] ?? true) ? ($cv['headline'] ?: 'Your next opportunity.') : 'Building your portfolio' }}</h1></x-inline-edit>
        @if(!($page['generation']['has_profile'] ?? true))<div class="mt-7 space-y-3" aria-label="Introduction pending"><div class="h-4 w-full animate-pulse rounded bg-stone-200 motion-reduce:animate-none"></div><div class="h-4 w-2/3 animate-pulse rounded bg-stone-200 motion-reduce:animate-none"></div><p class="text-sm text-stone-500">Your introduction will appear here.</p></div>@endif
        <x-inline-edit :page="$page" scope="home" field="introduction" :value="$page['home_content']['introduction'] ?? ''" label="Introduction"><p class="mt-7 whitespace-pre-line break-words text-lg leading-relaxed text-stone-600">{{ $page['home_content']['introduction'] ?? '' }}</p></x-inline-edit>
        <a href="{{ $page['links']['cv'] }}" class="mt-8 inline-flex rounded-full bg-stone-900 px-6 py-3 font-semibold text-white">Explore my CV →</a>
    </section>
    <section class="border-t border-stone-200 py-10">
        <h2 class="text-2xl font-semibold">My work</h2>
        <div class="mt-8 grid gap-6 sm:grid-cols-2">
            @forelse($page['projects'] as $project)
                <article class="overflow-hidden rounded-2xl border border-stone-200 bg-white">
                    @if($cover = ($project['gallery'][0] ?? null))
                        <a href="{{ $project['url'] }}" tabindex="-1" aria-hidden="true"><img src="{{ $cover['url'] }}" alt="" class="aspect-video w-full bg-stone-100 object-contain" loading="lazy"></a>
                    @endif
                    <div class="p-6">
                        <h3 class="break-words text-xl font-semibold"><a href="{{ $project['url'] }}">{{ $project['title'] ?: $project['repository_name'] }}</a></h3>
                        @if($project['pending'] ?? false)<div class="mt-4 h-4 w-3/4 animate-pulse rounded bg-stone-200 motion-reduce:animate-none" aria-label="Project content pending"></div><p class="mt-3 text-sm text-stone-500">Waiting for project content…</p>@endif
                        <p class="mt-3 break-words leading-relaxed text-stone-600">{{ $project['summary'] }}</p>
                        <ul class="mt-4 flex flex-wrap gap-2" aria-label="Technologies">@foreach($project['technologies'] ?? [] as $technology)<li class="rounded-full bg-stone-100 px-3 py-1 text-xs">{{ $technology }}</li>@endforeach</ul>
                        <a class="mt-5 inline-block text-sm font-semibold underline" href="{{ $project['url'] }}">View project →</a>
                    </div>
                </article>
            @empty
                <p class="text-stone-500">Your projects will appear here.</p>
            @endforelse
        </div>
    </section>
</x-portfolio-frame>
