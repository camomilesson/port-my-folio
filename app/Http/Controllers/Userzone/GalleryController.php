<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\ManageProjectGallery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Userzone\SaveGalleryImageRequest;
use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GalleryController extends Controller
{
    public function store(SaveGalleryImageRequest $request, Portfolio $portfolio, Project $project, ManageProjectGallery $gallery): RedirectResponse
    {
        $this->authorizeMedia($portfolio, $project);
        $gallery->store($portfolio, $project, $request->file('image'), $request->validated());

        return back()->with('status', 'Image processed and added to the draft. The original upload was not kept.');
    }

    public function update(SaveGalleryImageRequest $request, Portfolio $portfolio, Project $project, Media $media, ManageProjectGallery $gallery): RedirectResponse
    {
        $this->authorizeMedia($portfolio, $project, $media);
        $gallery->update($portfolio, $project, $media, $request->validated());

        return back()->with('status', 'Image details saved.');
    }

    public function destroy(Portfolio $portfolio, Project $project, Media $media, ManageProjectGallery $gallery): RedirectResponse
    {
        $this->authorizeMedia($portfolio, $project, $media);
        $gallery->remove($portfolio, $project, $media);

        return back()->with('status', 'Image removed from the draft.');
    }

    public function move(Request $request, Portfolio $portfolio, Project $project, Media $media, ManageProjectGallery $gallery): RedirectResponse
    {
        $this->authorizeMedia($portfolio, $project, $media);
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);
        $gallery->move($portfolio, $project, $media, $data['direction']);

        return back()->with('status', 'Gallery order saved.');
    }

    public function show(Portfolio $portfolio, Project $project, Media $media): StreamedResponse
    {
        $this->authorizeMedia($portfolio, $project, $media);

        return Storage::disk($media->disk)->response($media->getPathRelativeToRoot(), $media->file_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Type' => $media->mime_type]);
    }

    private function authorizeMedia(Portfolio $portfolio, Project $project, ?Media $media = null): void
    {
        abort_unless($project->portfolio_id === $portfolio->id, 404);
        Gate::authorize('view', $project);
        if ($media) {
            abort_unless($media->model_type === $project->getMorphClass() && $media->model_id === $project->id && $media->collection_name === 'gallery' && ! $media->getCustomProperty('draft_removed', false), 404);
            Gate::authorize('view', $media);
        }
    }
}
