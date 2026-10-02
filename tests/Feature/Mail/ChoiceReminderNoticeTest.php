<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\AddonChoiceGroup;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\ChoiceReminderNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * **«FALTA ELEGIR…», EL DÍA ANTES DE QUE SE CIERRE LA LISTA** (P4 de `fiesta-sistema-nuevo.md` §4.20; `[DECIDIDO owner]` `#913`
 * —«el día antes del plazo», un correo— y `#914`).
 *
 * La fiesta de prueba es el 20 a las 17:00 del parque y la lista cierra 24 h antes (el 19 a las 17:00), así que el aviso tiene
 * su ventana del 18 a las 17:00 al 19 a las 17:00. Cada caso, con su control.
 */
class ChoiceReminderNoticeTest extends TestCase
{
    use RefreshDatabase;

    private TicketType $pack;

    private TicketType $pizza;

    private AddonChoiceGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo($this->at('2026-10-10 12:00'));

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
        $this->group = AddonChoiceGroup::create(['product_id' => $this->pack->id, 'key' => 'merienda', 'title' => ['es' => '¿Qué merienda?'], 'is_required' => true]);
        $this->pizza = TicketType::create([
            'name' => ['es' => 'Pizza'], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 20,
        ]);
        $this->pack->configurableAddons()->attach($this->pizza->id, [
            'position' => 1, 'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'is_included' => true,
            'stage' => ProductAddon::STAGE_POSTFORM, 'choice_group' => 'merienda',
        ]);
        $this->slot = Slot::create([
            'zone_id' => $zone->id, 'date' => '2026-10-20', 'start_time' => '17:00:00', 'end_time' => '19:00:00',
            'capacity' => 40, 'online_capacity' => 40,
        ]);
    }

    private Slot $slot;

    public function test_it_writes_once_the_day_before_the_list_closes_although_it_runs_every_hour(): void
    {
        $item = $this->party();
        Notification::fake();

        $this->travelTo($this->at('2026-10-18 18:00'));
        $this->artisan('reservations:choice-reminder')->assertSuccessful();
        $this->travelTo($this->at('2026-10-18 19:00'));
        $this->artisan('reservations:choice-reminder')->assertSuccessful();

        Notification::assertSentToTimes($item->order->user, ChoiceReminderNotice::class, 1);
        $this->assertNotNull($item->fresh()?->choice_reminder_at, 'la marca, en su fila');
    }

    public function test_not_before_its_window_nor_once_the_list_has_closed(): void
    {
        $this->party();
        Notification::fake();

        $this->travelTo($this->at('2026-10-18 16:59'));
        $this->artisan('reservations:choice-reminder')->assertSuccessful();
        $this->travelTo($this->at('2026-10-19 17:00'));
        $this->artisan('reservations:choice-reminder')->assertSuccessful();

        Notification::assertNothingSent();

        // CONTROL: un minuto antes de cerrar, todavía sí.
        $this->travelTo($this->at('2026-10-19 16:59'));
        $this->artisan('reservations:choice-reminder')->assertSuccessful();
        Notification::assertSentTimes(ChoiceReminderNotice::class, 1);
    }

    public function test_a_party_sold_inside_its_window_is_not_written_to(): void
    {
        $this->travelTo($this->at('2026-10-18 17:30'));
        $this->party();
        Notification::fake();

        $this->travelTo($this->at('2026-10-18 18:00'));
        $this->artisan('reservations:choice-reminder')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_with_the_choice_made_or_an_optional_group_nobody_is_written_to(): void
    {
        $item = $this->party();
        app(PostFormAddons::class)->reconcile($item, [$this->pizza->id => 1], 'signed_link');
        $optional = $this->party(code: 'JJ-OPT01');
        $this->group->forceFill(['is_required' => false])->save();
        Notification::fake();

        $this->travelTo($this->at('2026-10-18 18:00'));
        $this->artisan('reservations:choice-reminder')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($optional->fresh()?->choice_reminder_at, 'sin aviso no hay marca');
    }

    public function test_the_email_names_what_is_missing_when_the_list_closes_and_where_to_choose(): void
    {
        $item = $this->party();

        $mail = (new ChoiceReminderNotice($item, ['merienda']))->toMail($item->order->user);
        $html = (string) $mail->render();

        $this->assertSame('Falta elegir en tu lista · '.$item->order->code, $mail->subject);
        $this->assertStringContainsString('¿Qué merienda?', $html, 'el título del grupo, el que puso el parque');
        $this->assertStringContainsString(e(__('emails.choice_reminder.intro', ['day' => DisplayTime::dayInSentence($this->at('2026-10-19 17:00')), 'hora' => '17:00'])), $html);
        $this->assertStringContainsString(e(__('emails.choice_reminder.park_decides')), $html);
        $this->assertStringContainsString('/reserva/'.$item->id.'/datos-invitados', $html, 'el botón lleva a la lista');
    }

    public function test_it_does_not_go_out_if_the_choice_was_made_while_it_waited_in_the_queue(): void
    {
        $item = $this->party();
        $notice = new ChoiceReminderNotice($item, ['merienda']);
        $this->assertTrue($notice->shouldSend($item->order->user, 'mail'));

        app(PostFormAddons::class)->reconcile($item, [$this->pizza->id => 1], 'signed_link');

        $this->assertFalse($notice->shouldSend($item->order->user, 'mail'), 'elegida mientras esperaba: un «falta elegir» falso, no');
    }

    // ── Fixture ─────────────────────────────────────────────────────────────────────────────────

    private function at(string $wall): Carbon
    {
        return Carbon::parse($wall, DisplayTime::timezone());
    }

    /** Una fiesta pagada de 6, vendida AHORA (la hora a la que viaja cada caso). */
    private function party(string $code = 'JJ-ELIGE1'): OrderItem
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => $code,
            'status' => Order::STATUS_PAID, 'subtotal' => 15000, 'tax' => 0, 'total' => 15000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id, 'amount' => 15000, 'currency' => 'EUR',
            'provider' => 'redsys', 'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) $order->id, 10, '0', STR_PAD_LEFT),
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack->id, 'slot_id' => $this->slot->id, 'quantity' => 6, 'unit_price' => 2500, 'seats' => 6,
        ]);

        return Order::with(['items.children', 'items.slot', 'items.ticketType.addons', 'adjustments', 'payments.refunds', 'user'])
            ->findOrFail($order->id)->items->firstWhere('parent_item_id', null);
    }
}
