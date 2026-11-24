<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\ManagePortfolioProjects;
use App\Http\Controllers\Controller;
use App\Http\Requests\Userzone\SaveProjectRequest;
use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectEditorController extends Controller
{
    public function create(Portfolio $portfolio): View
    {
        Gate::authorize('update', $portfolio);

        return view('userzone.projects.edit', ['portfolio' => $portfolio, 'project' => null]);
    }

    public function edit(Portfolio $portfolio, Project $project): View
    {
        $this->authorizeProject($portfolio, $project);
        $project->load('media');

        return view('userzone.projects.edit', compact('portfolio', 'project'));
    }

    public function store(SaveProjectRequest $request, Portfolio $portfolio, ManagePortfolioProjects $projects): RedirectResponse
    {
        $project = $projects->save($portfolio, null, $request->validated());

        return to_route('userzone.projects.edit', [$portfolio, $project])->with('status', 'Project added. Content is entered manually; no AI generation was run.');
    }

    public function update(SaveProjectRequest $request, Portfolio $portfolio, Project $project, ManagePortfolioProjects $projects): RedirectResponse
    {
        $this->authorizeProject($portfolio, $project);
        $projects->save($portfolio, $project, $request->validated());

        return back()->with('status', 'Project draft saved.');
    }

    public function destroy(Portfolio $portfolio, Project $project, ManagePortfolioProjects $projects): RedirectResponse
    {
        $this->authorizeProject($portfolio, $project);
        $projects->remove($portfolio, $project);

        return to_route('userzone.portfolios.show', $portfolio)->with('status', 'Project removed from the draft.');
    }

    public function move(Request $request, Portfolio $portfolio, Project $project, ManagePortfolioProjects $projects): RedirectResponse
    {
        $this->authorizeProject($portfolio, $project);
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);
        $projects->move($portfolio, $project, $data['direction']);

        return back()->with('status', 'Project order saved.');
    }

    private function authorizeProject(Portfolio $portfolio, Project $project): void
    {
        abort_unless($project->portfolio_id === $portfolio->id, 404);
        Gate::authorize('update', $project);
    }
}
