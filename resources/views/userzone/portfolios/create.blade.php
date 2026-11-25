<x-layout title="New portfolio · Portmyfolio">
    <section class="mx-auto max-w-2xl px-6 py-16">
        <a href="{{ route('dashboard') }}" class="text-sm underline underline-offset-4">← Workspace</a>
        <h1 class="mt-8 text-4xl font-semibold tracking-tight">Start a portfolio.</h1>
        <p class="mt-5 leading-relaxed text-stone-600">Upload your CV and choose the projects you want to show. Your inputs stay private until you publish.</p>
        <form method="POST" action="{{ route('userzone.portfolios.store') }}" enctype="multipart/form-data" class="mt-8 space-y-6">
            @csrf
            <x-form-input name="title" label="Portfolio title" required maxlength="120" placeholder="Backend developer" />
            <x-form-input name="slug" label="Portfolio address" required maxlength="64" placeholder="backend-developer" autocomplete="off" />
            <p class="text-sm text-stone-500">Your future address: /{{ auth()->user()->username }}/your-chosen-address. Use letters, numbers, and single hyphens.</p>
            <x-form-textarea name="focus" label="What should this portfolio focus on? (optional)" rows="5" maxlength="5000" placeholder="Who is this portfolio for? Add a target role, industry, or job description." />
            <p class="text-sm text-stone-500">Leave focus blank for a general professional portfolio.</p>
            <div class="space-y-2">
                <label for="pdf" class="block text-sm font-semibold">CV PDF *</label>
                <input id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" required class="block w-full rounded-xl border border-stone-300 p-3">
                <p class="text-sm text-stone-500">Up to 10 MB and 20 pages. Scanned PDFs are supported. No password protection. The original cannot be replaced; publishing will make it downloadable.</p>
                @error('pdf')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <x-form-errors />
            <fieldset class="space-y-5" data-repositories>
                <legend class="text-lg font-semibold">Public GitHub projects</legend>
                <p class="text-sm text-stone-500">Choose 1–5 repositories. Each full README must be smaller than 100 KB and contain more than 200 characters of meaningful text.</p>
                <div data-repository-list class="space-y-4">
                    @foreach(old('repositories', [['repository_url' => '', 'author_role' => '']]) as $index => $repository)
                        <div data-repository class="space-y-3 rounded-xl border border-stone-200 p-4">
                            <x-form-input :name="'repositories['.$index.'][repository_url]'" label="Repository URL" required maxlength="250" placeholder="https://github.com/owner/repository" />
                            <x-form-input :name="'repositories['.$index.'][author_role]'" label="Your role" required maxlength="255" placeholder="Solo developer, or backend developer in a team of three" />
                            <button type="button" data-repository-remove class="text-sm underline">Remove repository</button>
                        </div>
                    @endforeach
                </div>
                <template data-repository-template>
                    <div data-repository class="space-y-3 rounded-xl border border-stone-200 p-4">
                        <x-form-input name="repositories[__INDEX__][repository_url]" label="Repository URL" required maxlength="250" placeholder="https://github.com/owner/repository" />
                        <x-form-input name="repositories[__INDEX__][author_role]" label="Your role" required maxlength="255" />
                        <button type="button" data-repository-remove class="text-sm underline">Remove repository</button>
                    </div>
                </template>
                <button type="button" data-repository-add class="text-sm font-semibold underline">+ Add repository</button>
                <p aria-live="polite" data-repository-status class="text-sm text-stone-500"></p>
            </fieldset>
            <p class="text-sm text-stone-500">We’ll check your PDF and READMEs now. No AI call is made. If a check fails, choose the PDF again before resubmitting.</p>
            <button class="rounded-full bg-stone-900 px-6 py-3 font-medium text-white">Check and save inputs →</button>
        </form>
    </section>
</x-layout>
