<x-layout title="Workspace · Portmyfolio">
    <section class="mx-auto max-w-6xl px-6 py-16">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-orange-700">Your workspace · {{ auth()->user()->username }}</p>
                <h1 class="mt-5 text-4xl font-semibold tracking-tight">Welcome, {{ auth()->user()->name }}.</h1>
                <p class="mt-4 max-w-xl leading-relaxed text-stone-600">A different audience, a different portfolio. Start a draft and keep your work in one place.</p>
            </div>
            <a href="{{ route('userzone.portfolios.create') }}" class="rounded-full bg-stone-900 px-6 py-3 font-medium text-white">New portfolio →</a>
        </div>
        <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @forelse($portfolios as $portfolio)
                <article class="flex flex-col rounded-2xl border border-stone-200 bg-white p-6">
                    <div class="flex items-center justify-between gap-3 text-xs font-semibold uppercase tracking-wide">
                        <span class="text-stone-500">{{ $portfolio->isPublished() ? 'Published' : 'Draft' }}</span>
                        @if(auth()->user()->default_portfolio_id === $portfolio->id)<span class="text-orange-700">Default</span>@endif
                    </div>
                    <h2 class="mt-5 break-words text-2xl font-semibold"><a href="{{ route('userzone.portfolios.show', $portfolio) }}" class="hover:underline">{{ $portfolio->title }}</a></h2>
                    <p class="mt-3 break-all text-sm text-stone-500">/{{ auth()->user()->username }}/{{ $portfolio->slug }}</p>
                    <p class="mt-4 line-clamp-3 text-sm leading-relaxed text-stone-600">{{ $portfolio->focus ?: 'No focus added yet.' }}</p>
                    <p class="mt-auto pt-6 text-sm text-stone-500">{{ $portfolio->projects_count }} {{ Str::plural('project', $portfolio->projects_count) }}</p>
                    <a href="{{ route('userzone.portfolios.show', $portfolio) }}" class="mt-4 text-sm font-semibold underline underline-offset-4">Open portfolio →</a>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-stone-300 p-8 md:col-span-2 lg:col-span-3">
                    <h2 class="text-xl font-semibold">Your first portfolio starts here.</h2>
                    <p class="mt-3 text-stone-600">Choose a title, an address, and who you want to reach. You can add content when the editor is ready.</p>
                </div>
            @endforelse
        </div>
        <div class="mt-8">{{ $portfolios->links() }}</div>
    </section>
</x-layout>
