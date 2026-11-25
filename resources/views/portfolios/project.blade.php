@php($cv = $page['cv_content'])
<x-portfolio-frame :page="$page">
    <article class="py-12">
        <a href="{{ $page['links']['home'] }}" class="text-sm underline">← All work</a>
        <x-inline-edit :page="$page" :url="$project['inline_url'] ?? null" field="title" :value="$project['title']" label="Project name" type="textarea"><h1 class="mt-8 break-words text-4xl font-semibold sm:text-5xl">{{ $project['title'] ?: $project['repository_name'] }}</h1></x-inline-edit>
        @if($project['pending'] ?? false)<div class="mt-8 space-y-4"><p class="text-stone-500">This project is waiting to be written.</p><div class="h-24 animate-pulse rounded-xl bg-stone-100 motion-reduce:animate-none" aria-label="Project content pending"></div></div>@endif
        <x-inline-edit :page="$page" :url="$project['inline_url'] ?? null" field="summary" :value="$project['summary']" label="Project summary" type="textarea"><p class="mt-6 max-w-3xl break-words text-xl leading-relaxed text-stone-600">{{ $project['summary'] }}</p></x-inline-edit>
        <p class="mt-5 break-words text-sm"><strong>My role</strong> · {{ $project['author_role'] }}</p>
        <x-inline-edit :page="$page" :url="$project['inline_url'] ?? null" field="technologies" :value="implode(', ', $project['technologies'] ?? [])" label="Technologies" type="text"><ul class="mt-5 flex flex-wrap gap-2" aria-label="Technologies">@foreach($project['technologies'] ?? [] as $technology)<li class="rounded-full bg-stone-200 px-3 py-1 text-sm">{{ $technology }}</li>@endforeach</ul></x-inline-edit>
        @if($project['overview'] || ($page['editable'] ?? false))<section class="mt-12 max-w-3xl"><h2 class="text-2xl font-semibold">Overview</h2><x-inline-edit :page="$page" :url="$project['inline_url'] ?? null" field="overview" :value="$project['overview']" label="Overview" type="textarea"><p class="mt-5 whitespace-pre-line break-words leading-relaxed text-stone-600">{{ $project['overview'] }}</p></x-inline-edit></section>@endif
        <section class="mt-12 max-w-3xl"><h2 class="text-2xl font-semibold">Technical details</h2><x-inline-edit :page="$page" :url="$project['inline_url'] ?? null" field="technical_details" :value="$project['technical_details'] ?? ''" label="Technical details"><p class="mt-5 whitespace-pre-line break-words leading-relaxed text-stone-600">{{ $project['technical_details'] ?? '' }}</p></x-inline-edit></section>
        @if(count($project['gallery']) || ($page['editable'] ?? false))
            <section class="mt-12" @if($page['editable'] ?? false) data-inline-gallery @endif>
                <h2 class="text-2xl font-semibold">Gallery</h2>
                <div class="mt-6 space-y-8">
                    @foreach($project['gallery'] as $media)
                        <figure>
                            <img class="max-h-[48rem] w-full rounded-xl bg-stone-100 object-contain" src="{{ $media['url'] }}" alt="{{ $media['alt_text'] }}" loading="lazy">
                            @if($media['caption'] || ($page['editable'] ?? false))
                                <figcaption class="mt-3 break-words text-sm text-stone-600"><x-inline-edit :page="$page" :url="$media['inline_url'] ?? null" field="caption" :value="$media['caption']" label="Image caption">{{ $media['caption'] ?: 'Add caption' }}</x-inline-edit></figcaption>
                            @endif
                            @if($page['editable'] ?? false)
                                <p class="mt-3 text-xs text-stone-500">Saving an image description marks it as non-decorative.</p>
                                <div class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                    <x-inline-edit :page="$page" :url="$media['inline_url']" field="alt_text" :value="$media['alt_text']" label="Image description"><p class="break-words text-stone-500">Description: {{ $media['alt_text'] ?: 'None (decorative)' }}</p></x-inline-edit>
                                    <x-inline-edit :page="$page" :url="$media['inline_url']" field="decorative" :value="$media['decorative']" label="Decorative image" type="checkbox"><p class="text-stone-500">Decorative: {{ $media['decorative'] ? 'Yes' : 'No' }}</p></x-inline-edit>
                                </div>
                            @endif
                        </figure>
                    @endforeach
                </div>
                @if(($page['editable'] ?? false) && count($project['gallery']) < 10)<x-gallery-upload :project="$project" />@endif
            </section>
        @endif
        <div class="mt-12 flex flex-wrap gap-6 border-t border-stone-200 pt-6 font-semibold"><a href="{{ $project['repository_url'] }}" target="_blank" rel="noopener noreferrer" class="underline">GitHub repository ↗</a>@if($project['live_url'])<a href="{{ $project['live_url'] }}" target="_blank" rel="noopener noreferrer" class="underline">Live demo ↗</a>@endif</div>
    </article>
</x-portfolio-frame>
