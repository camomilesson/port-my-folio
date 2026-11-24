<?php

namespace App\Http\Controllers\Userzone;

use App\Http\Controllers\Controller;
use App\Http\Requests\Userzone\StoreUsernameRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UsernameController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if ($request->user()->username !== null) {
            return to_route('dashboard');
        }

        return view('userzone.onboarding.username');
    }

    public function store(StoreUsernameRequest $request): RedirectResponse
    {
        try {
            $request->user()->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'This username is already taken.']);
        }

        return to_route('dashboard')->with('status', 'Your username is set. Welcome to your workspace.');
    }
}
