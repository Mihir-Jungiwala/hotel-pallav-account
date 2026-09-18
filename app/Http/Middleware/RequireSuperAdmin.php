<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Screens only the single SuperAdmin may open, such as Master Data. */
class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::check() && Auth::user()->isSuperAdmin(), 403);

        return $next($request);
    }
}
