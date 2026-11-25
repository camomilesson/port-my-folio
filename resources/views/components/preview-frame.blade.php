@props(['portfolio', 'cv'])
<x-layout :title="($cv['name'] ?: $portfolio->title).' · Draft preview'">
    <div class="border-y border-orange-200 bg-orange-50 px-6 py-3 text-center text-sm">
        <strong>Private draft preview</strong> · Only you can view this.
        <a href="{{ route('userzone.portfolios.show', $portfolio) }}" class="ml-3 font-semibold underline">Back to editor</a>
    </div>
    <div class="mx-auto max-w-5xl px-6 py-10">
        <nav aria-label="Portfolio preview" class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 pb-6">
            <a class="font-semibold" href="{{ route('userzone.preview.home', $portfolio) }}">{{ $cv['name'] ?: 'Your portfolio' }}</a>
            <div class="flex gap-5 text-sm"><a href="{{ route('userzone.preview.home', $portfolio) }}" class="underline">Home</a><a href="{{ route('userzone.preview.cv', $portfolio) }}" class="underline">CV</a></div>
        </nav>
        {{ $slot }}
    </div>
</x-layout>
