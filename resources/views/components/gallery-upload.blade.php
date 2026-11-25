@props(['project'])
<details class="mt-6 rounded-xl border border-dashed border-stone-300 p-5" data-gallery-upload>
    <summary class="cursor-pointer font-semibold">Add images</summary>
    <form method="POST" action="{{ $project['upload_url'] }}" enctype="multipart/form-data" data-inline-form data-upload-form class="mt-5 space-y-4">
        @csrf
        <p class="text-sm text-stone-600">Select several JPEG, PNG or WebP images, up to 10 MB each. {{ 10 - count($project['gallery']) }} of 10 gallery spaces available.</p>
        <label class="block text-sm font-semibold">Images<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required class="mt-2 block w-full text-sm" data-gallery-files></label>
        <div data-image-details class="space-y-4"></div>
        <template data-image-template>
            <fieldset class="space-y-3 rounded-lg bg-stone-50 p-4">
                <legend class="break-all font-semibold" data-image-name></legend>
                <label class="block text-sm">Caption (optional)<textarea data-image-caption rows="2" maxlength="500" class="inline-input"></textarea></label>
                <label class="block text-sm">Image description<input data-image-alt type="text" maxlength="500" required class="inline-input"></label>
                <label class="flex items-center gap-2 text-sm"><input type="hidden" data-image-not-decorative value="0"><input type="checkbox" data-image-decorative value="1"> Decorative image (no description)</label>
            </fieldset>
        </template>
        <p data-inline-error class="text-sm text-red-700" role="alert"></p>
        <div class="flex gap-3 text-sm"><button type="submit" class="rounded-full bg-stone-900 px-5 py-2 font-semibold text-white">Add selected images</button><button type="button" data-upload-cancel class="rounded-full border border-stone-300 px-5 py-2">Cancel</button></div>
    </form>
</details>
