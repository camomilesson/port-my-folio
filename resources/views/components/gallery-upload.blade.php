@props(['project'])
<form method="POST" action="{{ $project['upload_url'] }}" enctype="multipart/form-data" data-inline-form data-upload-form class="mt-6 space-y-3">
    @csrf
    <input id="gallery-files-{{ $project['id'] }}" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required hidden data-gallery-files>
    <button type="button" data-gallery-choose class="rounded-full border border-stone-300 bg-white px-5 py-2 text-sm font-semibold">Add images</button>
    <p class="text-xs text-stone-500">JPEG, PNG or WebP, up to 10 MB each. {{ 10 - count($project['gallery']) }} of 10 spaces available. Selected images upload immediately.</p>
    <p data-inline-error class="text-sm text-red-700" role="alert"></p>
    <p data-upload-status class="text-sm text-stone-600" role="status"></p>
</form>
