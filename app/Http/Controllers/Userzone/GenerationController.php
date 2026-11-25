<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\GenerationPreview;
use App\Actions\StartPortfolioGeneration;
use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GenerationController extends Controller
{
    public function confirm(Portfolio $portfolio): View
    {
        Gate::authorize('update', $portfolio);
        $portfolio->load('cvDocument', 'projects');
        $generation = $portfolio->generations()->latest('id')->first();

        return view('userzone.portfolios.generate', compact('portfolio', 'generation'));
    }

    public function store(Request $request, Portfolio $portfolio, StartPortfolioGeneration $start): RedirectResponse
    {
        Gate::authorize('update', $portfolio);
        $request->validate(['confirm' => ['accepted']]);
        $start->handle($portfolio);

        return to_route('userzone.preview.home', $portfolio)->with('status', 'Generation queued. You can leave this page and return later.');
    }

    public function status(Portfolio $portfolio): JsonResponse
    {
        Gate::authorize('view', $portfolio);
        $generation = $portfolio->generations()->latest('id')->first();

        return response()->json(GenerationPreview::status($generation))->header('Cache-Control', 'private, no-store');
    }
}
