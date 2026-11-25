<x-layout :title="'Edit CV · '.$portfolio->title">
    <section class="mx-auto max-w-3xl px-6 py-12">
        <x-editor-nav :portfolio="$portfolio" />
        <h1 class="text-3xl font-semibold tracking-tight">The full picture.</h1>
        <p class="mt-3 text-stone-600">Edit your web CV. Contact information becomes public only when you publish. Dates can be a year or year-month; unknown dates can stay blank.</p>
        <x-form-errors />
        <form method="POST" action="{{ route('userzone.content.cv.update', $portfolio) }}" class="mt-8 space-y-10" data-draft-form>
            @csrf @method('PUT')
            <div class="space-y-5">
                <x-form-input name="name" label="Display name" :value="$cv['name']" required maxlength="120" />
                <x-form-input name="headline" label="Professional headline" :value="$cv['headline']" required maxlength="120" />
                <p class="text-sm text-stone-500">Name and headline also update the home draft.</p>
                <x-form-textarea name="summary" label="CV summary" :value="$cv['summary']" maxlength="5000" rows="5" />
                <x-form-input name="contact[email]" label="Email (optional)" type="email" :value="$cv['contact']['email']" maxlength="254" />
                <x-form-input name="contact[phone]" label="Phone (optional)" type="tel" :value="$cv['contact']['phone']" maxlength="40" />
                <x-form-input name="contact[location]" label="Location (optional)" :value="$cv['contact']['location']" maxlength="120" />
            </div>
            @foreach($sections + ['contact[links]' => ['label' => 'Link label', 'url' => 'URL (http or https)']] as $section => $fields)
                @php($entries = $section === 'contact[links]' ? old('contact.links', $cv['contact']['links']) : old($section, $cv[$section]))
                <section data-repeater="{{ $section }}" data-max="{{ $section === 'contact[links]' ? 10 : 30 }}" class="space-y-4">
                    <h2 class="text-xl font-semibold">{{ $section === 'contact[links]' ? 'Contact links' : ucfirst($section) }}</h2>
                    <div data-entry-list class="space-y-4">
                        @foreach(is_array($entries) ? $entries : [] as $index => $entry)
                            <x-repeater-entry :section="$section" :index="$index" :fields="$fields" :entry="is_array($entry) ? $entry : []" />
                        @endforeach
                    </div>
                    <template data-entry-template><x-repeater-entry :section="$section" index="__INDEX__" :fields="$fields" /></template>
                    <button type="button" data-entry-add class="text-sm font-semibold underline underline-offset-4">+ Add {{ $section === 'contact[links]' ? 'contact link' : $section . ' entry' }}</button>
                    <p data-repeater-status aria-live="polite" class="text-sm text-stone-500"></p>
                </section>
            @endforeach
            <div class="rounded-xl border border-stone-200 p-5">
                <input type="hidden" name="allow_original_download" value="0">
                <label class="flex items-start gap-3"><input class="mt-1" type="checkbox" name="allow_original_download" value="1" @checked(old('allow_original_download', $cv['allow_original_download']))><span>Allow public download of the original PDF</span></label>
                <p class="mt-3 text-sm text-stone-500">This is a draft setting. It will take effect when publishing becomes available and you publish changes. The source file stays in private storage.</p>
            </div>
            <input type="hidden" name="_complete_cv_form" value="1">
            <button class="rounded-full bg-stone-900 px-6 py-3 font-semibold text-white">Save CV draft</button>
        </form>
        <section class="mt-16 border-t border-stone-200 pt-8">
            <h2 class="text-xl font-semibold">Original PDF</h2>
            @if($portfolio->cvDocument)
                <p class="mt-3 break-all text-stone-600">{{ $portfolio->cvDocument->original_filename }}</p>
                <p class="mt-2 text-sm text-stone-500">One source PDF per portfolio. It cannot be replaced. Editing the web CV does not change this file.</p>
                <a href="{{ route('userzone.cv-document.show', $portfolio) }}" class="mt-4 inline-block font-semibold underline underline-offset-4">Download your source PDF ↓</a>
            @else
                <p class="mt-3 text-sm leading-relaxed text-stone-600">Upload one PDF, up to 10 MB. It stays private and cannot be replaced after upload. No automatic extraction runs in this stage.</p>
                <form method="POST" action="{{ route('userzone.cv-document.store', $portfolio) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf
                    <label for="pdf" class="block text-sm font-semibold">Source CV (PDF)</label>
                    <input id="pdf" type="file" name="pdf" accept="application/pdf,.pdf" required class="block w-full rounded-lg border border-stone-300 p-3">
                    <button class="rounded-full bg-stone-900 px-6 py-3 font-semibold text-white">Upload source PDF</button>
                </form>
            @endif
        </section>
    </section>
</x-layout>
