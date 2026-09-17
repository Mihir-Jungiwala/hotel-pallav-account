<?php

namespace App\Http\Controllers;

use App\Models\UserAuditLog;
use App\Support\ForceMode;
use App\Support\UnitContext;
use Illuminate\Http\Request;

/**
 * Session-level choices that change what a screen shows rather than the data:
 * which business is in view, and whether the SuperAdmin override is armed.
 */
class ContextController extends Controller
{
    public function switchUnit(Request $request)
    {
        $slug = $request->string('unit')->toString();

        UnitContext::remember($slug);
        UserAuditLog::record('unit.switched', $request->user(), ['unit' => UnitContext::currentKey()]);

        return back()->with('success', 'Now showing '.UnitContext::label().'.');
    }

    public function toggleForceMode(Request $request)
    {
        abort_unless(ForceMode::availableTo(), 403);

        $on = ! ForceMode::enabled();
        ForceMode::set($on);

        return back()->with($on ? 'error' : 'success', $on
            ? 'Force mode is armed — validation and record locks are off for your account.'
            : 'Force mode is off. Normal checks are back on.');
    }
}
