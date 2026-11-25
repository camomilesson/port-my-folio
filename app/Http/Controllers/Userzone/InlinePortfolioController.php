<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\EditInlinePortfolioField;
use App\Actions\ManageProjectGallery;
use App\Actions\StorePortfolioPhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Userzone\StoreGalleryBatchRequest;
use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InlinePortfolioController extends Controller
{
    public function page(Request $request, Portfolio $portfolio, string $scope, EditInlinePortfolioField $edit): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $portfolio);
        $data = $request->validate(['field' => ['required', 'string'], 'value' => ['present', 'nullable'], 'section' => ['nullable', 'string'], 'entry_id' => ['nullable', 'string', 'max:64']]);
        $edit->page($portfolio, $scope, $data['field'], $data['value'], $data['section'] ?? null, $data['entry_id'] ?? null);

        return $this->saved($request);
    }

    public function project(Request $request, Portfolio $portfolio, Project $project, EditInlinePortfolioField $edit): JsonResponse|RedirectResponse
    {
        abort_unless($project->portfolio_id === $portfolio->id, 404);
        Gate::authorize('update', $project);
        $data = $request->validate(['field' => ['required', 'string'], 'value' => ['present', 'nullable']]);
        $edit->project($portfolio, $project, $data['field'], $data['value']);

        return $this->saved($request);
    }

    public function images(StoreGalleryBatchRequest $request, Portfolio $portfolio, Project $project, ManageProjectGallery $gallery): JsonResponse|RedirectResponse
    {
        abort_unless($project->portfolio_id === $portfolio->id, 404);
        $gallery->storeBatch($portfolio, $project, $request->file('images'), $request->validated('details'));

        return $this->saved($request);
    }

    public function image(Request $request, Portfolio $portfolio, Project $project, Media $media, ManageProjectGallery $gallery): JsonResponse|RedirectResponse
    {
        abort_unless($project->portfolio_id === $portfolio->id && $media->model_type === $project->getMorphClass() && $media->model_id === $project->id && $media->collection_name === 'gallery' && ! $media->getCustomProperty('draft_removed', false), 404);
        Gate::authorize('update', $project);
        $data = $request->validate(['field' => ['required', 'in:caption,alt_text,decorative'], 'value' => ['present', 'nullable']]);
        $gallery->updateField($portfolio, $project, $media, $data['field'], $data['value']);

        return $this->saved($request);
    }

    public function photo(Request $request, Portfolio $portfolio, StorePortfolioPhoto $store): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $portfolio);
        $request->validate(['photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240']]);
        $store->handle($portfolio, $request->file('photo'));

        return $this->saved($request);
    }

    public function showPhoto(Portfolio $portfolio, string $photoId): StreamedResponse
    {
        Gate::authorize('view', $portfolio);
        $photo = $portfolio->home_content['photo'] ?? null;
        abort_unless($photo && $photo['id'] === $photoId && Storage::disk($photo['disk'])->exists($photo['path']), 404);

        return Storage::disk($photo['disk'])->response($photo['path'], 'photo.webp', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Type' => 'image/webp']);
    }

    private function saved(Request $request): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['message' => 'Draft saved. Publish changes to update the public version.']) : back()->with('status', 'Draft saved.');
    }
}
