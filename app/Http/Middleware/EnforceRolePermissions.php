<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role ceiling applied to every write, whatever the screen:
 *   Viewer → read only
 *   Editor → create and update, no deletes
 *   Admin / SuperAdmin → full access (finer rules live in controllers)
 */
class EnforceRolePermissions
{
    /** Writes every signed-in user may make, whatever their role. */
    private const SELF_SERVICE = ['logout', 'profile.update', 'profile.password', 'unit.switch', 'force-mode.toggle'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || $request->isMethodSafe() || $request->routeIs(...self::SELF_SERVICE)) {
            return $next($request);
        }

        if (! $user->canWrite()) {
            return $this->deny($request, 'Your account is read-only. Ask an administrator if you need to make changes.');
        }

        if ($request->isMethod('DELETE') && ! $user->canDelete()) {
            return $this->deny($request, 'Only Admins can delete records.');
        }

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->with('error', $message);
    }
}
