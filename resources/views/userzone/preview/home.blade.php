<x-preview-frame :portfolio="$portfolio" :cv="$cv">
    <section class="max-w-3xl py-16">
        <p class="text-sm font-semibold uppercase tracking-widest text-orange-700">{{ $cv['name'] }}</p>
        <h1 class="mt-5 break-words text-4xl font-semibold tracking-tight sm:text-6xl">{{ $cv['headline'] ?: 'Your next opportunity.' }}</h1>
        <p class="mt-7 whitespace-pre-line break-words text-lg leading-relaxed text-stone-600">{{ $portfolio->home_content['introduction'] ?? '' }}</p>
        <a href="{{ route('userzone.preview.cv', $portfolio) }}" class="mt-8 inline-flex rounded-full bg-stone-900 px-6 py-3 font-semibold text-white">Explore my CV →</a>
    </section>
    <section class="border-t border-stone-200 py-10">
        <h2 class="text-2xl font-semibold">My work</h2>
        <div class="mt-8 grid gap-6 sm:grid-cols-2">
            @forelse($portfolio->projects as $project)
                <article class="overflow-hidden rounded-2xl border border-stone-200 bg-white">
                    @if($cover = $project->activeGallery()->first())
                        <a href="{{ route('userzone.preview.project', [$portfolio, $project]) }}" tabindex="-1" aria-hidden="true"><img src="{{ route('userzone.gallery.show', [$portfolio, $project, $cover]) }}" alt="" class="aspect-video w-full bg-stone-100 object-contain" loading="lazy"></a>
                    @endif
                    <div class="p-6">
                        <h3 class="break-words text-xl font-semibold"><a href="{{ route('userzone.preview.project', [$portfolio, $project]) }}">{{ $project->title ?: $project->repository_name }}</a></h3>
                        <p class="mt-3 break-words leading-relaxed text-stone-600">{{ $project->summary }}</p>
                        <ul class="mt-4 flex flex-wrap gap-2" aria-label="Technologies">@foreach($project->technologies ?? [] as $technology)<li class="rounded-full bg-stone-100 px-3 py-1 text-xs">{{ $technology }}</li>@endforeach</ul>
                        <a class="mt-5 inline-block text-sm font-semibold underline" href="{{ route('userzone.preview.project', [$portfolio, $project]) }}">View project →</a>
                    </div>
                </article>
            @empty
                <p class="text-stone-500">Your projects will appear here.</p>
            @endforelse
        </div>
    </section>
</x-preview-frame>
