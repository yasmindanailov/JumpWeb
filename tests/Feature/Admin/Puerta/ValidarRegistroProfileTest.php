<?php

namespace Tests\Feature\Admin\Puerta;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\CustomerVisit;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\CardToken;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 6 · subsistema A — la PANTALLA DE PUERTA con la FICHA (`docs/specs/identidad-qr-puerta.md` §4.5,
 * §4.6, §4.8, §8.3, §9.2 A·5/A·6/A·7): el carné por el mismo input, quién ve la ficha, los dos
 * limitadores, la caducidad EN EL SERVIDOR, la visita explícita e idempotente, y **nunca el nombre de
 * un menor**.
 */
class ValidarRegistroProfileTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-09-05';

    private const MINOR = 'Zorrocotroco Único';

    private Zone $zone;

    private TicketType $entry;

    private ?LegalDocumentVersion $version = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->travelTo(Carbon::parse(self::TODAY.' 10:00:00', 'Europe/Madrid'));

        $rateId = (int) RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0])->id;
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1000]);
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
        Setting::flushMemo();
    }

    // ─── Fixture ──────────────────────────────────────────────────────────────

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        RateLimiter::clear("puerta:validate:user:{$u->id}");
        RateLimiter::clear("puerta:lookup:user:{$u->id}");

        return $u;
    }

    private function staffWithoutProfile(): User
    {
        $u = $this->staff();
        $u->roles->first()->permissions()->detach(Permission::where('name', 'puerta.profile')->value('id'));

        return $u;
    }

    private function publish(): LegalDocumentVersion
    {
        return $this->version = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function signFor(User $holder, ?Dependent $dependent = null): void
    {
        app(WaiverSigner::class)->sign($holder, $this->version ?? $this->publish(), new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB, ip: '10.0.0.7', userAgent: 'test',
            subjectType: $dependent === null ? WaiverSignature::SUBJECT_HOLDER : WaiverSignature::SUBJECT_DEPENDENT,
            subjectId: $dependent?->getKey(),
        ));
    }

    /**
     * Un cliente con exención firmada, carné, un menor firmado y otro sin firma, y una entrada HOY con el
     * primero asignado. @return array{0: User, 1: string}  el titular y su token en claro
     */
    private function customer(): array
    {
        $holder = User::factory()->create(['name' => 'Ana Titular', 'email' => 'ana@example.com', 'phone' => '+34600111222', 'email_verified_at' => now()]);
        $holder->roles()->sync([Role::where('name', 'customer')->value('id')]);
        $this->signFor($holder);
        $lucas = app(DependentRegistry::class)->add($holder, self::MINOR, '2017-03-12');
        app(DependentRegistry::class)->add($holder, 'Vera Secreta', '2019-11-02');
        $this->signFor($holder, $lucas);

        $order = Order::create([
            'user_id' => $holder->id, 'code' => 'R-PUERTA1', 'status' => Order::STATUS_PAID,
            'subtotal' => 2000, 'tax' => 0, 'total' => 2000, 'currency' => 'EUR', 'paid_at' => now()->subDay(),
        ]);
        Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'cash',
            'amount' => 2000, 'currency' => 'EUR', 'status' => Payment::STATUS_PAID, 'paid_at' => now()->subDay(),
        ]);
        $slot = Slot::create(['zone_id' => $this->zone->id, 'date' => self::TODAY, 'start_time' => '11:00:00', 'end_time' => '12:00:00', 'capacity' => 20, 'online_capacity' => 20]);
        $order->items()->create(['ticket_type_id' => $this->entry->id, 'slot_id' => $slot->id, 'quantity' => 2, 'unit_price' => 1000, 'seats' => 2]);
        app(DependentAssigner::class)->assign($holder, $order->id, [
            ['index' => 0, 'product_id' => $this->entry->id, 'date' => self::TODAY, 'quantity' => 2, 'dependent_ids' => [$lucas->id]],
        ]);

        $token = (string) app(CustomerCards::class)->ensureFor($holder)->plainToken();

        return [$holder, $token];
    }

    // ─── El carné por el MISMO input (A·5) ────────────────────────────────────

    public function test_scanning_a_card_opens_the_profile_and_audits_the_scan_and_the_disclosure(): void
    {
        [$holder, $token] = $this->customer();
        $staff = $this->staff();

        $page = Livewire::actingAs($staff)
            ->test(ValidarRegistro::class)
            ->set('input', strtolower(substr($token, 0, 6)).' '.substr($token, 6)) // como lo teclearía un lector, o una persona
            ->call('search');

        $page->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
            ->assertSet('profile.holder_name', 'Ana Titular')
            ->assertSet('profile.via', 'card')
            ->assertSet('profile.card', 'active')
            ->assertSet('profile.visit_registered_today', false)
            ->assertSee('Ana Titular')
            ->assertSee('Entrada 1h')
            ->assertSee('9 años · exención ✓')
            ->assertSee('6 años · sin exención')
            ->assertSee('Registrar visita')
            ->assertDontSee(self::MINOR)
            ->assertDontSee('Vera Secreta')
            ->assertDontSee('ana@example.com')
            ->assertDontSee('600111222');

        $this->assertSame(1, count($page->get('profile.today_reservations')));
        $this->assertSame([['age' => 9, 'waiver' => 'current']], $page->get('profile.today_reservations')[0]['minors']);
        $state = json_encode($page->get('profile'), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Zorrocotroco', $state, 'el estado que viaja al navegador tampoco lleva el nombre del menor');
        $this->assertStringNotContainsString('ana@example.com', $state);

        $scan = AuditLog::where('action', 'puerta.card_scanned')->sole();
        $this->assertSame(CardToken::hash($token), $scan->payload_hash, 'el token se audita como dato sensible: su hash, nunca en claro');
        $this->assertSame($holder->id, (int) $scan->target_id);
        $this->assertSame($staff->id, (int) $scan->user_id);
        $disclosure = AuditLog::where('action', 'puerta.profile_viewed')->sole();
        $this->assertSame($holder->id, (int) $disclosure->target_id);
        $this->assertSame(['via' => 'card'], $disclosure->payload);
        $this->assertSame(0, AuditLog::where('action', 'registrations.validated')->count(), 'un escaneo no es una búsqueda tecleada');
    }

    public function test_without_the_permission_a_scan_gives_only_the_state(): void
    {
        [, $token] = $this->customer();

        Livewire::actingAs($this->staffWithoutProfile())
            ->test(ValidarRegistro::class)
            ->set('input', $token)
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
            ->assertSet('profile', null)
            ->assertDontSee('Ana Titular')
            ->assertDontSee('Registrar visita');

        $this->assertSame(0, AuditLog::where('action', 'puerta.profile_viewed')->count(), 'sin ficha no hay divulgación que auditar');
    }

    public function test_a_revoked_card_and_an_unknown_card_open_nothing(): void
    {
        [$holder, $old] = $this->customer();
        app(CustomerCards::class)->rotate($holder);

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', $old)
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_CARD_REVOKED)
            ->assertSet('profile', null)
            ->assertSee('Carné caducado')
            ->assertDontSee('Ana Titular');

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', CardToken::generate())
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_CARD_UNKNOWN)
            ->assertSet('profile', null)
            ->assertSee('Carné no reconocido');

        $this->assertSame(2, AuditLog::where('action', 'puerta.card_scanned')->count(), 'los dos escaneos quedan auditados');
    }

    public function test_input_detection_puts_the_card_before_the_phone(): void
    {
        $this->assertSame('card', ValidarRegistro::detectInputType('jw7k3m9p2xa4zq0ht5g'.'A'));
        $this->assertSame('card', ValidarRegistro::detectInputType(' JW7K-3M9P 2XA4 ZQ0H T5GA '));
        $this->assertSame('phone', ValidarRegistro::detectInputType('+34 600 111 222'));
        $this->assertSame('phone', ValidarRegistro::detectInputType('12345678901234567890'), 'veinte dígitos sin el prefijo del carné siguen siendo un teléfono');
        $this->assertSame('email', ValidarRegistro::detectInputType('ana@example.com'));
        $this->assertNull(ValidarRegistro::detectInputType('JW7K3M9P'));
    }

    // ─── La búsqueda TECLEADA abre la ficha, con su propio limitador (A·5, §4.6·1/·3/·4) ──

    public function test_a_typed_lookup_opens_the_profile_under_its_own_hourly_limiter_and_the_scan_keeps_working(): void
    {
        [, $token] = $this->customer();
        Setting::updateOrCreate(['key' => 'puerta.lookup_rate_limit_per_hour'], ['value' => '2', 'group' => 'puerta']);
        Setting::flushMemo();
        $staff = $this->staff();

        for ($i = 0; $i < 2; $i++) {
            Livewire::actingAs($staff)
                ->test(ValidarRegistro::class)
                ->set('input', 'ana@example.com')
                ->call('search')
                ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
                ->assertSet('profile.via', 'lookup')
                ->assertSee('Ana Titular');
        }
        $this->assertSame(2, AuditLog::where('action', 'registrations.validated')->count(), 'la búsqueda tecleada se sigue auditando con hash');
        $this->assertSame(2, AuditLog::where('action', 'puerta.profile_viewed')->where('payload->via', 'lookup')->count());

        // La tercera en la misma hora: rechazada, auditada como CRÍTICA una sola vez, sin la ficha.
        for ($i = 0; $i < 2; $i++) {
            Livewire::actingAs($staff)
                ->test(ValidarRegistro::class)
                ->set('input', '+34 600 111 222')
                ->call('search')
                ->assertSet('result.status', ValidarRegistro::STATUS_LOOKUP_LIMITED)
                ->assertSet('profile', null)
                ->assertDontSee('Ana Titular');
        }
        $limited = AuditLog::where('action', 'puerta.lookup_rate_limited')->get();
        $this->assertCount(1, $limited, 'una vez por ventana (SEC-05)');
        $this->assertSame(['limit' => 2], $limited->first()->payload);
        $this->assertContains('puerta.lookup_rate_limited', AuditLog::CRITICAL_ACTIONS, 'hereda el aviso al operador (§4.6·4)');

        // El ESCANEO no cuenta en ese limitador: la cola sigue.
        Livewire::actingAs($staff)
            ->test(ValidarRegistro::class)
            ->set('input', $token)
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
            ->assertSet('profile.via', 'card');
    }

    // ─── La ficha caduca en el SERVIDOR (A·6, §4.8) ───────────────────────────

    public function test_the_profile_expires_on_the_server_and_a_later_request_does_not_revive_it(): void
    {
        [, $token] = $this->customer();
        Setting::updateOrCreate(['key' => 'puerta.profile_ttl_minutes'], ['value' => '2', 'group' => 'puerta']);
        Setting::flushMemo();

        $page = Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', $token)
            ->call('search')
            ->assertSet('profile.ttl_minutes', 2)
            ->assertSee('Ana Titular');
        $this->assertSame(now()->addMinutes(2)->timestamp, $page->get('profile.expires_at'));

        // Con la pestaña «dormida» (ningún temporizador disparó), tres minutos después llega cualquier
        // petición con el estado antiguo: el servidor la descarta antes de hacer nada.
        $this->travel(3)->minutes();
        $page->call('registerVisit')
            ->assertSet('profile', null)
            ->assertSet('result', null)
            ->assertDontSee('Ana Titular');
        $this->assertSame(0, CustomerVisit::count(), 'sobre una ficha caducada no se registra ninguna visita');
    }

    public function test_an_interaction_renews_the_server_clock(): void
    {
        [, $token] = $this->customer();
        Setting::updateOrCreate(['key' => 'puerta.profile_ttl_minutes'], ['value' => '2', 'group' => 'puerta']);
        Setting::flushMemo();

        $page = Livewire::actingAs($this->staff())->test(ValidarRegistro::class)->set('input', $token)->call('search');
        $this->travel(1)->minutes();
        $page->call('registerVisit')->assertSet('profile.visit_registered_today', true);
        $this->assertSame(now()->addMinutes(2)->timestamp, $page->get('profile.expires_at'), 'registrar la visita reinicia el reloj');

        $this->travel(90)->seconds();
        $page->call('registerVisit')->assertSet('profile.holder_name', 'Ana Titular', 'a los 2:30 desde la apertura sigue viva porque se tocó al minuto');
    }

    // ─── La visita: explícita, idempotente, con permiso (§8.3) ────────────────

    public function test_the_visit_is_registered_once_per_day_with_the_operator_and_needs_the_permission(): void
    {
        [$holder, $token] = $this->customer();
        $staff = $this->staff();

        $page = Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', $token)->call('search');
        $this->assertSame(0, CustomerVisit::count(), 'ABRIR la ficha no acredita nada');

        $page->call('registerVisit')
            ->assertSet('profile.visit_registered_today', true)
            ->assertSee('Visita registrada hoy')
            ->assertDontSee('Registrar visita');
        $page->call('registerVisit');

        $this->assertSame(1, CustomerVisit::where('user_id', $holder->id)->count(), 'una por día: volver a pulsar no suma');
        $visit = CustomerVisit::sole();
        $this->assertSame(self::TODAY, $visit->visited_on->toDateString());
        $this->assertSame($staff->id, (int) $visit->registered_by);
        $this->assertSame(1, AuditLog::where('action', 'puerta.visit_registered')->count());

        // Y al volver a escanear, la ficha ya lo dice.
        Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', $token)->call('search')
            ->assertSet('profile.visit_registered_today', true);

        // Sin el permiso de la ficha no hay visita que registrar.
        Livewire::actingAs($this->staffWithoutProfile())->test(ValidarRegistro::class)->call('registerVisit')->assertForbidden();
    }

    public function test_the_page_still_renders_in_zh_with_the_profile_strings(): void
    {
        [, $token] = $this->customer();
        $staff = $this->staff();
        $staff->forceFill(['panel_locale' => 'zh_CN'])->save();
        // `Livewire::test` no pasa por la ruta ni por `SetAdminLocale`: se fija el idioma como haría el middleware.
        app()->setLocale('zh_CN');

        Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', $token)->call('search')
            ->assertSee('入口档案')
            ->assertSee('登记到访');
    }
}
