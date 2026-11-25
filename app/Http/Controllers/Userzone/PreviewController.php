<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\PortfolioContent;
use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PreviewController extends Controller
{
    public function home(Portfolio $portfolio): View
    {
        Gate::authorize('view', $portfolio);
        $portfolio->load('projects.media');

        return view('userzone.preview.home', ['portfolio' => $portfolio, 'cv' => PortfolioContent::cv($portfolio)]);
    }

    public function cv(Portfolio $portfolio): View
    {
        Gate::authorize('view', $portfolio);

        return view('userzone.preview.cv', ['portfolio' => $portfolio, 'cv' => PortfolioContent::cv($portfolio)]);
    }

    public function project(Portfolio $portfolio, Project $project): View
    {
        abort_unless($project->portfolio_id === $portfolio->id, 404);
        Gate::authorize('view', $project);
        $project->load('media');

        return view('userzone.preview.project', ['portfolio' => $portfolio, 'project' => $project, 'cv' => PortfolioContent::cv($portfolio)]);
    }
}
