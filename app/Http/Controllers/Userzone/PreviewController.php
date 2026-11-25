<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\PortfolioPageData;
use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PreviewController extends Controller
{
    public function home(Portfolio $portfolio, PortfolioPageData $pages): View
    {
        Gate::authorize('view', $portfolio);

        return view('portfolios.home', ['page' => $pages->preview($portfolio)]);
    }

    public function cv(Portfolio $portfolio, PortfolioPageData $pages): View
    {
        Gate::authorize('view', $portfolio);

        return view('portfolios.cv', ['page' => $pages->preview($portfolio)]);
    }

    public function project(Portfolio $portfolio, Project $project, PortfolioPageData $pages): View
    {
        abort_unless($project->portfolio_id === $portfolio->id, 404);
        Gate::authorize('view', $project);
        $page = $pages->preview($portfolio);

        return view('portfolios.project', ['page' => $page, 'project' => collect($page['projects'])->firstWhere('id', $project->id)]);
    }
}
