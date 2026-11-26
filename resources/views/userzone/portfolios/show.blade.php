<x-layout :title="$portfolio->title . ' · Portmyfolio'">
    <section class="mx-auto max-w-4xl px-6 py-16">
        <a href="{{ route('dashboard') }}" class="text-sm underline underline-offset-4">← Workspace</a>
        <p class="mt-10 text-sm font-semibold uppercase tracking-widest text-orange-700">{{ $portfolio->isPublished() ? 'Published · editing draft' : 'Private draft' }}</p>
        <h1 class="mt-4 break-words text-4xl font-semibold tracking-tight">{{ $portfolio->title }}</h1>
        <p class="mt-4 break-all text-sm text-stone-500">/{{ auth()->user()->username }}/{{ $portfolio->slug }}</p>
        <a class="mt-6 inline-block font-semibold underline" href="{{ route('userzone.generation.confirm', $portfolio) }}">Generation & progress →</a>
        <nav aria-label="Edit portfolio" class="mt-8 flex flex-wrap gap-5 text-sm font-semibold">
            <a class="underline" href="{{ route('userzone.content.home', $portfolio) }}">Edit home</a>
            <a class="underline" href="{{ route('userzone.content.cv', $portfolio) }}">Edit CV & PDF</a>
            <a class="underline" href="{{ route('userzone.preview.home', $portfolio) }}">Preview draft →</a>
            <a class="underline" href="{{ route('userzone.publication.confirm', $portfolio) }}">{{ $portfolio->isPublished() ? 'Publish changes' : 'Publish portfolio' }}</a>
        </nav>
        <x-form-errors />
        @if($portfolio->isPublished())
            <div class="mt-7 space-y-4 rounded-xl border border-stone-200 p-5">
                <a href="{{ route('portfolio.home', [auth()->user()->username, $portfolio->slug]) }}" class="break-all font-semibold underline">{{ route('portfolio.home', [auth()->user()->username, $portfolio->slug]) }}</a>
                <p class="text-sm text-stone-500">Last published {{ $portfolio->published_at->format('d M Y, H:i') }} UTC. Draft edits stay private until you publish changes.</p>
                @if(auth()->user()->default_portfolio_id === $portfolio->id)
                    <p class="text-sm font-semibold text-orange-700">Default portfolio</p>
                @else
                    <form method="POST" action="{{ route('userzone.publication.default', $portfolio) }}">@csrf<button class="text-sm font-semibold underline">Make default</button></form>
                @endif
                <form method="POST" action="{{ route('userzone.publication.unpublish', $portfolio) }}">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-700 underline" onclick="return confirm('Unpublish this portfolio and disable new public PDF and image downloads?');">Unpublish portfolio</button></form>
            </div>
        @endif
        <div class="mt-8 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Portfolio focus</h2>
            <p class="mt-3 whitespace-pre-line break-words leading-relaxed text-stone-600">{{ $portfolio->focus ?: 'No focus added yet.' }}</p>
        </div>
        <h2 class="mt-10 text-xl font-semibold">Projects ({{ $portfolio->projects_count }})</h2>
        @if($portfolio->projects_count < 5)<a class="mt-4 inline-block text-sm font-semibold underline" href="{{ route('userzone.projects.create', $portfolio) }}">+ Add project</a>@else<p class="mt-3 text-sm text-stone-500">Five projects maximum. Remove one before adding another.</p>@endif
        <div class="mt-4 space-y-4">
            @forelse($portfolio->projects as $project)
                <article class="rounded-xl border border-stone-200 p-5">
                    <h3 class="break-words font-semibold">{{ $project->title ?: $project->repository_name }}</h3>
                    <a class="mt-3 inline-block text-sm font-semibold underline" href="{{ route('userzone.projects.edit', [$portfolio, $project]) }}">Edit project & gallery →</a>
                    <form method="POST" action="{{ route('userzone.projects.move', [$portfolio, $project]) }}" class="mt-3 flex gap-5 text-sm">@csrf<button name="direction" value="up" @disabled($loop->first) class="underline disabled:opacity-30">Move up</button><button name="direction" value="down" @disabled($loop->last) class="underline disabled:opacity-30">Move down</button></form>
                    <a href="{{ $project->repositoryUrl() }}" rel="noopener noreferrer" target="_blank" class="mt-2 inline-block break-all text-sm underline underline-offset-4">{{ $project->repository_owner }}/{{ $project->repository_name }}</a>
                </article>
            @empty
                <p class="text-stone-600">No projects yet. Add a public GitHub repository and enter its content manually.</p>
            @endforelse
        </div>
        <p class="mt-8 text-sm leading-relaxed text-stone-500">This is your private editor. Saving changes updates the draft; publishing updates the public version.</p>
        @error('portfolio')<p role="alert" class="mt-6 text-sm text-red-700">{{ $message }}</p>@enderror
            <form method="POST" action="{{ route('userzone.portfolios.destroy', $portfolio) }}" class="mt-10 border-t border-stone-200 pt-6">
                @csrf @method('DELETE')
                <button class="text-sm font-semibold text-red-700 underline underline-offset-4" onclick="return confirm('Delete this portfolio and its files? Its public pages will stop working. This cannot be undone.');">Delete {{ $portfolio->isPublished() ? 'portfolio' : 'draft' }}</button>
            </form>
    </section>
</x-layout>
