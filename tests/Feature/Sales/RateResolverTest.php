<?php

namespace Tests\Feature\Sales;

use App\Models\RateType;
use App\Models\SpecialDate;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\RateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 5.0 — El RateResolver decide la tarifa de un día (#59): festivo/finde/víspera
 * = especial; el resto = normal. `special_dates` manda sobre la regla de día de semana.
 */
class RateResolverTest extends TestCase
{
    use RefreshDatabase;

    private RateResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create([
            'key' => RateType::KEY_NORMAL,
            'label' => ['es' => 'Día normal'],
            'is_special' => false,
            'weekdays' => null,
            'priority' => 0,
        ]);
        RateType::create([
            'key' => RateType::KEY_SPECIAL,
            'label' => ['es' => 'Especial'],
            'is_special' => true,
            'weekdays' => [0, 6], // domingo y sábado
            'priority' => 10,
        ]);

        $this->resolver = new RateResolver;
    }

    public function test_weekday_resolves_to_normal(): void
    {
        $tuesday = Carbon::now()->next(Carbon::TUESDAY);

        $this->assertSame(RateType::KEY_NORMAL, $this->resolver->for($tuesday)->key);
    }

    public function test_weekend_resolves_to_special(): void
    {
        $saturday = Carbon::now()->next(Carbon::SATURDAY);

        $this->assertSame(RateType::KEY_SPECIAL, $this->resolver->for($saturday)->key);
    }

    public function test_special_date_overrides_weekday_rule(): void
    {
        $tuesday = Carbon::now()->next(Carbon::TUESDAY);

        SpecialDate::create([
            'date' => $tuesday->toDateString(),
            'rate_type_id' => RateType::where('key', RateType::KEY_SPECIAL)->value('id'),
        ]);

        // Aunque es día de semana, el festivo marcado fuerza la tarifa especial.
        $this->assertSame(RateType::KEY_SPECIAL, $this->resolver->for($tuesday)->key);
    }

    public function test_price_cents_uses_the_applicable_rate(): void
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $ticket = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'],
            'zone_id' => $zone->id,
            'duration_min' => 60,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 1,
        ]);
        $ticket->prices()->create([
            'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'),
            'amount_cents' => 1000,
        ]);
        $ticket->prices()->create([
            'rate_type_id' => RateType::where('key', RateType::KEY_SPECIAL)->value('id'),
            'amount_cents' => 1500,
        ]);

        $this->assertSame(1000, $this->resolver->priceCents($ticket, Carbon::now()->next(Carbon::TUESDAY)));
        $this->assertSame(1500, $this->resolver->priceCents($ticket, Carbon::now()->next(Carbon::SATURDAY)));
    }
}
