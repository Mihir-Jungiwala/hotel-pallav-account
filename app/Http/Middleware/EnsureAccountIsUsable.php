<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\AuthController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks the signed-in account on every request, so deactivating or
 * locking someone takes effect immediately rather than at their next login.
 */
class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active || $user->isLocked()) {
            AuthController::closeActivityLog();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => $user->is_active
                    ? 'This account has been locked. Contact your administrator.'
                    : 'This account has been deactivated. Contact your administrator.',
            ]);
        }

        // A temporary password must be replaced before anything else
        if ($user->must_change_password && ! $request->routeIs('profile.*', 'logout')) {
            return redirect()->route('profile.show', ['tab' => 'password'])
                ->with('error', 'Please set a new password before continuing.');
        }

        return $next($request);
    }
}
