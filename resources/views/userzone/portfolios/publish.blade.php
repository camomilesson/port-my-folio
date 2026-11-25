<x-layout :title="'Publish · '.$portfolio->title">
    <section class="mx-auto max-w-3xl px-6 py-14">
        <a class="text-sm underline" href="{{ route('userzone.preview.home', $portfolio) }}">← Back to your portfolio</a>
        <h1 class="mt-8 text-3xl font-semibold">{{ $portfolio->isPublished() ? 'Publish your saved changes?' : 'Publish this portfolio?' }}</h1>
        <x-form-errors />
        <p class="mt-5 leading-relaxed text-stone-600">Your saved text, photo, appearance, project order and gallery will become public together. Unsaved form changes are not included.</p>
        <div class="mt-7 rounded-xl border border-orange-200 bg-orange-50 p-6">
            <p class="font-semibold">Your portfolio and original CV PDF will become public.</p>
            <p class="mt-3 text-sm leading-relaxed">The original PDF may include contact details or other information you removed from the web CV. Visitors will be able to download that original file. Unpublishing stops new downloads, but cannot recall copies already downloaded.</p>
        </div>
        <p class="mt-6 text-sm text-stone-500">First publication requires completed initial generation, a source PDF and at least one project. Optional CV sections, images, contacts and extra prose are not required.</p>
        <p class="mt-4 text-sm text-stone-500">If you have no default portfolio, this one will become your default.</p>
        <a href="{{ route('userzone.preview.home', $portfolio) }}" class="mt-6 inline-block font-semibold underline">Preview saved draft →</a>
        <form method="POST" action="{{ route('userzone.publication.publish', $portfolio) }}" class="mt-8 space-y-6">
            @csrf
            <label class="flex items-start gap-3"><input type="checkbox" name="confirm_publication" value="1" required class="mt-1"><span>I understand that my portfolio and original CV PDF will be public.</span></label>
            <button class="rounded-full bg-stone-900 px-6 py-3 font-semibold text-white">{{ $portfolio->isPublished() ? 'Publish changes' : 'Publish portfolio' }}</button>
        </form>
    </section>
</x-layout>
