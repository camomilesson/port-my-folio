@props(['portfolio'])
<div class="mb-10 space-y-5">
    <a href="{{ route('userzone.portfolios.show', $portfolio) }}" class="text-sm underline underline-offset-4">← {{ $portfolio->title }}</a>
    <nav aria-label="Portfolio editor" class="flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold">
        <a href="{{ route('userzone.content.home', $portfolio) }}" class="underline underline-offset-4">Home</a>
        <a href="{{ route('userzone.content.cv', $portfolio) }}" class="underline underline-offset-4">CV & source PDF</a>
        <a href="{{ route('userzone.projects.create', $portfolio) }}" class="underline underline-offset-4">Add project</a>
        <a href="{{ route('userzone.preview.home', $portfolio) }}" class="underline underline-offset-4">Preview draft →</a>
    </nav>
    <p class="text-sm text-stone-500">Changes stay in your private draft. Save each form before previewing. Publishing comes in the next stage.</p>
</div>
