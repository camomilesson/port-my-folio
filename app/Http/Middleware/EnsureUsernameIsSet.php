<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUsernameIsSet
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->username === null) {
            return to_route('userzone.username.edit');
        }

        return $next($request);
    }
}
