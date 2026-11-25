<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\StorePortfolioCv;
use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CvDocumentController extends Controller
{
    public function store(Request $request, Portfolio $portfolio, StorePortfolioCv $store): RedirectResponse
    {
        Gate::authorize('update', $portfolio);
        $request->validate(['pdf' => ['required', 'file', 'mimetypes:application/pdf', 'extensions:pdf', 'max:10240']]);
        $store->handle($portfolio, $request->file('pdf'));

        return back()->with('status', 'Source PDF saved privately. It cannot be replaced. No extraction has been run.');
    }

    public function show(Portfolio $portfolio): StreamedResponse
    {
        Gate::authorize('view', $portfolio);
        $document = $portfolio->cvDocument()->firstOrFail();
        Gate::authorize('view', $document);

        return Storage::disk($document->storageDisk())->download($document->storage_path, 'source-cv.pdf', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Type' => 'application/pdf']);
    }
}
