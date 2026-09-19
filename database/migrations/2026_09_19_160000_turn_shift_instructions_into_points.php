<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Special instructions become a list of points, like the notes. Whatever was
 * written before is split on its lines, list items and paragraphs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->json('instructions')->nullable()->after('notes');
        });

        DB::table('shift_handovers')->orderBy('id')->each(function ($row) {
            $text = (string) $row->special_instruction;
            $text = preg_replace('/<(br|\/p|\/li|\/div|\/h\d|\/blockquote)[^>]*>/i', "\n", $text);
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $points = collect(preg_split('/\R/', $text))
                ->map(fn ($line) => trim(str_replace("\xC2\xA0", ' ', $line)))
                ->filter()->values()->all();

            DB::table('shift_handovers')->where('id', $row->id)->update(['instructions' => json_encode($points)]);
        });

        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->dropColumn('special_instruction');
        });
    }

    public function down(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->text('special_instruction')->nullable()->after('notes');
        });

        DB::table('shift_handovers')->orderBy('id')->each(function ($row) {
            $points = json_decode((string) $row->instructions, true) ?: [];
            DB::table('shift_handovers')->where('id', $row->id)
                ->update(['special_instruction' => $points ? implode("\n", $points) : null]);
        });

        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->dropColumn('instructions');
        });
    }
};
