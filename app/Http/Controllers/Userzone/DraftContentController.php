<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\EditPortfolioDraft;
use App\Actions\PortfolioContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Userzone\UpdateCvRequest;
use App\Http\Requests\Userzone\UpdateHomeRequest;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DraftContentController extends Controller
{
    public function home(Portfolio $portfolio): View
    {
        Gate::authorize('update', $portfolio);

        return view('userzone.portfolios.home', ['portfolio' => $portfolio, 'cv' => PortfolioContent::cv($portfolio)]);
    }

    public function cv(Portfolio $portfolio): View
    {
        Gate::authorize('update', $portfolio);

        return view('userzone.portfolios.cv', ['portfolio' => $portfolio, 'cv' => PortfolioContent::cv($portfolio), 'sections' => PortfolioContent::sections()]);
    }

    public function updateHome(UpdateHomeRequest $request, Portfolio $portfolio, EditPortfolioDraft $editor): RedirectResponse
    {
        $data = $request->validated();
        $editor->handle($portfolio, function (Portfolio $locked) use ($data): void {
            $cv = PortfolioContent::cv($locked);
            $cv['name'] = $data['name'];
            $cv['headline'] = $data['headline'];
            $locked->update(['title' => $data['title'], 'focus' => $data['focus'] ?? null, 'home_content' => ['schema_version' => 1, 'name' => $data['name'], 'headline' => $data['headline'], 'introduction' => $data['introduction'] ?? null], 'cv_content' => $cv]);
        });

        return back()->with('status', 'Home draft saved.');
    }

    public function updateCv(UpdateCvRequest $request, Portfolio $portfolio, EditPortfolioDraft $editor): RedirectResponse
    {
        $cv = $request->validated();
        unset($cv['_complete_cv_form']);
        $cv['schema_version'] = 1;
        foreach (['experience', 'education'] as $section) {
            foreach ($cv[$section] as &$entry) {
                $entry['current'] = (bool) $entry['current'];
            }
            unset($entry);
        }
        $editor->handle($portfolio, function (Portfolio $locked) use ($cv): void {
            $home = array_replace($locked->home_content ?? [], ['schema_version' => 1, 'name' => $cv['name'], 'headline' => $cv['headline']]);
            $locked->update(['home_content' => $home, 'cv_content' => $cv]);
        });

        return back()->with('status', 'CV draft saved.');
    }
}
