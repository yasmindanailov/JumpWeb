<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Domain\Platform\Services\PersonNameKey;
use App\Filament\Analytics\PartiesReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **La fiesta, contra un junio sembrado** (`docs/specs/analitica-fiesta.md` §4.3 y §6, T2; `#739`).
 *
 * El parque en `Europe/Madrid`, el reloj en el 2026-06-10. Dos fiestas de junio: la de Lucía (el 6) completa el
 * circuito —formulario abierto dos veces y completado tres días antes, dos extras de tarta y un invitado más, una
 * invitación vista tres veces con dos «sí» (uno adoptado) y un «no», el calendario bajado, el justificante abierto
 * dos veces y firmado desde la invitación media hora después, 35 € cobrados en efectivo— y la de Mateo (el 20), que
 * solo abrió el formulario y lleva el justificante marcado. Fuera del periodo o del censo: una fiesta de mayo (la
 * comparación), un pedido sin pagar, una entrada que no es pack, una reserva cancelada, una edición del PANEL y un
 * hecho de otra reserva del mismo pedido.
 *
 * Lo que vigila: que cada cifra coincida con una SUMA DIRECTA sobre las tablas de negocio; el embudo por reserva;
 * los tiempos en días del parque; la serie por día de fiesta; el periodo anterior; el presupuesto de consultas; y la
 * caché.
 */
class PartiesReportTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Europe/Madrid';

    private TicketType $pack;

    private TicketType $cake;

    private TicketType $entry;

    private User $host;

    private int $counter = 0;

    /** @var array<string, OrderItem> */
    private array $parties = [];

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => self::TZ, 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        Cache::flush();

        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id, 'duration_min' => 120,
            'min_qty' => 1, 'max_qty' => 20, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_invitation' => true, 'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
        ]);
        $this->cake = TicketType::create([
            'name' => ['es' => 'Tarta'], 'type' => TicketType::TYPE_ADDON, 'zone_id' => $zone->id, 'duration_min' => 0,
            'seats_per_unit' => 0, 'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 3,
        ]);
        $this->host = User::factory()->create();
    }

    private function june(): Window
    {
        return Window::ofDays(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'), self::TZ);
    }

    // ─── El fixture ─────────────────────────────────────────────────────────────────────────────

    private function seedJune(): void
    {
        // Lucía, el 6 de junio a las 17:00: el circuito entero.
        $lucia = $this->party('2026-06-06', completedAt: '2026-06-03 10:00:00');
        $cake = $this->child($lucia, $this->cake, 2);
        $this->edit($cake, 2000, 'postform_addon', 0, 2);
        $this->edit($lucia, 1500, 'guest_count_increase', 10, 11);
        $this->edit($lucia, -500, null);   // una edición del PANEL: aparte
        $this->payment($lucia->order, 3500, 'cash');
        $invitation = $this->invitation($lucia);
        $yes = $this->reply($invitation, $lucia, 'Hugo Ruiz', true, adopted: true);
        $this->reply($invitation, $lucia, 'Vera Gil', true);
        $this->reply($invitation, $lucia, 'Noa Sanz', false);
        $this->signature($lucia, '2026-06-04 18:00:00', fromReply: $yes);
        $this->fact($lucia, 'guest_form_opened', ['days_before' => 5, 'device' => 'desktop', 'locale' => 'es']);
        $this->fact($lucia, 'guest_form_opened', ['days_before' => 3, 'device' => 'desktop', 'locale' => 'es']);
        $this->fact($lucia, 'guest_form_submitted', ['days_before' => 3, 'guests_delta' => 1, 'extras_cents' => 2000, 'replies_adopted' => 1]);
        $this->fact($lucia, 'invitation_viewed', ['days_before' => 4, 'device' => 'mobile', 'locale' => 'es']);
        $this->fact($lucia, 'invitation_viewed', ['days_before' => 4, 'device' => 'mobile', 'locale' => 'es']);
        $this->fact($lucia, 'invitation_viewed', ['days_before' => 2, 'device' => 'desktop', 'locale' => 'en']);
        $this->fact($lucia, 'invitation_calendar_downloaded', ['days_before' => 4]);
        $this->fact($lucia, 'authorization_opened', ['via' => 'invitation', 'days_before' => 2, 'device' => 'mobile']);
        $this->fact($lucia, 'authorization_opened', ['via' => 'link', 'days_before' => 2, 'device' => 'mobile']);
        $this->fact($lucia, 'authorization_signed', ['via' => 'invitation', 'days_before' => 2, 'hours_since_open' => 0.5]);
        // Un hecho del MISMO pedido pero de otra reserva (una fiesta fuera del periodo): no cuenta.
        $this->fact($lucia, 'invitation_viewed', ['days_before' => 1, 'device' => 'tablet', 'locale' => 'fr'], reservation: 999999);

        // Mateo, el 20 de junio: abrió el formulario y nada más; el justificante va marcado desde la compra.
        $mateo = $this->party('2026-06-20', marked: true);
        $this->fact($mateo, 'guest_form_opened', ['days_before' => 12, 'device' => 'mobile', 'locale' => 'es']);

        // Mayo (el periodo anterior): completa, con extras y una firma.
        $mayo = $this->party('2026-05-28', completedAt: '2026-05-20 10:00:00');
        $this->edit($this->child($mayo, $this->cake, 1), 1000, 'postform_addon', 0, 1);
        $this->signature($mayo, '2026-05-25 10:00:00');

        // Fuera del censo: sin pagar, una entrada, y una reserva cancelada.
        $this->party('2026-06-25', status: Order::STATUS_PENDING);
        $this->party('2026-06-15', type: $this->entry);
        $this->party('2026-06-28')->forceFill(['cancelled_at' => now()])->saveQuietly();
    }

    private function party(string $date, ?string $completedAt = null, bool $marked = false, string $status = Order::STATUS_PAID, ?TicketType $type = null): OrderItem
    {
        $type ??= $this->pack;
        $slot = Slot::firstOrCreate(
            ['zone_id' => $type->zone_id, 'date' => $date, 'start_time' => '17:00:00'],
            ['end_time' => '19:00:00', 'capacity' => 200, 'online_capacity' => 200],
        );
        $order = Order::create([
            'user_id' => $this->host->id, 'code' => 'F-'.Str::upper(Str::random(6)), 'status' => $status,
            'subtotal' => 5000, 'tax' => 0, 'total' => 5000, 'currency' => 'EUR',
            'paid_at' => $status === Order::STATUS_PAID ? '2026-05-15 10:00:00' : null,
        ]);
        /** @var OrderItem $item */
        $item = $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'quantity' => 10, 'unit_price' => 500, 'seats' => 10,
            'event_data' => ['celebrant' => 'Peque'],
        ]);
        $item->forceFill(['guest_form_completed_at' => $completedAt, 'guardian_authorization' => $marked])->saveQuietly();
        $this->parties[$date] = $item;

        return $item;
    }

    private function child(OrderItem $principal, TicketType $addon, int $quantity): OrderItem
    {
        /** @var OrderItem $child */
        $child = $principal->order->items()->create([
            'ticket_type_id' => $addon->id, 'parent_item_id' => $principal->id, 'quantity' => $quantity, 'unit_price' => 1000, 'seats' => 0,
        ]);

        return $child;
    }

    private function edit(OrderItem $item, int $cents, ?string $reason, int $from = 0, int $to = 0): void
    {
        OrderAdjustment::create([
            'order_id' => $item->order_id, 'order_item_id' => $item->id, 'type' => OrderAdjustment::TYPE_EDIT,
            'amount_cents' => $cents, 'currency' => 'EUR', 'reason' => $reason, 'applied_by' => $this->host->id,
            'context' => $reason === null ? null : ['changes' => ['quantity_change' => ['old' => $from, 'new' => $to]]],
        ]);
    }

    private function payment(Order $order, int $amount, string $provider): void
    {
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id, 'amount' => $amount, 'currency' => 'EUR',
            'provider' => $provider, 'status' => Payment::STATUS_PAID, 'paid_at' => '2026-06-06 19:30:00',
            'gateway_order' => str_pad((string) (900000 + ++$this->counter), 10, '0', STR_PAD_LEFT),
        ]);
    }

    private function invitation(OrderItem $reservation): int
    {
        return (int) DB::table('party_invitations')->insertGetId([
            'order_item_id' => $reservation->id, 'token' => Str::random(12), 'theme' => 'jump', 'honoree_name' => 'Lucía',
            'host_line' => 'Te invita Marta', 'show_host_phone' => false, 'reminded_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function reply(int $invitation, OrderItem $reservation, string $child, bool $attending, bool $adopted = false): int
    {
        return (int) DB::table('invitation_replies')->insertGetId([
            'party_invitation_id' => $invitation, 'order_item_id' => $reservation->id, 'attending' => $attending,
            'child_name' => $child, 'child_key' => PersonNameKey::for($child), 'adopted_at' => $adopted ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function signature(OrderItem $reservation, string $createdAtUtc, ?int $fromReply = null): void
    {
        DB::table('guardian_authorizations')->insert([
            'order_item_id' => $reservation->id, 'invitation_reply_id' => $fromReply,
            'minor_name' => 'Hugo', 'minor_surname' => 'Ruiz', 'minor_key' => PersonNameKey::for('Hugo Ruiz'), 'minor_born_on' => '2018-01-01',
            'guardian_name' => 'Marta', 'guardian_surname' => 'Ruiz', 'guardian_relationship' => 'mother',
            'guardian_email' => 'marta@example.com', 'guardian_phone' => null, 'created_at' => $createdAtUtc,
        ]);
    }

    /**
     * Un hecho de la reserva, como lo escribe `Recorder::factOfOrder()`: el pedido en la columna y la reserva en las props.
     *
     * @param  array<string, scalar>  $props
     * @param  int|null  $reservation  otra reserva del mismo pedido, para el caso que no debe contar
     */
    private function fact(OrderItem $of, string $name, array $props, ?int $reservation = null): void
    {
        DB::table('analytics_events')->insert([
            'event_id' => Visitor::mint(), 'session_id' => null, 'visitor_id' => null, 'user_id' => null, 'name' => $name, 'route' => null,
            'props' => json_encode(['reservation' => $reservation ?? $of->id] + $props),
            'occurred_at' => '2026-06-02 10:00:00', 'received_at' => '2026-06-02 10:00:00', 'order_id' => $of->order_id,
        ]);
    }

    // ─── Las cifras, contra las tablas ──────────────────────────────────────────────────────────

    public function test_the_parties_of_the_period_are_the_paid_pack_reservations_by_party_day(): void
    {
        $this->seedJune();
        $r = PartiesReport::for($this->june());

        $this->assertSame(2, $r['parties']);
        $this->assertSame(['from' => '2026-06-01', 'to' => '2026-06-30', 'days' => 30, 'granularity' => 'day'], $r['window']);
    }

    public function test_the_funnel_counts_reservations_per_step_and_ignores_a_fact_of_another_reservation(): void
    {
        $this->seedJune();
        $funnel = collect(PartiesReport::for($this->june())['funnel'])->keyBy('step');

        $this->assertSame(PartiesReport::STEPS, $funnel->keys()->all());
        $this->assertSame(2, $funnel['parties']['reached']);
        $this->assertSame(2, $funnel['form_opened']['reached']);
        $this->assertSame(1, $funnel['form_completed']['reached']);
        $this->assertSame(1, $funnel['with_extras']['reached']);
        $this->assertSame(1, $funnel['with_invitation']['reached']);
        $this->assertSame(1, $funnel['invitation_viewed']['reached']);
        $this->assertSame(1, $funnel['with_reply']['reached']);
        $this->assertSame(1, $funnel['authorization_opened']['reached']);
        $this->assertSame(1, $funnel['signed']['reached']);
        $this->assertSame(10000, $funnel['form_opened']['of_parties_bp']);
        $this->assertSame(5000, $funnel['signed']['of_parties_bp']);
    }

    public function test_the_money_equals_the_ledger_and_the_park_payments_and_keeps_the_panel_apart(): void
    {
        $this->seedJune();
        $m = PartiesReport::for($this->june())['money'];
        $lucia = $this->parties['2026-06-06'];
        $items = OrderItem::query()->where('order_id', $lucia->order_id)->pluck('id')->all();

        // La suma DIRECTA sobre el libro, sin pasar por el informe.
        $ledger = static fn (array $reasons): int => (int) DB::table('order_adjustments')->whereIn('order_item_id', $items)->whereIn('reason', $reasons)->sum('amount_cents');
        $this->assertSame($ledger(PartiesReport::EXTRA_REASONS), $m['extras']);
        $this->assertSame($ledger(PartiesReport::GUEST_REASONS), $m['guests']);
        $this->assertSame(2000, $m['extras']);
        $this->assertSame(1500, $m['guests']);
        $this->assertSame(3500, $m['sold_after_booking']);
        $this->assertSame(-500, $m['panel_edits'], 'la edición del panel no es del formulario');
        $this->assertSame(1, $m['guests_added']);
        $this->assertSame(0, $m['guests_removed']);
        $this->assertSame(1, $m['with_extras']);
        $this->assertSame(2000, $m['avg_extras']);
        $this->assertSame([['addon' => 'Tarta', 'units' => 2, 'cents' => 2000]], $m['by_addon']);

        $park = (int) DB::table('payments')->where('payable_id', $lucia->order_id)->whereIn('provider', PartiesReport::PARK_PROVIDERS)->sum('amount');
        $this->assertSame($park, $m['collected_in_park']);
        $this->assertSame(3500, $m['collected_in_park']);
    }

    public function test_the_invitation_and_the_authorization_come_from_their_tables_and_the_openings_from_the_facts(): void
    {
        $this->seedJune();
        $r = PartiesReport::for($this->june());

        $this->assertSame(['with' => 1, 'views' => 3, 'viewed' => 1, 'replies_yes' => 2, 'replies_no' => 1, 'with_reply' => 1, 'adopted' => 1, 'calendar' => 1], $r['invitations']);
        $this->assertSame(['marked' => 1, 'openings' => 2, 'opened' => 1, 'signed' => 1, 'signatures' => 1, 'from_invitation' => 1], $r['authorizations']);
        $this->assertSame(['opened' => 2, 'completed' => 1, 'on_time' => 1, 'on_time_bp' => 10000, 'cutoff_hours' => app(GuestCountPolicy::class)->cutoffHours()], $r['forms']);
    }

    public function test_the_timing_is_in_park_days_with_its_medians_and_histogram(): void
    {
        $this->seedJune();
        $t = PartiesReport::for($this->june())['timing'];

        // Completado el 3 (Madrid) para el 6 → 3 días; firmado el 4 para el 6 → 2; media hora entre abrir y firmar.
        $this->assertSame(3, $t['form_days_median']);
        $this->assertSame(2, $t['sign_days_median']);
        $this->assertSame(0.5, $t['hours_since_open_median']);
        $this->assertSame(['late' => 0, 'same_day' => 0, 'd1_3' => 1, 'd4_7' => 0, 'd8_14' => 0, 'd15_plus' => 0], $t['form_days_histogram']);
    }

    public function test_the_guests_are_counted_by_device_and_locale_from_the_openings(): void
    {
        $this->seedJune();
        $g = PartiesReport::for($this->june())['guests'];

        $this->assertSame(['mobile' => 4, 'desktop' => 1], $g['devices']);
        $this->assertSame(['es' => 2, 'en' => 1], $g['locales']);
    }

    public function test_the_series_goes_by_party_day_without_gaps_and_the_previous_period_has_its_totals(): void
    {
        $this->seedJune();
        $r = PartiesReport::for($this->june());

        $series = collect($r['series'])->keyBy('key');
        $this->assertCount(30, $series);
        $this->assertSame(['key' => '2026-06-06', 'parties' => 1, 'completed' => 1, 'invitations' => 1, 'replies_yes' => 2, 'signatures' => 1, 'extras' => 2000], $series['2026-06-06']);
        $this->assertSame(['key' => '2026-06-20', 'parties' => 1, 'completed' => 0, 'invitations' => 0, 'replies_yes' => 0, 'signatures' => 0, 'extras' => 0], $series['2026-06-20']);
        $this->assertSame(0, $series['2026-06-25']['parties'], 'el pedido sin pagar no es una fiesta');

        $this->assertSame(['parties' => 1, 'completed' => 1, 'sold_after_booking' => 1000, 'collected_in_park' => 0, 'with_extras' => 1, 'avg_extras' => 1000, 'replies_yes' => 0, 'signatures' => 1], $r['previous']);
    }

    public function test_an_empty_period_is_all_zeros_and_no_medians(): void
    {
        $this->seedJune();
        $r = PartiesReport::for(Window::ofDays(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'), self::TZ));

        $this->assertSame(0, $r['parties']);
        $this->assertSame(0, $r['money']['sold_after_booking']);
        $this->assertSame([], $r['money']['by_addon']);
        $this->assertNull($r['timing']['form_days_median']);
        $this->assertSame(0, collect($r['funnel'])->sum('reached'));
        $this->assertSame(['devices' => [], 'locales' => []], $r['guests']);
    }

    public function test_the_query_budget_does_not_grow_with_the_parties(): void
    {
        $this->seedJune();
        app(GuestCountPolicy::class)->cutoffHours();   // el memo de `Setting`, caliente

        DB::flushQueryLog();
        DB::enableQueryLog();
        (new PartiesReport)->compute($this->june());
        $withTwo = count(DB::getQueryLog());

        foreach (range(1, 8) as $i) {
            $party = $this->party('2026-06-1'.$i, completedAt: '2026-06-01 10:00:00');
            $this->edit($this->child($party, $this->cake, 1), 1000, 'postform_addon', 0, 1);
            $this->fact($party, 'invitation_viewed', ['days_before' => 3, 'device' => 'mobile', 'locale' => 'es']);
        }

        DB::flushQueryLog();
        (new PartiesReport)->compute($this->june());
        $withTen = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(20, $withTwo, "el informe costó {$withTwo} consultas");
        $this->assertSame($withTwo, $withTen, 'el número de consultas crece con las fiestas');
    }

    public function test_the_report_is_cached_per_period(): void
    {
        $this->seedJune();
        PartiesReport::for($this->june());

        DB::flushQueryLog();
        DB::enableQueryLog();
        PartiesReport::for($this->june());
        $this->assertCount(0, DB::getQueryLog(), 'la segunda lectura del mismo periodo no consulta');
        DB::disableQueryLog();
    }
}
