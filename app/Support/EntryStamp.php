<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * The date and time an entry was made. Only an Admin or the SuperAdmin may
 * set it; for everyone else it is the moment they saved, and an edit keeps
 * whatever it already was.
 */
class EntryStamp
{
    /** Whether the signed-in user may choose an entry's date and time. */
    public static function editable(): bool
    {
        $user = Auth::user();

        // No one signed in: seeders, the console and imports set their own
        return ! $user || $user->isAdmin();
    }
}
