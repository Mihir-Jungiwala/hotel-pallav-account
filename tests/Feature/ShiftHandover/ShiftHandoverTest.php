<?php

namespace Tests\Feature\ShiftHandover;

use App\Models\ShiftHandover;
use App\Models\User;
use App\Support\RichText;
use App\Support\UnitContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        UnitContext::flush();
        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);
    }


    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date' => now()->toDateString(), 'time' => '14:00', 'shift' => 'Morning',
        ], $overrides);
    }

    public function test_the_list_renders_points_and_the_pop_up_form(): void
    {
        $record = ShiftHandover::create([
            'date' => now()->toDateString(), 'time' => '09:00', 'shift' => 'Morning',
            'user_id' => $this->user->id, 'full_name' => $this->user->name,
            'notes' => ['Room 204 <strong>checks out</strong> early'], 'instructions' => ['Keep the suite ready'],
            'total' => 1500, 'total_in_words' => 'One Thousand Five Hundred Rupees Only',
        ]);

        $this->get(route('shift-handover.index'))->assertOk()
            ->assertSee('<strong>checks out</strong>', false)
            ->assertSee('Keep the suite ready')
            ->assertSee('One Thousand Five Hundred Rupees Only')
            ->assertSee('id="handoverModal"', false)
            ->assertSee('data-handover-id="'.$record->id.'"', false);

        $this->get(route('shift-handover.view', $record))->assertOk();
    }

    public function test_a_refused_save_reopens_the_pop_up_with_what_was_typed(): void
    {
        $this->from(route('shift-handover.index'))
            ->post(route('shift-handover.store'), [
                '_form' => 'handover', 'shift' => 'Morning', 'notes' => ['Typed before the error'],
            ])->assertSessionHasErrors('date');

        $this->get(route('shift-handover.index'))->assertSee('Typed before the error');
    }




    public function test_notes_and_instructions_are_saved_as_points(): void
    {
        $notes = array_map(fn ($i) => "Note <em>{$i}</em>", range(1, 8));

        $this->post(route('shift-handover.store'), $this->payload([
            'notes' => [...$notes, '', '<br>', '   '],
            'instructions' => ['First', '  ', 'Second '],
        ]))->assertSessionHasNoErrors();

        $record = ShiftHandover::first();
        $this->assertSame($notes, $record->notes);
        $this->assertSame(['First', 'Second'], $record->instructions);
    }

    public function test_the_total_is_worked_out_and_written_in_words(): void
    {
        $this->post(route('shift-handover.store'), $this->payload([
            'd500_count' => 3, 'd100_count' => 2, 'coins_count' => 7,
        ]));

        $record = ShiftHandover::first();
        $this->assertEquals(1707, $record->total);
        $this->assertSame('One Thousand Seven Hundred Seven Rupees Only', $record->total_in_words);
        $this->assertEquals(1500, $record->d500_total);
    }

    public function test_note_points_keep_formatting_but_lose_anything_unsafe(): void
    {
        $this->post(route('shift-handover.store'), $this->payload([
            'notes' => [
                '<span onclick="x()"><strong>VIP</strong> in <em>301</em></span><script>alert(1)</script>',
                '<a href="javascript:alert(1)">bad</a> and <a href="https://example.com" style="x">ok</a>',
            ],
        ]));

        [$first, $second] = ShiftHandover::first()->notes;

        $this->assertSame('<strong>VIP</strong> in <em>301</em>', $first);
        $this->assertStringContainsString('href="https://example.com"', $second);
        $this->assertStringNotContainsString('javascript', $second);
        $this->assertStringNotContainsString('style=', $second);
    }

    public function test_escaped_text_stays_escaped(): void
    {
        $clean = RichText::inline('Use &lt;script&gt; <b>carefully</b>');

        $this->assertStringContainsString('&lt;script&gt;', $clean);
        $this->assertStringNotContainsString('<script>', $clean);
    }

    public function test_plain_text_from_older_records_is_escaped(): void
    {
        $this->assertSame('Tom &amp; Jerry<br>in 204', RichText::inline("Tom & Jerry\nin 204"));
        $this->assertNull(RichText::inline('<br> '));
    }


    public function test_editing_replaces_the_points(): void
    {
        $record = ShiftHandover::create([
            'date' => now()->toDateString(), 'time' => '08:00', 'shift' => 'Morning',
            'user_id' => $this->user->id, 'full_name' => $this->user->name, 'notes' => ['Old'],
        ]);

        $this->put(route('shift-handover.update', $record), $this->payload(['notes' => ['Updated'], 'instructions' => ['Lock the safe']]))
            ->assertRedirect(route('shift-handover.index'));

        $this->assertSame(['Updated'], $record->fresh()->notes);
        $this->assertSame(['Lock the safe'], $record->fresh()->instructions);
    }

    public function test_the_shift_is_picked_from_the_time_of_day(): void
    {
        $shifts = ['Morning', 'Afternoon', 'Evening', 'Night'];

        $this->assertSame('Morning', ShiftHandover::shiftAt('06:00', $shifts));
        $this->assertSame('Morning', ShiftHandover::shiftAt('11:59', $shifts));
        $this->assertSame('Afternoon', ShiftHandover::shiftAt('12:00', $shifts));
        $this->assertSame('Evening', ShiftHandover::shiftAt('21:30', $shifts));
        $this->assertSame('Night', ShiftHandover::shiftAt('23:10', $shifts));
        $this->assertSame('Night', ShiftHandover::shiftAt('02:00', $shifts));

        // Renamed shifts still match; unknown names leave it to the default
        $this->assertSame('Morning Shift', ShiftHandover::shiftAt('08:00', ['Morning Shift', 'Night Shift']));
        $this->assertNull(ShiftHandover::shiftAt('08:00', ['A', 'B']));
    }

    public function test_a_handover_cannot_be_dated_in_the_future(): void
    {
        $this->post(route('shift-handover.store'), $this->payload(['date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('date');
    }

    public function test_the_search_finds_handovers_by_word_number_and_date(): void
    {
        $make = fn (array $attrs) => ShiftHandover::create(array_merge([
            'date' => '2026-09-01', 'time' => '08:00', 'shift' => 'Morning',
            'user_id' => $this->user->id, 'full_name' => 'Asha Patel',
        ], $attrs));

        $one = $make(['notes' => ['Laundry pickup at five']]);
        $two = $make(['date' => '2026-09-02', 'shift' => 'Night', 'full_name' => 'Ravi Shah', 'instructions' => ['Lock the safe'], 'total' => 1500]);

        $search = fn (string $q) => $this->get(route('shift-handover.index', ['q' => $q]));

        $search('laundry')->assertSee('Laundry pickup')->assertDontSee('Ravi Shah');
        $search('safe')->assertSee('Lock the safe')->assertDontSee('Laundry pickup');
        $search('ravi night')->assertSee('Lock the safe')->assertDontSee('Laundry pickup');
        $search('#'.$two->entryNumber())->assertSee('Lock the safe')->assertDontSee('Laundry pickup');
        $search('02-09-2026')->assertSee('Lock the safe')->assertDontSee('Laundry pickup');
        $search('2026-09-01')->assertSee('Laundry pickup')->assertDontSee('Lock the safe');
        $search('1,500')->assertSee('Lock the safe')->assertDontSee('Laundry pickup');
        $search('nothingmatches')->assertOk()->assertSee('No Handover matches that search');
    }

    public function test_the_search_survives_paging(): void
    {
        foreach (range(1, 12) as $i) {
            ShiftHandover::create([
                'date' => now()->toDateString(), 'time' => sprintf('%02d:00', $i), 'shift' => 'Night',
                'user_id' => $this->user->id, 'full_name' => 'Ravi Shah',
            ]);
        }

        $this->get(route('shift-handover.index', ['q' => 'ravi']))->assertSee('Showing 1-10 of 12')
            ->assertSee('name="q" value="ravi"', false);
        $this->get(route('shift-handover.index', ['q' => 'ravi', 'page' => 2]))->assertSee('Showing 11-12 of 12');
    }
}
