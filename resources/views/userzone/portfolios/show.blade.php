<x-layout :title="$portfolio->title . ' · Portmyfolio'">
    <section class="mx-auto max-w-4xl px-6 py-16">
        <a href="{{ route('dashboard') }}" class="text-sm underline underline-offset-4">← Workspace</a>
        <p class="mt-10 text-sm font-semibold uppercase tracking-widest text-orange-700">{{ $portfolio->isPublished() ? 'Published fixture' : 'Private draft' }}</p>
        <h1 class="mt-4 break-words text-4xl font-semibold tracking-tight">{{ $portfolio->title }}</h1>
        <p class="mt-4 break-all text-sm text-stone-500">/{{ auth()->user()->username }}/{{ $portfolio->slug }}</p>
        <div class="mt-8 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Portfolio focus</h2>
            <p class="mt-3 whitespace-pre-line break-words leading-relaxed text-stone-600">{{ $portfolio->focus ?: 'No focus added yet.' }}</p>
        </div>
        <h2 class="mt-10 text-xl font-semibold">Projects ({{ $portfolio->projects_count }})</h2>
        <div class="mt-4 space-y-4">
            @forelse($portfolio->projects as $project)
                <article class="rounded-xl border border-stone-200 p-5">
                    <h3 class="break-words font-semibold">{{ $project->title ?: $project->repository_name }}</h3>
                    <p class="mt-2 text-sm text-stone-600">{{ $project->author_role }}</p>
                    <a href="{{ $project->repositoryUrl() }}" rel="noopener noreferrer" target="_blank" class="mt-2 inline-block break-all text-sm underline underline-offset-4">{{ $project->repository_owner }}/{{ $project->repository_name }}</a>
                </article>
            @empty
                <p class="text-stone-600">No projects yet. Repository import and the content editor are coming in the next stages.</p>
            @endforelse
        </div>
        <p class="mt-8 text-sm leading-relaxed text-stone-500">This is your owner workspace. Content editing, generation, and public publishing aren't available yet.</p>
        @error('portfolio')<p role="alert" class="mt-6 text-sm text-red-700">{{ $message }}</p>@enderror
        @if(! $portfolio->isPublished())
            <form method="POST" action="{{ route('userzone.portfolios.destroy', $portfolio) }}" class="mt-10 border-t border-stone-200 pt-6">
                @csrf @method('DELETE')
                <button class="text-sm font-semibold text-red-700 underline underline-offset-4" onclick="return confirm('Delete this private draft? This cannot be undone.');">Delete draft</button>
            </form>
        @endif
    </section>
</x-layout>
