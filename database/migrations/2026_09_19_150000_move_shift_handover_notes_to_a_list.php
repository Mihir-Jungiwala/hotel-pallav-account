<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Handover notes were five fixed boxes. They become one list so a shift can
 * leave as many as it needs, and the old boxes are folded into it.
 */
return new class extends Migration
{
    private const OLD = ['message_one', 'message_two', 'message_three', 'message_four', 'message_five'];

    public function up(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->json('notes')->nullable()->after('shift');
        });

        DB::table('shift_handovers')->orderBy('id')->each(function ($row) {
            $notes = collect(self::OLD)
                ->map(fn ($column) => trim((string) $row->{$column}))
                ->filter()->values()->all();

            DB::table('shift_handovers')->where('id', $row->id)->update(['notes' => json_encode($notes)]);
        });

        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->dropColumn(self::OLD);
        });
    }

    public function down(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            foreach (self::OLD as $column) {
                $table->text($column)->nullable()->after('shift');
            }
        });

        // Only five boxes to go back into; anything past the fifth joins the last
        DB::table('shift_handovers')->orderBy('id')->each(function ($row) {
            $notes = json_decode((string) $row->notes, true) ?: [];
            $update = [];
            foreach (self::OLD as $i => $column) {
                $update[$column] = $i < 4 ? ($notes[$i] ?? null) : (implode("\n", array_slice($notes, 4)) ?: null);
            }
            DB::table('shift_handovers')->where('id', $row->id)->update($update);
        });

        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
