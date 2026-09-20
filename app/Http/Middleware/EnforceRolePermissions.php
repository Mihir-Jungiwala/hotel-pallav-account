<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every request is checked against what the account is allowed to do.
 *
 * The screen it asks for and the method it used name the permission it needs
 * ("expense.delete"), which the role ladder answers. A request this cannot
 * place still meets the old ceiling: read-only accounts never write, and only
 * accounts allowed to delete may delete.
 */
class EnforceRolePermissions
{
    /** Writes every signed-in user may make, whatever their role. */
    private const SELF_SERVICE = ['logout', 'profile.update', 'profile.password', 'unit.switch', 'force-mode.toggle', 'profile.two-factor', 'profile.two-factor.check'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || $request->routeIs(...self::SELF_SERVICE)) {
            return $next($request);
        }

        $permission = Permissions::forRequest($request->route()?->getName(), $request->method());

        if ($permission && ! $user->can($permission)) {
            return $this->deny($request, $this->explain($permission, $request->isMethodSafe()));
        }

        if ($request->isMethodSafe()) {
            return $next($request);
        }

        if (! $user->canWrite()) {
            return $this->deny($request, 'Your account is read-only. Ask an administrator if you need to make changes.');
        }

        if ($request->isMethod('DELETE') && ! $user->canDelete()) {
            return $this->deny($request, 'Your account is not allowed to delete records.');
        }

        return $next($request);
    }

    /** Name the screen and the action, so the message can be acted on. */
    private function explain(string $permission, bool $safe): string
    {
        $label = Permissions::label($permission);

        return $safe
            ? "Your account cannot open {$label}. Ask an administrator for access."
            : "Your account is not allowed to {$label}. Ask an administrator for access.";
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        // A screen they cannot open at all has nowhere to go back to
        if ($request->isMethodSafe()) {
            abort(403, $message);
        }

        return back()->with('error', $message);
    }
}
