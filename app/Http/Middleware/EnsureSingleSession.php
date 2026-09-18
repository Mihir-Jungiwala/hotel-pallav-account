<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\AuthController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * One account, one device. The session id recorded at sign-in is the only one
 * that counts, so signing in somewhere else ends this one on its next request.
 */
class EnsureSingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        // Read the stored token rather than the model in memory: another device
        // may have taken the slot since this request's user was loaded
        $held = DB::table('users')->where('id', $user->id)->value('current_session_id');
        $mine = $request->session()->get(AuthController::DEVICE);

        // A session that predates this rule simply claims the slot
        if ($held === null || $mine === null) {
            $device = $mine ?? Str::random(40);
            $request->session()->put(AuthController::DEVICE, $device);

            $user->forceFill([
                'current_session_id' => $device,
                'session_started_at' => $user->session_started_at ?? now(),
            ])->save();

            return $next($request);
        }

        if ($held !== $mine) {
            AuthController::closeActivityLog();

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'This account was signed in on another device, so this session was ended.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 401);
            }

            return redirect()->route('login')->with('error', $message);
        }

        return $next($request);
    }
}
