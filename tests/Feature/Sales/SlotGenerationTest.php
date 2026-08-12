<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\SlotTemplate;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Booking\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 5.0 / transversal §9.1 — El comando `slots:generate` crea las franjas concretas por
 * zona desde las plantillas, es idempotente, respeta los días cerrados y el HORARIO EFECTIVO
 * del día (`opening_hours` semanal + excepciones `special_dates`) (#DECIDIDO-B).
 */
class SlotGenerationTest extends TestCase
{
    use RefreshDatabase;

    /** Crea 4 plantillas horarias (10:00..13:00, 60 min) para un día y zona. */
    private function hourlyTemplates(Zone $zone, int $weekday): void
    {
        foreach (range(10, 13) as $hour) {
            SlotTemplate::create([
                'zone_id' => $zone->id,
                'weekday' => $weekday,
                'start_time' => sprintf('%02d:00:00', $hour),
                'duration_min' => 60,
                'capacity' => 50,
                'online_capacity' => 30,
            ]);
        }
    }

    public function test_generates_a_slot_for_a_matching_weekday(): void
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);

        SlotTemplate::create([
            'zone_id' => $zone->id,
            'weekday' => $wednesday->dayOfWeek,
            'start_time' => '10:00:00',
            'duration_min' => 60,
            'capacity' => 50,
            'online_capacity' => 30,
        ]);

        $this->artisan('slots:generate', [
            'from' => $wednesday->toDateString(),
            'to' => $wednesday->toDateString(),
        ])->assertSuccessful();

        $this->assertSame(1, Slot::count());

        $slot = Slot::first();
        $this->assertSame($zone->id, $slot->zone_id);
        $this->assertSame($wednesday->toDateString(), $slot->date->toDateString());
        $this->assertSame('11:00:00', $slot->end_time);  // 10:00 + 60 min
        $this->assertSame(50, $slot->capacity);
        $this->assertSame(30, $slot->online_capacity);
    }

    public function test_is_idempotent(): void
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);

        SlotTemplate::create([
            'zone_id' => $zone->id,
            'weekday' => $wednesday->dayOfWeek,
            'start_time' => '10:00:00',
            'duration_min' => 60,
            'capacity' => 50,
            'online_capacity' => 30,
        ]);

        $args = ['from' => $wednesday->toDateString(), 'to' => $wednesday->toDateString()];
        $this->artisan('slots:generate', $args)->assertSuccessful();
        $this->artisan('slots:generate', $args)->assertSuccessful();

        $this->assertSame(1, Slot::count());
    }

    public function test_skips_closed_days(): void
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);

        SlotTemplate::create([
            'zone_id' => $zone->id,
            'weekday' => $wednesday->dayOfWeek,
            'start_time' => '10:00:00',
            'duration_min' => 60,
            'capacity' => 50,
            'online_capacity' => 30,
        ]);

        SpecialDate::create(['date' => $wednesday->toDateString(), 'is_closed' => true]);

        $this->artisan('slots:generate', [
            'from' => $wednesday->toDateString(),
            'to' => $wednesday->toDateString(),
        ])->assertSuccessful();

        $this->assertSame(0, Slot::count());
    }

    public function test_does_not_generate_for_non_matching_weekday(): void
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);

        // Plantilla para el jueves; generamos solo el miércoles.
        SlotTemplate::create([
            'zone_id' => $zone->id,
            'weekday' => $wednesday->copy()->addDay()->dayOfWeek,
            'start_time' => '10:00:00',
            'duration_min' => 60,
            'capacity' => 50,
            'online_capacity' => 30,
        ]);

        $this->artisan('slots:generate', [
            'from' => $wednesday->toDateString(),
            'to' => $wednesday->toDateString(),
        ])->assertSuccessful();

        $this->assertSame(0, Slot::count());
    }

    public function test_only_generates_slots_inside_the_opening_hours_window(): void
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        $this->hourlyTemplates($zone, $wednesday->dayOfWeek);

        // Parque abierto 11:00–13:00 ese día → solo caben las franjas 11:00 y 12:00
        // (la de 10:00 empieza antes de abrir; la de 13:00 acabaría a las 14:00, tras cerrar).
        OpeningHour::create(['weekday' => $wednesday->dayOfWeek, 'open_time' => '11:00:00', 'close_time' => '13:00:00']);

        $this->artisan('slots:generate', [
            'from' => $wednesday->toDateString(),
            'to' => $wednesday->toDateString(),
        ])->assertSuccessful();

        $this->assertSame(['11:00:00', '12:00:00'], Slot::orderBy('start_time')->pluck('start_time')->all());
    }

    public function test_skips_a_weekday_closed_in_opening_hours(): void
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        $this->hourlyTemplates($zone, $wednesday->dayOfWeek);

        OpeningHour::create(['weekday' => $wednesday->dayOfWeek, 'is_closed' => true]);

        $this->artisan('slots:generate', [
            'from' => $wednesday->toDateString(),
            'to' => $wednesday->toDateString(),
        ])->assertSuccessful();

        $this->assertSame(0, Slot::count());
    }

    public function test_special_date_window_overrides_the_weekly_hours(): void
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        $this->hourlyTemplates($zone, $wednesday->dayOfWeek);

        OpeningHour::create(['weekday' => $wednesday->dayOfWeek, 'open_time' => '10:00:00', 'close_time' => '21:00:00']);
        // Excepción puntual con horario reducido 12:00–14:00 → manda sobre el semanal.
        SpecialDate::create(['date' => $wednesday->toDateString(), 'open_time' => '12:00:00', 'close_time' => '14:00:00']);

        $this->artisan('slots:generate', [
            'from' => $wednesday->toDateString(),
            'to' => $wednesday->toDateString(),
        ])->assertSuccessful();

        $this->assertSame(['12:00:00', '13:00:00'], Slot::orderBy('start_time')->pluck('start_time')->all());
    }
}
