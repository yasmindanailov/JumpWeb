<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Filament\Analytics\SegmentsReport;
use App\Filament\Widgets\Analytics\SegmentsWidget;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **Los segmentos** (`specs/analitica.md` §4.6, T4b): cada regla con su caso y su CONTROL —quien casi entra y no
 * entra—, siempre desde los PEDIDOS y el libro, nunca desde la fecha de nacimiento de un menor; el equipo y las
 * cuentas anonimizadas no cuentan; y la lista exportable solo lleva a quien dio el opt-in.
 */
class SegmentsReportTest extends TestCase
{
    use RefreshDatabase;

    private TicketType $jump;

    private TicketType $pack;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00'));
        Cache::flush();

        $zone = Zone::create(['slug' => 'z-'.Str::lower(Str::random(5)), 'name' => ['es' => 'Zona']]);
        $this->slot = Slot::create(['zone_id' => $zone->id, 'date' => now()->addDays(7)->toDateString(), 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 50, 'online_capacity' => 50]);
        $this->jump = TicketType::create(['name' => ['es' => 'Salto libre'], 'zone_id' => $zone->id, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]);
        $this->pack = TicketType::create(['name' => ['es' => 'Cumpleaños'], 'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2]);
    }

    private function customer(bool $optIn = false, array $attributes = []): User
    {
        return User::factory()->create(['marketing_opt_in' => $optIn] + $attributes);
    }

    private function order(User $user, string $paidAt, ?TicketType $type = null, string $status = Order::STATUS_PAID): Order
    {
        $order = Order::create(['user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => $status, 'paid_at' => Carbon::parse($paidAt), 'total' => 3000, 'expires_at' => now()->addMinutes(30)]);
        OrderItem::create(['order_id' => $order->id, 'ticket_type_id' => ($type ?? $this->jump)->id, 'slot_id' => $this->slot->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 3000]);

        return $order;
    }

    private function contact(User $user): void
    {
        AnalyticsEvent::create(['event_id' => Visitor::mint(), 'session_id' => null, 'visitor_id' => Visitor::mint(), 'user_id' => $user->id, 'name' => 'contact_received', 'occurred_at' => now(), 'received_at' => now()]);
    }

    private function guest(string $email, Order $order): void
    {
        DB::table('guardian_authorizations')->insert([
            'order_item_id' => $order->items()->value('id'),
            'minor_name' => 'Peque', 'minor_surname' => 'Invitado', 'minor_key' => Str::random(12), 'minor_born_on' => '2018-05-05',
            'guardian_name' => 'Madre', 'guardian_surname' => 'Invitada', 'guardian_relationship' => 'mother',
            'guardian_email' => $email, 'guardian_phone' => null, 'created_at' => now(),
        ]);
    }

    public function test_bought_once_and_never_came_back_needs_one_collected_order_older_than_a_season(): void
    {
        $in = $this->customer(optIn: true);
        $this->order($in, '2026-03-01 10:00:00');
        $recent = $this->customer();
        $this->order($recent, '2026-09-01 10:00:00');                     // volvió hace poco: no
        $twice = $this->customer();
        $this->order($twice, '2026-01-01 10:00:00');
        $this->order($twice, '2026-02-01 10:00:00');                      // dos compras: no
        $pending = $this->customer();
        $this->order($pending, '2026-01-01 10:00:00', status: Order::STATUS_PENDING);   // sin cobrar: no
        $refunded = $this->customer();
        $this->order($refunded, '2026-02-01 10:00:00', status: Order::STATUS_REFUNDED); // cobrado y devuelto: sí

        $counts = (new SegmentsReport)->compute();

        $this->assertSame(['size' => 2, 'opt_in' => 1], $counts[SegmentsReport::ONCE_NEVER_BACK]);
        $members = (new SegmentsReport)->members(SegmentsReport::ONCE_NEVER_BACK);
        $this->assertSame([$in->email], array_column($members, 'email'), 'solo quien dio el opt-in');
        $this->assertSame('01/03/2026', $members[0]['last_purchase']);
    }

    public function test_a_party_a_year_ago_is_the_last_pack_between_ten_and_twelve_months_ago_from_the_orders(): void
    {
        $in = $this->customer(optIn: true);
        $this->order($in, '2025-10-20 10:00:00', $this->pack);             // hace 11 meses: sí
        $again = $this->customer();
        $this->order($again, '2025-10-20 10:00:00', $this->pack);
        $this->order($again, '2026-08-01 10:00:00', $this->pack);          // volvió a celebrar: no
        $noPack = $this->customer();
        $this->order($noPack, '2025-10-20 10:00:00');                      // no era una fiesta: no
        $tooOld = $this->customer();
        $this->order($tooOld, '2025-08-01 10:00:00', $this->pack);         // hace más de 12 meses: no
        $tooRecent = $this->customer();
        $this->order($tooRecent, '2026-02-01 10:00:00', $this->pack);      // hace 7 meses: no

        $counts = (new SegmentsReport)->compute();

        $this->assertSame(['size' => 1, 'opt_in' => 1], $counts[SegmentsReport::PARTY_YEAR_AGO]);
        $this->assertSame([$in->email], array_column((new SegmentsReport)->members(SegmentsReport::PARTY_YEAR_AGO), 'email'));
    }

    public function test_a_guest_who_never_bought_is_a_guardian_email_without_a_collected_order_and_is_only_counted(): void
    {
        $host = $this->customer();
        $party = $this->order($host, '2026-06-01 10:00:00', $this->pack);
        $this->guest('madre@example.test', $party);
        $this->guest('MADRE@example.test', $party);                        // el mismo correo, otra fiesta: uno
        $this->guest('padre@example.test', $party);
        $buyer = $this->customer(optIn: true, attributes: ['email' => 'padre@example.test']);
        $this->order($buyer, '2026-07-01 10:00:00');                       // el padre compró después: no

        $counts = (new SegmentsReport)->compute();

        $this->assertSame(['size' => 1, 'opt_in' => 0], $counts[SegmentsReport::GUEST_NO_PURCHASE]);
        $this->assertSame([], (new SegmentsReport)->members(SegmentsReport::GUEST_NO_PURCHASE), 'sin cuenta no hay opt-in: no se exporta a nadie');
    }

    public function test_a_contact_without_an_order_is_an_identified_contact_fact_and_no_collected_order(): void
    {
        $in = $this->customer(optIn: true);
        $this->contact($in);
        $bought = $this->customer();
        $this->contact($bought);
        $this->order($bought, '2026-08-01 10:00:00');                      // escribió y compró: no
        $silent = $this->customer();                                       // ni escribió ni compró: no

        $counts = (new SegmentsReport)->compute();

        $this->assertSame(['size' => 1, 'opt_in' => 1], $counts[SegmentsReport::CONTACT_NO_ORDER]);
        $this->assertSame([$in->email], array_column((new SegmentsReport)->members(SegmentsReport::CONTACT_NO_ORDER), 'email'));
    }

    /** El equipo y una cuenta anonimizada no son clientes de ningún segmento, aunque sus pedidos digan que sí. */
    public function test_the_team_and_an_anonymized_account_never_count(): void
    {
        $staff = $this->customer(optIn: true);
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $this->order($staff, '2026-03-01 10:00:00');
        $anon = $this->customer(optIn: true, attributes: ['email' => 'deleted_9@'.User::ANONYMIZED_EMAIL_DOMAIN]);
        $this->order($anon, '2026-03-01 10:00:00');

        $counts = (new SegmentsReport)->compute();

        $this->assertSame(['size' => 0, 'opt_in' => 0], $counts[SegmentsReport::ONCE_NEVER_BACK]);
        $this->assertSame([], (new SegmentsReport)->members(SegmentsReport::ONCE_NEVER_BACK));
    }

    public function test_an_unknown_segment_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SegmentsReport)->members('todos');
    }

    /** El widget de la pestaña «Clientes» pinta los cuatro segmentos con sus dos cifras. */
    public function test_the_widget_lists_the_four_segments_with_their_counts(): void
    {
        $in = $this->customer(optIn: true);
        $this->order($in, '2026-03-01 10:00:00');
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        Livewire::actingAs($admin)->test(SegmentsWidget::class)
            ->assertSee(__('admin.analytics.segments.heading'))
            ->assertSee(__('admin.analytics.segments.name.once_never_back'))
            ->assertSee(__('admin.analytics.segments.name.party_year_ago'))
            ->assertSee(__('admin.analytics.segments.name.guest_no_purchase'))
            ->assertSee(__('admin.analytics.segments.name.contact_no_order'));
    }
}
