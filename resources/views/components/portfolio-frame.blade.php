@props(['page'])
<x-layout :title="($page['cv_content']['name'] ?: 'Portfolio').($page['preview'] ? ' · Draft preview' : '')" :show-account="$page['preview']">
    @if($page['preview'])
        <div class="border-y border-orange-200 bg-orange-50 px-6 py-3 text-center text-sm">
            <strong>Private draft preview</strong> · Only you can view this.
            <a href="{{ $page['links']['editor'] }}" class="ml-3 font-semibold underline">Back to editor</a>
        </div>
    @endif
    <div class="mx-auto max-w-5xl px-6 py-10" style="--portfolio-accent: {{ $page['accent_color'] }}">
        <nav aria-label="Portfolio navigation" class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 pb-6">
            <a class="font-semibold" href="{{ $page['links']['home'] }}">{{ $page['cv_content']['name'] ?: 'Portfolio' }}</a>
            <div class="flex gap-5 text-sm"><a href="{{ $page['links']['home'] }}" class="underline">Home</a><a href="{{ $page['links']['cv'] }}" class="underline">CV</a></div>
        </nav>
        {{ $slot }}
    </div>
</x-layout>
