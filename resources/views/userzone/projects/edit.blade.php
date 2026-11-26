<x-layout :title="($project ? 'Edit project' : 'Add project').' · '.$portfolio->title">
    <section class="mx-auto max-w-3xl px-6 py-12">
        <x-editor-nav :portfolio="$portfolio" />
        <h1 class="text-3xl font-semibold tracking-tight">{{ $project ? 'Shape your project.' : 'Add a repository-backed project.' }}</h1>
        <p class="mt-3 leading-relaxed text-stone-600">Enter content manually. No AI generation is triggered. GitHub availability and README checks arrive with import.</p>
        <x-form-errors />
        <form method="POST" action="{{ $project ? route('userzone.projects.update', [$portfolio, $project]) : route('userzone.projects.store', $portfolio) }}" class="mt-8 space-y-6" data-draft-form>
            @csrf @if($project) @method('PUT') @endif
            @if($project)
                <p class="break-all text-sm text-stone-600">Repository: <a class="underline" href="{{ $project->repositoryUrl() }}" target="_blank" rel="noopener noreferrer">{{ $project->repository_owner }}/{{ $project->repository_name }}</a></p>
                <p class="text-sm text-stone-500">Repository identity stays fixed. To use a different repository, remove this project and add another.</p>
            @else
                <x-form-input name="repository_url" label="Public GitHub repository URL" type="url" required placeholder="https://github.com/owner/repository" maxlength="250" />
            @endif
            <x-form-input name="title" label="Project title" :value="$project?->title" required maxlength="120" />
            <x-form-textarea name="summary" label="Short summary" :value="$project?->summary" rows="3" maxlength="1000" />
            <x-form-textarea name="overview" label="Project overview" :value="$project?->overview" rows="8" maxlength="10000" />
            <x-form-textarea name="technical_details" label="Technical details" :value="$project?->technical_details" rows="8" maxlength="10000" required />
            <x-form-textarea name="technologies_text" label="Technologies (one per line)" :value="implode(chr(10), $project?->technologies ?? [])" rows="4" maxlength="3000" />
            <x-form-input name="live_url" label="Live demo URL (optional)" type="url" :value="$project?->live_url" maxlength="2048" />
            <button class="rounded-full bg-stone-900 px-6 py-3 font-semibold text-white">{{ $project ? 'Save project draft' : 'Add project' }}</button>
        </form>
        @if($project)
            <section class="mt-16 border-t border-stone-200 pt-8">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-2xl font-semibold">Project gallery</h2>
                    <a class="text-sm font-semibold underline" href="{{ route('userzone.preview.project', [$portfolio, $project]) }}">Preview project →</a>
                </div>
                <p class="mt-3 text-sm leading-relaxed text-stone-600">Up to 10 still images. JPEG, PNG or WebP, 10 MB each, maximum 12,000 pixels per side and 24 megapixels. Images are resized within 2,400 pixels and stored as WebP without metadata; original uploads are discarded. First image is the cover.</p>
                <div class="mt-6 space-y-6">
                    @forelse($project->activeGallery() as $media)
                        <article class="rounded-xl border border-stone-200 bg-white p-5">
                            <p class="mb-3 text-sm font-semibold">{{ $loop->first ? 'Cover image' : 'Gallery image '.$loop->iteration }}</p>
                            <img src="{{ route('userzone.gallery.show', [$portfolio, $project, $media]) }}" alt="{{ $media->getCustomProperty('alt_text', '') }}" class="max-h-80 w-full rounded-lg object-contain" loading="lazy">
                            <div class="mt-5 flex flex-wrap gap-5 border-t border-stone-100 pt-4">
                                <form method="POST" action="{{ route('userzone.gallery.move', [$portfolio, $project, $media]) }}" class="flex gap-5 text-sm font-semibold">@csrf<button name="direction" value="up" @disabled($loop->first) class="underline disabled:opacity-30">Move up</button><button name="direction" value="down" @disabled($loop->last) class="underline disabled:opacity-30">Move down</button></form>
                                <form method="POST" action="{{ route('userzone.gallery.destroy', [$portfolio, $project, $media]) }}">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-700 underline">Remove from draft</button></form>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-stone-500">No images yet. The gallery will stay hidden in the preview until you add one.</p>
                    @endforelse
                </div>
                @if($project->activeGallery()->count() < 10)
                    <form method="POST" action="{{ route('userzone.gallery.store', [$portfolio, $project]) }}" enctype="multipart/form-data" class="mt-8 space-y-4 rounded-xl border border-stone-200 p-5">
                        @csrf
                        <label class="inline-flex cursor-pointer rounded-full border border-stone-300 px-5 py-2 text-sm font-semibold" for="image">Add image</label><input id="image" type="file" name="image" required accept="image/jpeg,image/png,image/webp" class="sr-only" data-gallery-single>
                        <p class="text-xs text-stone-500">The selected image uploads immediately.</p>
                    </form>
                @endif
            </section>
            <form method="POST" action="{{ route('userzone.projects.destroy', [$portfolio, $project]) }}" class="mt-12 border-t border-stone-200 pt-6">
                @csrf @method('DELETE')
                <button class="text-sm font-semibold text-red-700 underline" onclick="return confirm('Remove this project from your draft? Any published version will be retained.');">Remove project from draft</button>
            </form>
        @endif
    </section>
</x-layout>
