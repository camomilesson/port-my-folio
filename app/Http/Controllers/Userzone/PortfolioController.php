<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\DeletePortfolio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Userzone\StorePortfolioRequest;
use App\Models\Portfolio;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', Portfolio::class);

        return view('userzone.portfolios.create');
    }

    public function store(StorePortfolioRequest $request): RedirectResponse
    {
        try {
            $portfolio = $request->user()->portfolios()->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'You already have a portfolio with this address.']);
        }

        return to_route('userzone.portfolios.show', $portfolio)->with('status', 'Draft created. Add your introduction, CV, and projects.');
    }

    public function show(Portfolio $portfolio): View
    {
        Gate::authorize('view', $portfolio);
        $portfolio->loadCount('projects');
        $portfolio->load('projects');

        return view('userzone.portfolios.show', compact('portfolio'));
    }

    public function destroy(Portfolio $portfolio, DeletePortfolio $delete): RedirectResponse
    {
        Gate::authorize('delete', $portfolio);
        $delete->handle($portfolio);

        return to_route('dashboard')->with('status', 'Draft deleted.');
    }
}
