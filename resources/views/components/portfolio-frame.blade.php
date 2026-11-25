@props(['page'])
<x-layout :title="($page['cv_content']['name'] ?: 'Portfolio').($page['preview'] ? ' · Draft preview' : '')" :show-account="$page['preview']">
    <div @if($page['preview']) data-live-portfolio data-active="{{ in_array($page['generation']['status'] ?? null, ['queued', 'running'], true) ? '1' : '0' }}" data-revision="{{ $page['generation']['revision'] ?? '' }}" @endif>
    @if($page['preview'])
        <div class="border-y border-orange-200 bg-orange-50 px-6 py-3 text-center text-sm">
            <strong>Private draft preview</strong> · Only you can view this.
            <a href="{{ $page['links']['editor'] }}" class="ml-3 font-semibold underline">Back to editor</a>
            @if(in_array($page['generation']['status'] ?? null, ['queued', 'running', 'failed'], true))
                <p class="mt-3 font-semibold" role="status">{{ $page['generation']['label'] }}</p>
                @if($page['generation']['status'] === 'failed')
                    <form method="POST" action="{{ route('userzone.generation.store', request()->route('portfolio')) }}" class="mt-3">@csrf<input type="hidden" name="confirm" value="1"><button class="rounded-full bg-stone-900 px-5 py-2 text-white">Retry remaining steps</button></form>
                @else
                    <p class="mt-2 text-xs text-stone-600">Your portfolio is taking shape. You can explore its pages or return later.</p>
                @endif
                <x-form-errors />
            @else
                @if(($page['generation']['status'] ?? null) === 'succeeded')<span class="ml-3 font-semibold">Ready</span>@endif
                <a class="ml-3 font-semibold underline" href="{{ route('userzone.publication.confirm', request()->route('portfolio')) }}">{{ request()->route('portfolio')->isPublished() ? 'Publish changes' : 'Publish portfolio' }}</a>
            @endif
        </div>
    @endif
    <div class="mx-auto max-w-5xl px-6 py-10" style="--portfolio-accent: {{ $page['accent_color'] }}">
        <nav aria-label="Portfolio navigation" class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 pb-6">
            <a class="font-semibold" href="{{ $page['links']['home'] }}">{{ $page['cv_content']['name'] ?: 'Portfolio' }}</a>
            <div class="flex gap-5 text-sm"><a href="{{ $page['links']['home'] }}" class="underline">Home</a><a href="{{ $page['links']['cv'] }}" class="underline">CV</a></div>
        </nav>
        {{ $slot }}
    </div>
    </div>
</x-layout>
