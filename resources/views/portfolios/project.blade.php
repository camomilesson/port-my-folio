@php($cv = $page['cv_content'])
<x-portfolio-frame :page="$page">
    <article class="py-12">
        <a href="{{ $page['links']['home'] }}" class="text-sm underline">← All work</a>
        <h1 class="mt-8 break-words text-4xl font-semibold sm:text-5xl">{{ $project['title'] ?: $project['repository_name'] }}</h1>
        <p class="mt-6 max-w-3xl break-words text-xl leading-relaxed text-stone-600">{{ $project['summary'] }}</p>
        <p class="mt-5 break-words text-sm"><strong>My role</strong> · {{ $project['author_role'] }}</p>
        <ul class="mt-5 flex flex-wrap gap-2" aria-label="Technologies">@foreach($project['technologies'] ?? [] as $technology)<li class="rounded-full bg-stone-200 px-3 py-1 text-sm">{{ $technology }}</li>@endforeach</ul>
        @if($project['overview'])<section class="mt-12 max-w-3xl"><h2 class="text-2xl font-semibold">Overview</h2><p class="mt-5 whitespace-pre-line break-words leading-relaxed text-stone-600">{{ $project['overview'] }}</p></section>@endif
        @if(count($project['gallery']))
            <section class="mt-12"><h2 class="text-2xl font-semibold">Gallery</h2><div class="mt-6 space-y-8">@foreach($project['gallery'] as $media)<figure><img class="max-h-[48rem] w-full rounded-xl bg-stone-100 object-contain" src="{{ $media['url'] }}" alt="{{ $media['alt_text'] }}" loading="lazy">@if($media['caption'])<figcaption class="mt-3 break-words text-sm text-stone-600">{{ $media['caption'] }}</figcaption>@endif</figure>@endforeach</div></section>
        @endif
        <div class="mt-12 flex flex-wrap gap-6 border-t border-stone-200 pt-6 font-semibold"><a href="{{ $project['repository_url'] }}" target="_blank" rel="noopener noreferrer" class="underline">GitHub repository ↗</a>@if($project['live_url'])<a href="{{ $project['live_url'] }}" target="_blank" rel="noopener noreferrer" class="underline">Live demo ↗</a>@endif</div>
    </article>
</x-portfolio-frame>
