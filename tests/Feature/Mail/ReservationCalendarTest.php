<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * **«AÑADIR AL CALENDARIO» DE UNA RESERVA** (la R2 de `specs/correos-rediseno.md` §4.3, `ReservationCalendarController`): el
 * `.ics` del resguardo de los correos de la reserva. FIRMADO; en la hora de PARED del parque, con la duración efectiva
 * (`OrderItem::visitWindow()`, la de la invitación); y una reserva que ya no vale da el mismo 404.
 */
class ReservationCalendarTest extends TestCase
{
    use RefreshDatabase;

    /** ⏰ El ahora de los casos, en la hora del parque: antes de la reserva del fixture (`TESTING.md` §2). */
    private const AHORA = '2026-09-20 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse(self::AHORA, DisplayTime::timezone()));
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid']);
        Setting::updateOrCreate(['key' => 'business.name'], ['value' => 'SaltoPark']);
        Setting::updateOrCreate(['key' => 'address.line1'], ['value' => 'Calle Mayor, 3']);
        Setting::flushMemo();
    }

    public function test_the_signed_link_gives_the_wall_clock_event_of_the_reservation(): void
    {
        $reserva = $this->reserva();

        $ics = (string) $this->get(URL::signedRoute('reserva.calendario', ['reserva' => $reserva->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->getContent();

        $this->assertStringContainsString('DTSTART;TZID=Europe/Madrid:20260926T170000', $ics, 'la hora de PARED, con su zona');
        $this->assertStringContainsString('DTEND;TZID=Europe/Madrid:20260926T183000', $ics, 'la duración EFECTIVA: 60 + 30 de hora extra');
        $this->assertStringContainsString('SUMMARY:Kids 1 hora · SaltoPark', $ics);
        $this->assertStringContainsString('LOCATION:SaltoPark\, Calle Mayor\, 3', $ics);
    }

    public function test_without_its_signature_or_once_the_reservation_is_gone_it_does_not_open(): void
    {
        $reserva = $this->reserva();

        $this->get(route('reserva.calendario', ['reserva' => $reserva->id]))->assertForbidden();

        $reserva->forceFill(['cancelled_at' => now()])->save();
        $this->get(URL::signedRoute('reserva.calendario', ['reserva' => $reserva->id]))->assertNotFound();

        // CONTROL: la misma reserva, viva y con su pedido pagado, sí.
        $reserva->forceFill(['cancelled_at' => null])->save();
        $this->get(URL::signedRoute('reserva.calendario', ['reserva' => $reserva->id]))->assertOk();

        // Un pedido que no se pagó, no.
        $reserva->order?->forceFill(['status' => Order::STATUS_EXPIRED])->save();
        $this->get(URL::signedRoute('reserva.calendario', ['reserva' => $reserva->id]))->assertNotFound();
    }

    private function reserva(): OrderItem
    {
        $zona = Zone::create(['slug' => 'kids', 'name' => ['es' => 'Kids'], 'is_active' => true]);
        $tipo = TicketType::create([
            'name' => ['es' => 'Kids 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zona->id, 'duration_min' => 60,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $franja = Slot::create([
            'zone_id' => $zona->id, 'date' => '2026-09-26', 'start_time' => '17:00:00', 'end_time' => '18:00:00',
            'capacity' => 40, 'online_capacity' => 40,
        ]);
        $pedido = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'R-7K2P4', 'status' => Order::STATUS_PAID,
            'subtotal' => 2400, 'tax' => 0, 'total' => 2400, 'currency' => 'EUR', 'paid_at' => now(),
        ]);

        return $pedido->items()->create([
            'ticket_type_id' => $tipo->id, 'slot_id' => $franja->id, 'quantity' => 2, 'unit_price' => 1200, 'seats' => 2,
            'extra_minutes' => 30,
        ]);
    }
}
