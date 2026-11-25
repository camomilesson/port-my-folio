<x-layout title="New portfolio · Portmyfolio">
    <section class="mx-auto max-w-2xl px-6 py-16">
        <a href="{{ route('dashboard') }}" class="text-sm underline underline-offset-4">← Workspace</a>
        <h1 class="mt-8 text-4xl font-semibold tracking-tight">Start a portfolio.</h1>
        <p class="mt-5 leading-relaxed text-stone-600">Upload your CV and choose the projects you want to show. Your inputs stay private until you publish.</p>
        <form method="POST" action="{{ route('userzone.portfolios.generate') }}" enctype="multipart/form-data" class="mt-8 space-y-6" data-create-portfolio>
            @csrf
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
                            <button type="button" data-repository-remove class="text-sm underline">Remove repository</button>
                        </div>
                    @endforeach
                </div>
                <template data-repository-template>
                    <div data-repository class="space-y-3 rounded-xl border border-stone-200 p-4">
                        <x-form-input name="repositories[__INDEX__][repository_url]" label="Repository URL" required maxlength="250" placeholder="https://github.com/owner/repository" />
                        <button type="button" data-repository-remove class="text-sm underline">Remove repository</button>
                    </div>
                </template>
                <button type="button" data-repository-add class="text-sm font-semibold underline">+ Add repository</button>
                <p aria-live="polite" data-repository-status class="text-sm text-stone-500"></p>
            </fieldset>
            <p class="text-sm text-stone-500">Generate sends your CV and repository evidence to the AI provider. Your portfolio stays private until you publish. We’ll check the READMEs as your portfolio starts taking shape.</p>
            <details class="text-sm"><summary class="cursor-pointer font-semibold">Add a focus (optional)</summary><div class="mt-4"><x-form-textarea name="focus" label="Who is this portfolio for?" rows="4" maxlength="5000" placeholder="A role, industry or job description" /></div></details>
            <input type="hidden" name="confirm" value="1">
            <button class="rounded-full bg-stone-900 px-6 py-3 font-medium text-white">Generate my portfolio →</button>
        </form>
    </section>
</x-layout>
