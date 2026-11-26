@php($cv = $page['cv_content'])
<x-portfolio-frame :page="$page">
    <article class="portfolio-project py-12">
        <a href="{{ $page['links']['home'] }}" class="text-sm underline">← All work</a>
        <x-inline-edit :page="$page" :url="$project['inline_url'] ?? null" field="title" :value="$project['title']" label="Project name" type="textarea"><h1 class="mt-8 break-words text-4xl font-semibold sm:text-5xl">{{ $project['title'] ?: $project['repository_name'] }}</h1></x-inline-edit>
        @if($project['pending'] ?? false)<div class="mt-8 space-y-4"><p class="text-stone-500">This project is waiting to be written.</p><div class="h-24 animate-pulse rounded-xl bg-stone-100 motion-reduce:animate-none" aria-label="Project content pending"></div></div>@endif
        <x-inline-edit :page="$page" :url="$project['inline_url'] ?? null" field="summary" :value="$project['summary']" label="Project summary" type="textarea"><p class="mt-6 max-w-3xl break-words text-xl leading-relaxed text-stone-600">{{ $project['summary'] }}</p></x-inline-edit>
        <x-inline-edit :page="$page" :url="$project['inline_url'] ?? null" field="technologies" :value="implode(', ', $project['technologies'] ?? [])" label="Technologies" type="text"><ul class="portfolio-tags mt-5 flex flex-wrap gap-2" aria-label="Technologies">@foreach($project['technologies'] ?? [] as $technology)<li class="rounded-full bg-stone-200 px-3 py-1 text-sm">{{ $technology }}</li>@endforeach</ul></x-inline-edit>
        @if(count($project['gallery']) || ($page['editable'] ?? false))
            <section class="portfolio-gallery mt-12" @if($page['editable'] ?? false) data-inline-gallery @endif>
                <h2 class="text-2xl font-semibold">Gallery</h2>
                <div class="mt-6" data-carousel role="region" aria-roledescription="carousel" aria-label="Project images">
                    @foreach($project['gallery'] as $media)
                        <figure class="carousel-slide" data-carousel-slide="{{ $media['id'] }}" @if($loop->first) data-current @endif role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $loop->count }}">
                            <div class="carousel-stage relative overflow-hidden rounded-xl bg-stone-100">
                                <img class="h-full w-full object-contain" src="{{ $media['url'] }}" alt="{{ $media['alt_text'] }}" loading="lazy">
                                @if(count($project['gallery']) > 1)
                                    <button type="button" data-carousel-direction="-1" class="carousel-arrow left-0" aria-label="Previous image"><span aria-hidden="true">←</span></button>
                                    <button type="button" data-carousel-direction="1" class="carousel-arrow right-0" aria-label="Next image"><span aria-hidden="true">→</span></button>
                                @endif
                            </div>
                            @if($media['caption'])<figcaption class="mt-3 break-words text-sm text-stone-600">{{ $media['caption'] }}</figcaption>@endif
                        </figure>
                    @endforeach
                    @if(count($project['gallery']) > 1)<p class="mt-3 text-center text-xs text-stone-500" data-carousel-count role="status" aria-live="polite">1 / {{ count($project['gallery']) }}</p>@endif
                </div>
                @if(($page['editable'] ?? false) && count($project['gallery']) < 10)<x-gallery-upload :project="$project" />@endif
            </section>
        @endif
        @if($project['overview'] || ($page['editable'] ?? false))<section class="portfolio-prose mt-12 max-w-3xl"><h2 class="text-2xl font-semibold">Overview</h2><x-inline-edit :page="$page" :url="$project['inline_url'] ?? null" field="overview" :value="$project['overview']" label="Overview" type="textarea"><p class="mt-5 whitespace-pre-line break-words leading-relaxed text-stone-600">{{ $project['overview'] }}</p></x-inline-edit></section>@endif
        <section class="portfolio-prose mt-12 max-w-3xl"><h2 class="text-2xl font-semibold">Technical details</h2><x-inline-edit :page="$page" :url="$project['inline_url'] ?? null" field="technical_details" :value="$project['technical_details'] ?? ''" label="Technical details"><p class="mt-5 whitespace-pre-line break-words leading-relaxed text-stone-600">{{ $project['technical_details'] ?? '' }}</p></x-inline-edit></section>
        <div class="mt-12 flex flex-wrap gap-6 border-t border-stone-200 pt-6 font-semibold"><a href="{{ $project['repository_url'] }}" target="_blank" rel="noopener noreferrer" class="underline">GitHub repository ↗</a>@if($project['live_url'])<a href="{{ $project['live_url'] }}" target="_blank" rel="noopener noreferrer" class="underline">Live demo ↗</a>@endif</div>
    </article>
</x-portfolio-frame>
