<?php

use App\Support\SuperAdminIndex;
use Illuminate\Database\Migrations\Migration;

/** Puts the partial index back after the latest columns were added. */
return new class extends Migration
{
    public function up(): void
    {
        SuperAdminIndex::ensure();
    }

    public function down(): void
    {
        // The index is recreated by the migration that owns it
    }
};
