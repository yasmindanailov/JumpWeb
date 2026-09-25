<?php

namespace Tests\Feature\Admin\Users;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Domain\Platform\Services\Money;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Support\CustomerInsights;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La 360 del cliente en su ficha** (`specs/analitica.md` §4.6, T4a): bajo `customers.insights` —el admin la ve,
 * el staff solo si el rol lo lleva—, lo del CONTRATO sale siempre de los pedidos cobrados (cuántos, lo vendido,
 * lo cobrado, lo devuelto, la primera y la última compra, la frecuencia, los productos) y lo de la NAVEGACIÓN
 * solo en el régimen identificado (la primera fuente, las visitas antes de comprar, los contactos); una cuenta
 * anonimizada no enseña nada. Los datos se afirman por `data-*`, no por su literal.
 */
class UserInsightsInfolistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-24 12:00:00', 'Europe/Madrid'));
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function sheet(User $viewer, User $customer): Testable
    {
        return Livewire::actingAs($viewer)->test(ViewUser::class, ['record' => $customer->id]);
    }

    /** Un pedido cobrado (o devuelto) con sus líneas y su cobro, en la fecha dada. */
    private function purchase(User $customer, string $paidAt, int $total, array $lines, string $status = Order::STATUS_PAID, int $refundedCents = 0): Order
    {
        $order = Order::create([
            'user_id' => $customer->id, 'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => $status,
            'total' => $total, 'paid_at' => Carbon::parse($paidAt), 'expires_at' => now()->addMinutes(30),
        ]);
        foreach ($lines as [$type, $slot, $qty]) {
            OrderItem::create(['order_id' => $order->id, 'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'quantity' => $qty, 'seats' => $qty, 'unit_price' => (int) ($total / max(1, $qty))]);
        }
        $payment = Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'redsys',
            'amount' => $total, 'currency' => 'EUR', 'status' => Payment::STATUS_PAID, 'gateway_order' => Str::random(10),
        ]);
        if ($refundedCents > 0) {
            $payment->refunds()->create(['amount_cents' => $refundedCents, 'currency' => 'EUR', 'status' => 'succeeded', 'mode' => 'manual', 'reason' => 'test', 'requested_by' => $customer->id, 'requested_at' => now()]);
        }

        return $order;
    }

    /** @return array{0: TicketType, 1: TicketType, 2: Slot} */
    private function catalog(): array
    {
        $zone = Zone::create(['slug' => 'z-'.Str::lower(Str::random(5)), 'name' => ['es' => 'Zona']]);
        $slot = Slot::create(['zone_id' => $zone->id, 'date' => now()->addDays(7)->toDateString(), 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 50, 'online_capacity' => 50]);
        $jump = TicketType::create(['name' => ['es' => 'Salto libre', 'en' => 'Free jump'], 'zone_id' => $zone->id, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]);
        $party = TicketType::create(['name' => ['es' => 'Cumpleaños'], 'zone_id' => $zone->id, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2]);

        return [$jump, $party, $slot];
    }

    private function attribute(string $html, string $attribute): ?string
    {
        return preg_match('/'.preg_quote($attribute, '/').'="([^"]*)"/', $html, $m) === 1 ? $m[1] : null;
    }

    public function test_only_who_holds_the_permission_sees_the_section(): void
    {
        $customer = $this->userWithRole('customer');

        $this->sheet($this->userWithRole('admin'), $customer)->assertSee(__('admin.users.section_insights'));

        // El staff no lleva `customers.insights` por defecto: la sección no se pinta.
        $staffRole = Role::where('name', 'staff')->firstOrFail();
        $staffRole->permissions()->attach(Permission::where('name', 'users.manage')->value('id'));
        $this->sheet($this->userWithRole('staff'), $customer)->assertDontSee(__('admin.users.section_insights'));

        $staffRole->permissions()->attach(Permission::where('name', 'customers.insights')->value('id'));
        $this->sheet($this->userWithRole('staff'), $customer)->assertSee(__('admin.users.section_insights'));
    }

    public function test_the_contract_block_comes_from_the_collected_orders(): void
    {
        [$jump, $party, $slot] = $this->catalog();
        $customer = $this->userWithRole('customer');
        $this->purchase($customer, '2025-09-24 10:00:00', 4000, [[$jump, $slot, 4]]);
        $this->purchase($customer, '2026-03-24 10:00:00', 12000, [[$party, $slot, 1], [$jump, $slot, 2]], Order::STATUS_REFUNDED, refundedCents: 2000);
        $this->purchase($customer, '2026-09-20 10:00:00', 3000, [[$jump, $slot, 3]]);
        // Un pedido PENDIENTE (no cobrado) no cuenta como compra.
        Order::create(['user_id' => $customer->id, 'code' => 'JJ-PEND', 'status' => Order::STATUS_PENDING, 'total' => 9900, 'expires_at' => now()->addMinutes(30)]);

        $i = CustomerInsights::forCustomer($customer);

        $this->assertSame(3, $i['orders']);
        $this->assertSame('190,00 €', $i['sold']);
        $this->assertSame('190,00 €', $i['collected']);
        $this->assertSame('20,00 €', $i['refunded']);
        $this->assertSame('24/09/2025', $i['first_purchase']);
        $this->assertSame('20/09/2026', $i['last_purchase']);
        $this->assertNotNull($i['frequency'], 'con tres compras en un año hay frecuencia');
        $this->assertSame([['name' => 'Salto libre', 'units' => 9], ['name' => 'Cumpleaños', 'units' => 1]], $i['products']);
        $this->assertFalse($i['identified']);

        $html = $this->sheet($this->userWithRole('admin'), $customer)->html();
        $this->assertSame('3', $this->attribute($html, 'data-insights-orders'));
        $this->assertSame('0', $this->attribute($html, 'data-insights-identified'));
        $this->assertStringContainsString('data-insights="not-identified"', $html, 'sin sesiones atadas, la navegación lo dice y no inventa');
        $this->assertStringContainsString('Salto libre', $html);
    }

    public function test_the_navigation_block_exists_only_in_the_identified_regime(): void
    {
        [$jump, , $slot] = $this->catalog();
        $customer = $this->userWithRole('customer');
        $customer->forceFill(['first_attribution' => ['source' => 'google', 'medium' => 'cpc', 'campaign' => 'verano']])->save();
        $this->purchase($customer, '2026-09-10 10:00:00', 3000, [[$jump, $slot, 3]]);

        $visitor = Visitor::mint();
        foreach (['2026-09-01 10:00:00', '2026-09-05 10:00:00', '2026-09-15 10:00:00'] as $at) {
            $session = AnalyticsSession::create(['visitor_id' => $visitor, 'user_id' => $customer->id, 'started_at' => Carbon::parse($at), 'last_seen_at' => Carbon::parse($at), 'is_bot' => false, 'is_internal' => false]);
            AnalyticsEvent::create(['event_id' => Visitor::mint(), 'session_id' => $session->id, 'visitor_id' => $visitor, 'user_id' => $customer->id, 'name' => 'page_viewed', 'occurred_at' => Carbon::parse($at), 'received_at' => Carbon::parse($at)]);
        }
        AnalyticsEvent::create(['event_id' => Visitor::mint(), 'session_id' => null, 'visitor_id' => $visitor, 'user_id' => $customer->id, 'name' => 'contact_received', 'occurred_at' => now(), 'received_at' => now()]);

        $i = CustomerInsights::forCustomer($customer);

        $this->assertTrue($i['identified']);
        $this->assertSame('google / cpc · verano', $i['first_source']);
        $this->assertSame(2, $i['visits_before'], 'dos visitas antes de la primera compra del 10-09');
        $this->assertSame(1, $i['contacts']);

        $html = $this->sheet($this->userWithRole('admin'), $customer)->html();
        $this->assertSame('2', $this->attribute($html, 'data-insights-visits-before'));
        $this->assertSame('1', $this->attribute($html, 'data-insights-contacts'));
        $this->assertStringContainsString('google / cpc · verano', $html);
    }

    /**
     * T3 de la fiesta (`specs/analitica-fiesta.md` §4.4): el bloque «Fiestas» sale de los pedidos del cliente (régimen
     * del contrato) —reservas de pack, formularios, invitaciones, «sí», firmas, extras de después de reservar— y dice
     * si vino invitado antes de comprar (su correo firmó un justificante de menor invitado antes de su primera compra).
     */
    public function test_the_parties_block_comes_from_the_customer_reservations_and_says_if_she_came_as_a_guest(): void
    {
        [$jump, , $slot] = $this->catalog();
        $pack = TicketType::create(['name' => ['es' => 'Cumpleaños Jump'], 'zone_id' => $slot->zone_id, 'type' => TicketType::TYPE_PACK, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 3]);
        $customer = $this->userWithRole('customer');
        $customer->forceFill(['email' => 'Ana@Example.test'])->save();

        // Vino invitada a OTRA fiesta el 1 de septiembre, y compró la suya el 10.
        $other = $this->purchase($this->userWithRole('customer'), '2026-08-20 10:00:00', 9000, [[$pack, $slot, 6]]);
        DB::table('guardian_authorizations')->insert([
            'order_item_id' => $other->items()->value('id'), 'minor_name' => 'Peque', 'minor_surname' => 'Invitado', 'minor_key' => 'peque-1', 'minor_born_on' => '2018-05-05',
            'guardian_name' => 'Ana', 'guardian_surname' => 'Gómez', 'guardian_relationship' => 'mother', 'guardian_email' => 'ana@example.test', 'guardian_phone' => null, 'created_at' => '2026-09-01 10:00:00',
        ]);
        $party = $this->purchase($customer, '2026-09-10 10:00:00', 12000, [[$pack, $slot, 8]]);
        $this->purchase($customer, '2026-09-12 10:00:00', 3000, [[$jump, $slot, 3]]);   // una entrada suelta: no es fiesta
        /** @var OrderItem $reservation */
        $reservation = $party->items()->firstOrFail();
        $reservation->forceFill(['guest_form_completed_at' => '2026-09-15 10:00:00'])->saveQuietly();
        $invitation = DB::table('party_invitations')->insertGetId(['order_item_id' => $reservation->id, 'token' => 'tok3nInvitac', 'theme' => 'jump', 'honoree_name' => 'Lucía', 'host_line' => 'Te invita Ana', 'show_host_phone' => false, 'reminded_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['Hugo', true], ['Vera', true], ['Noa', false]] as [$child, $attending]) {
            DB::table('invitation_replies')->insert(['party_invitation_id' => $invitation, 'order_item_id' => $reservation->id, 'attending' => $attending, 'child_name' => $child, 'child_key' => strtolower($child), 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('guardian_authorizations')->insert([
            'order_item_id' => $reservation->id, 'minor_name' => 'Hugo', 'minor_surname' => 'Ruiz', 'minor_key' => 'hugo-ruiz', 'minor_born_on' => '2018-05-05',
            'guardian_name' => 'Marta', 'guardian_surname' => 'Ruiz', 'guardian_relationship' => 'mother', 'guardian_email' => 'marta@example.test', 'guardian_phone' => null, 'created_at' => now(),
        ]);
        $cake = OrderItem::create(['order_id' => $party->id, 'ticket_type_id' => $jump->id, 'parent_item_id' => $reservation->id, 'slot_id' => $slot->id, 'quantity' => 2, 'seats' => 0, 'unit_price' => 1000]);
        OrderAdjustment::create(['order_id' => $party->id, 'order_item_id' => $cake->id, 'type' => OrderAdjustment::TYPE_EDIT, 'amount_cents' => 2000, 'currency' => 'EUR', 'reason' => 'postform_addon', 'applied_by' => $customer->id]);
        OrderAdjustment::create(['order_id' => $party->id, 'order_item_id' => $reservation->id, 'type' => OrderAdjustment::TYPE_EDIT, 'amount_cents' => -500, 'currency' => 'EUR', 'reason' => null, 'applied_by' => $customer->id]);

        $p = CustomerInsights::forCustomer($customer)['parties'];

        $this->assertSame(1, $p['count'], 'la entrada suelta no es una fiesta');
        $this->assertSame(1, $p['forms_completed']);
        $this->assertSame(1, $p['invitations']);
        $this->assertSame(2, $p['replies_yes']);
        $this->assertSame(1, $p['signatures']);
        $this->assertSame(Money::format(2000), $p['extras_after'], 'la edición del panel no es un extra');
        $this->assertSame('01/09/2026', $p['came_as_guest']);

        $html = $this->sheet($this->userWithRole('admin'), $customer)->html();
        $this->assertSame('1', $this->attribute($html, 'data-insights-parties'));
        $this->assertSame('1', $this->attribute($html, 'data-insights-signatures'));
        $this->assertSame('1', $this->attribute($html, 'data-insights-came-as-guest'));
        $this->assertStringContainsString(__('admin.users.insights.came_as_guest_yes', ['date' => '01/09/2026']), $html);

        // Quien nunca vino invitado ni tiene fiestas: ceros y «No».
        $plain = CustomerInsights::forCustomer($this->userWithRole('customer'))['parties'];
        $this->assertSame(0, $plain['count']);
        $this->assertNull($plain['came_as_guest']);
    }

    /** T4 de `specs/encuestas.md` §4.4: cuántas encuestas contestó y la ÚLTIMA, con su nota y su texto. */
    public function test_the_surveys_block_shows_how_many_she_answered_and_the_last_one(): void
    {
        $customer = $this->userWithRole('customer');
        $gate = Survey::create(['key' => 'visita', 'name' => ['es' => 'Tu visita'], 'kind' => Survey::KIND_INTERNAL, 'active' => true, 'questions' => [
            ['key' => 'ambiente', 'type' => 'scale', 'label' => ['es' => 'Ambiente']],
            ['key' => 'comentario', 'type' => 'text', 'label' => ['es' => 'Algo más']],
        ]]);
        $mail = Survey::create(['key' => 'que-tal', 'name' => ['es' => 'Qué tal ayer'], 'kind' => Survey::KIND_EXTERNAL, 'active' => true, 'questions' => [
            ['key' => 'nota', 'type' => 'scale', 'label' => ['es' => 'Nota']],
            ['key' => 'texto', 'type' => 'text', 'label' => ['es' => 'Cuéntanos']],
        ]]);
        SurveyResponse::create(['survey_id' => $gate->id, 'user_id' => $customer->id, 'channel' => 'internal', 'visited_on' => '2026-09-20', 'answered_at' => '2026-09-20 10:00:00', 'answers' => ['ambiente' => 2, 'comentario' => 'Mucha cola en la entrada'], 'locale' => 'es']);
        SurveyResponse::create(['survey_id' => $mail->id, 'user_id' => $customer->id, 'channel' => 'external', 'token' => Str::random(40), 'sent_at' => '2026-09-22 08:00:00', 'answered_at' => '2026-09-22 09:30:00', 'answers' => ['nota' => 4, 'texto' => 'Mejor que la otra vez'], 'locale' => 'es']);

        $s = CustomerInsights::forCustomer($customer)['surveys'];

        $this->assertSame(2, $s['answered']);
        $this->assertSame('22/09/2026', $s['last_on'], 'la última por fecha de respuesta');
        $this->assertSame('external', $s['last_channel']);
        $this->assertSame('Qué tal ayer', $s['last_survey']);
        $this->assertSame(4, $s['last_score']);
        $this->assertSame('Mejor que la otra vez', $s['last_text']);

        $html = $this->sheet($this->userWithRole('admin'), $customer)->html();
        $this->assertSame('2', $this->attribute($html, 'data-insights-surveys'));
        $this->assertSame('4', $this->attribute($html, 'data-insights-last-score'));
        $this->assertStringContainsString('Mejor que la otra vez', $html);
        $this->assertStringNotContainsString('Mucha cola en la entrada', $html, 'solo la última: el histórico entero no cabe en la ficha');

        $plain = CustomerInsights::forCustomer($this->userWithRole('customer'))['surveys'];
        $this->assertSame(0, $plain['answered']);
        $this->assertNull($plain['last_on']);
    }

    public function test_an_anonymized_account_shows_nothing(): void
    {
        [$jump, , $slot] = $this->catalog();
        $customer = $this->userWithRole('customer');
        $this->purchase($customer, '2026-09-10 10:00:00', 3000, [[$jump, $slot, 3]]);
        $customer->forceFill(['email' => 'deleted_'.$customer->id.'@'.User::ANONYMIZED_EMAIL_DOMAIN])->save();

        $this->assertTrue(CustomerInsights::forCustomer($customer->fresh())['anonymized']);
        $this->assertSame(0, CustomerInsights::forCustomer($customer->fresh())['orders'], 'una fila anónima no enseña su historia');

        $html = $this->sheet($this->userWithRole('admin'), $customer->fresh())->html();
        $this->assertStringContainsString('data-insights="anonymized"', $html);
        $this->assertStringNotContainsString('data-insights-orders', $html);
    }

    public function test_the_frequency_needs_two_purchases_and_speaks_in_purchases_per_year(): void
    {
        [$jump, , $slot] = $this->catalog();
        $one = $this->userWithRole('customer');
        $this->purchase($one, '2026-01-10 10:00:00', 3000, [[$jump, $slot, 3]]);
        $this->assertNull(CustomerInsights::forCustomer($one)['frequency'], 'con una compra no hay frecuencia');

        $two = $this->userWithRole('customer');
        $this->purchase($two, '2025-09-24 10:00:00', 3000, [[$jump, $slot, 3]]);
        $this->purchase($two, '2026-09-24 10:00:00', 3000, [[$jump, $slot, 3]]);
        $this->assertSame((string) __('admin.users.insights.frequency_per_year', ['n' => '2']), CustomerInsights::forCustomer($two)['frequency']);
    }
}
