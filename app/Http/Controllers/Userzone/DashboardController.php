<?php

namespace App\Http\Controllers\Userzone;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $portfolios = $request->user()->portfolios()->withCount('projects')->latest('id')->paginate(12);

        return view('userzone.dashboard', compact('portfolios'));
    }
}
