<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return to_route('home')->with('status', 'Google sign-in is not configured yet.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return to_route('home')->with('status', 'Sign-in was cancelled. You can try again.');
        }

        try {
            $identity = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return to_route('home')->with('status', 'Your sign-in session expired. Please try again.');
        }

        abort_unless($identity->getId() && $identity->getEmail(), 422, 'Google did not supply an account identity.');

        $user = User::where('google_id', $identity->getId())->first();

        if (! $user) {
            // Never attach a new identity to an existing account based on email alone.
            if (User::where('email', $identity->getEmail())->exists()) {
                return to_route('home')->with('status', 'This email already belongs to another account.');
            }

            $user = User::create([
                'google_id' => $identity->getId(),
                'email' => $identity->getEmail(),
                'name' => $identity->getName() ?: $identity->getEmail(),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
