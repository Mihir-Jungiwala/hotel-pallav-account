<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('full_name', 20)->nullable();
            $table->string('shift', 20)->nullable();
            $table->text('message_one')->nullable();
            $table->text('message_two')->nullable();
            $table->text('message_three')->nullable();
            $table->text('message_four')->nullable();
            $table->text('message_five')->nullable();
            $table->text('special_instruction')->nullable();

            foreach (['d500', 'd200', 'd100', 'd50', 'd20', 'd10', 'd5', 'coins'] as $denom) {
                $table->decimal("{$denom}_total", 10, 2)->default(0);
                $table->string("{$denom}_count", 10)->default('0');
            }

            $table->decimal('total', 10, 2)->default(0);
            $table->string('total_in_words', 250)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_handovers');
    }
};
