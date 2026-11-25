<x-layout :title="'Edit home · '.$portfolio->title">
    <section class="mx-auto max-w-3xl px-6 py-12">
        <x-editor-nav :portfolio="$portfolio" />
        <h1 class="text-3xl font-semibold tracking-tight">Your introduction.</h1>
        <p class="mt-3 text-stone-600">Keep it brief. Your CV holds the detail, and every project appears below your introduction.</p>
        <x-form-errors />
        <form method="POST" action="{{ route('userzone.content.home.update', $portfolio) }}" class="mt-8 space-y-6" data-draft-form>
            @csrf @method('PUT')
            <x-form-input name="title" label="Portfolio title (workspace)" :value="$portfolio->title" required maxlength="120" />
            <x-form-input name="name" label="Display name" :value="$cv['name']" required maxlength="120" />
            <x-form-input name="headline" label="Professional headline" :value="$cv['headline']" required maxlength="120" />
            <p class="text-sm text-stone-500">Your display name and headline are shared with this portfolio's CV page. Other portfolios and your Google account are independent.</p>
            <x-form-textarea name="introduction" label="Home introduction" :value="$portfolio->home_content['introduction'] ?? ''" maxlength="3000" rows="6" />
            <x-form-textarea name="focus" label="Portfolio focus" :value="$portfolio->focus" maxlength="5000" rows="4" />
            <p class="text-sm text-stone-500">Changing the focus does not rewrite existing content.</p>
            <button class="rounded-full bg-stone-900 px-6 py-3 font-semibold text-white">Save home draft</button>
        </form>
    </section>
</x-layout>
