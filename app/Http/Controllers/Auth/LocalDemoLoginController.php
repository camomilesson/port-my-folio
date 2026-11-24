<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocalDemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']) && config('portmyfolio.demo_login_enabled'), 404);

        $user = User::where('google_id', 'demo-admin')->where('email', 'admin@example.com')->first();
        if (! $user) {
            return to_route('home')->with('status', 'Seed the local demo database before using demo sign-in.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
