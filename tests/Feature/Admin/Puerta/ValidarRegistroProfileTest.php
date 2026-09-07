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
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\PuertaSettings;
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
use Tests\Support\DeclaresDependents;
use Tests\TestCase;

/**
 * Fase 6 · subsistema A — la PANTALLA DE PUERTA con la FICHA (`docs/specs/identidad-qr-puerta.md` §4.5,
 * §4.6, §4.8, §8.3, §9.2 A·5/A·6/A·7): el carné por el mismo input, quién ve la ficha, los dos
 * limitadores, la caducidad EN EL SERVIDOR, la visita explícita e idempotente, y **nunca el nombre de
 * un menor**.
 */
class ValidarRegistroProfileTest extends TestCase
{
    use DeclaresDependents;
    use RefreshDatabase;

    private const TODAY = '2026-09-05';

    /**
     * Los APELLIDOS de un menor, que la pantalla no puede enseñar nunca (`#236`).
     *
     * ⚠️ Hasta `#236` esta constante era su NOMBRE y lo prohibido era el nombre. El owner revirtió
     * esa parte —con tres niños y una firma que falta, «7 años ✗» no dice a cuál—, así que el
     * nombre de pila ahora sí se pinta y la prohibición se mudó a los apellidos. Se elige una
     * cadena imposible de confundir para que `assertDontSee` no case por casualidad.
     */
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
        // `#236`: nombre de pila + apellidos. El nombre SE PINTA en la puerta; los apellidos NO.
        $lucas = $this->declareLegacyDependent($holder, 'Lucas', '2017-03-12', self::MINOR, 'mother');
        $this->declareLegacyDependent($holder, 'Vilma', '2019-11-02', 'Retamocho Secreto', 'father');
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
            // §9.7 C·5: los menores se aseveran por `data-*`, NO por la cadena compuesta
            // «9 años · exención ✓». Esa cadena la formaba la plantilla juntando dos rótulos con un
            // separador, así que el test caía al cambiar la puntuación y NO caía si el dato era otro.
            ->assertSee('data-gate-minor-name="Lucas" data-gate-minor-age="9" data-gate-minor-waiver="current"', false)
            ->assertSee('data-gate-minor-name="Vilma" data-gate-minor-age="6" data-gate-minor-waiver="missing"', false)
            // `#320`: en la PASTILLA solo entra la excepción. A Vilma le falta la firma y el operador
            // tiene que verlo; la de Lucas está vigente y no se anuncia. El dato sigue en el `data-`
            // de arriba, así que esto es presentación y no una pérdida de información.
            ->assertDontSee(__('admin.puerta.validar.profile.minor_waiver_current'))
            ->assertSee(__('admin.puerta.validar.profile.minor_waiver_missing'))
            // `#236`: el NOMBRE se ve —es lo que resuelve «¿a cuál le falta la firma?»— y los
            // APELLIDOS no llegan a la pantalla.
            ->assertSee('Lucas')
            ->assertDontSee(self::MINOR)
            ->assertDontSee('Retamocho Secreto')
            ->assertDontSee('ana@example.com')
            ->assertDontSee('600111222');

        $this->assertSame(1, count($page->get('profile.today_reservations')));
        $this->assertSame([['name' => 'Lucas', 'age' => 9, 'waiver' => 'current']], $page->get('profile.today_reservations')[0]['minors']);
        $state = json_encode($page->get('profile'), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Zorrocotroco', $state, 'el estado que viaja al navegador no lleva los APELLIDOS del menor (`#236`)');
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
            ->assertDontSee('data-gate-visit', false);

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
            ->assertSee('data-gate-card-revoked', false)
            ->assertDontSee('Ana Titular');

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', CardToken::generate())
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_CARD_UNKNOWN)
            ->assertSet('profile', null)
            ->assertSee('data-gate-card-unknown', false);

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

    /**
     * ⚠️⚠️ **EL NAVEGADOR NO DECIDE A QUIÉN SE LE ACREDITA LA VISITA NI CUÁNDO CADUCA LA FICHA**
     * (2026-08-28, revisión de `#217`).
     *
     * `$profile` es estado público de Livewire: viaja en el snapshot y **vuelve del cliente**. Mientras
     * `registerVisit()` leyó de ahí el `user_id`, un cliente manipulado podía acreditarle la visita a
     * OTRA persona —y de las visitas salen los JumpPoints (§8.3), o sea que es una moneda— y estirar
     * el `expires_at` para resucitar una ficha vencida, justo lo que `SEC-04` aplicado al tiempo
     * impide. Hoy las dos cosas viven en propiedades `#[Locked]` que solo escribe el servidor.
     *
     * Este caso conduce el ataque de las dos formas: cambiando la copia pública (que Livewire acepta,
     * porque es pública) y cambiando la bloqueada (que Livewire rechaza).
     */
    public function test_the_browser_cannot_choose_who_gets_the_visit_nor_extend_the_profile(): void
    {
        [$holder, $token] = $this->customer();
        $otro = User::factory()->create(['email' => 'otro@example.com']);
        $staff = $this->staff();

        $page = Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', $token)->call('search');

        // (1) La copia PÚBLICA se puede cambiar… y no decide nada.
        $page->set('profile.user_id', $otro->id)->call('registerVisit');

        $this->assertSame(1, CustomerVisit::where('user_id', $holder->id)->count(), 'la visita es del titular de la ficha');
        $this->assertSame(0, CustomerVisit::where('user_id', $otro->id)->count(), 'y NO de quien dijo el navegador');

        // (2) La propiedad BLOQUEADA no se puede cambiar: Livewire lo rechaza.
        try {
            $page->set('profileUserId', $otro->id);
            $this->fail('`profileUserId` tiene que estar bloqueada: el navegador no puede escribirla');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('profileUserId', $e->getMessage());
        }

        // (3) Y estirar el vencimiento por la copia pública tampoco resucita una ficha vencida.
        $page->set('profile.expires_at', now()->addHour()->timestamp);
        $this->travel(PuertaSettings::profileTtlMinutes() + 1)->minutes();
        $page->call('registerVisit')->assertSet('profile', null);

        $this->assertSame(1, CustomerVisit::count(), 'la ficha estaba vencida en el SERVIDOR: no hay segunda visita');
    }

    public function test_the_visit_is_registered_once_per_day_with_the_operator_and_needs_the_permission(): void
    {
        [$holder, $token] = $this->customer();
        $staff = $this->staff();

        $page = Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', $token)->call('search');
        $this->assertSame(0, CustomerVisit::count(), 'ABRIR la ficha no acredita nada');

        // #234: la TARJETA de visita se retiró de la pantalla hasta que exista JumpPoints, así que
        // ya no hay `data-gate-visit` que aseverar. La MAQUINARIA sigue entera y es lo que este caso
        // mide: el hecho es idempotente por día, lleva al operador y exige el permiso. Se comprueba
        // por el ESTADO del componente y por la fila en base, que es donde vive la verdad.
        // ⚠️ Se asevera el marcador COMPLETO, no el prefijo: `data-gate-visit-badge` —la píldora
        // «visita registrada hoy» de la cabecera, que SÍ sigue— contiene `data-gate-visit`, así que
        // un `assertDontSee` por subcadena falla aunque la tarjeta esté bien retirada. Cuarta vez
        // que la subcadena engaña en este repo.
        $page->call('registerVisit')
            ->assertSet('profile.visit_registered_today', true)
            ->assertDontSee('data-gate-visit="registered"', false)
            ->assertDontSee('data-gate-visit="register"', false);
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

    // ─── El rediseño (§9.7 C·5): un solo semáforo y NUNCA el nombre de un menor ──

    /**
     * ⚠️⚠️ **La guarda del invariante, y su mutación.**
     *
     * «De un menor, la puerta enseña EDAD y ESTADO DE LA EXENCIÓN, jamás el nombre» es estructural:
     * `GateProfileData` no tiene campo para el nombre. Pero lo estructural protege al DTO, no a la
     * PLANTILLA: el día que alguien añada un campo al DTO —o que llegue por otra vía— la vista lo
     * pintaría sin que nada fallara, porque el fixture normal no trae nombre que enseñar.
     *
     * Aquí se INYECTA el nombre en el estado de la ficha y se exige que la vista siga sin pintarlo.
     * Mutación comprobada: basta con añadir `{{ $m['name'] ?? '' }}` en `validar.blade.php` (o en
     * `partials/reservation.blade.php`) para que este test se ponga rojo; el test de arriba, que solo
     * mira el fixture sano, sigue verde.
     */
    /**
     * ⚠️⚠️ **Esta guarda cambió de regla en `#236` y la anterior queda escrita.**
     *
     * Aseveraba que la vista NO pintara el nombre de un menor aunque el estado lo trajera. El owner
     * revirtió esa decisión: con tres niños y una firma que falta, «7 años ✗» no dice a cuál. Ahora
     * el nombre SÍ se pinta.
     *
     * ▶ Lo que sigue vigilando es el recorte que no se movió: **apellidos y correo no llegan a la
     * pantalla ni metiéndolos a mano en el estado**. La plantilla imprime `name`, `age` y `waiver`,
     * y nada más — es el mismo mecanismo de antes, aplicado a lo que hoy sobra.
     */
    public function test_the_view_prints_the_first_name_but_never_the_surname_or_the_email(): void
    {
        [, $token] = $this->customer();

        $page = Livewire::actingAs($this->staff())->test(ValidarRegistro::class)->set('input', $token)->call('search');

        $page->set('profile.dependents', [
            ['age' => 9, 'waiver' => 'current', 'name' => 'Lucas', 'surname' => self::MINOR, 'email' => 'menor@example.com'],
        ])->set('profile.today_reservations.0.minors', [
            ['age' => 9, 'waiver' => 'current', 'name' => 'Lucas', 'surname' => self::MINOR],
        ]);

        $page->assertSee('data-gate-minor-name="Lucas" data-gate-minor-age="9" data-gate-minor-waiver="current"', false)
            ->assertSee('Lucas')
            ->assertDontSee(self::MINOR)
            ->assertDontSee('Zorrocotroco')
            ->assertDontSee('menor@example.com');
    }

    /**
     * El semáforo pasó de OCHO tarjetas duplicadas a UN callout (§9.7 C·5). Que sea uno no es estética:
     * dos semáforos a la vez son dos respuestas a la vez para el empleado.
     */
    public function test_the_semaphore_is_one_component_carrying_the_status(): void
    {
        [, $token] = $this->customer();

        $html = Livewire::actingAs($this->staff())->test(ValidarRegistro::class)->set('input', $token)->call('search')->html();

        $this->assertSame(1, substr_count($html, 'data-gate-status='), 'un solo semáforo por respuesta');
        $this->assertStringContainsString('data-gate-status="'.ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER.'"', $html);
    }

    /**
     * «Abierta por QR» vs «por búsqueda tecleada» no es decoración: el tecleo es el camino con
     * limitador propio y el que convierte la puerta en un oráculo (§4.6·1). El empleado tiene que
     * verlo, y el atributo es el que miran los guiones headless.
     */
    public function test_the_profile_says_out_loud_how_it_was_opened(): void
    {
        [, $token] = $this->customer();

        Livewire::actingAs($this->staff())->test(ValidarRegistro::class)->set('input', $token)->call('search')
            ->assertSee('data-gate-via="card"', false)
            ->assertSee('data-gate-via-badge="card"', false);

        Livewire::actingAs($this->staff())->test(ValidarRegistro::class)->set('input', 'ana@example.com')->call('search')
            ->assertSee('data-gate-via="lookup"', false)
            ->assertSee('data-gate-via-badge="lookup"', false);
    }

    /** Las seis tarjetas de la ficha (§9.7 C·5): si una desaparece, el empleado pierde un dato. */
    /**
     * Los bloques de la ficha, que desde `#234` son **CUATRO** y no seis: `[DECIDIDO owner]` fuera
     * el QR del cliente —muy pocos casos dan problema de carné y se comía una columna— y fuera la
     * VISITA hasta que exista JumpPoints, que es lo único que da sentido a acreditarla.
     *
     * Los dos retirados se aseveran AUSENTES a propósito: que no estén es la decisión, y si vuelven
     * tiene que ser mirando también el reparto de columnas del kiosco, que cuadra con cuatro.
     */
    public function test_the_profile_shows_the_four_blocks(): void
    {
        [, $token] = $this->customer();

        Livewire::actingAs($this->staff())->test(ValidarRegistro::class)->set('input', $token)->call('search')
            ->assertSee('data-gate-today', false)            // HOY
            ->assertSee('data-gate-waiver="current"', false) // EXENCIÓN
            ->assertSee('data-gate-minors', false)           // MENORES
            ->assertDontSee('data-gate-card', false)         // el QR, retirado
            // Marcador COMPLETO: `data-gate-visit-badge` (la píldora de la cabecera, que se queda)
            // contiene `data-gate-visit` y haría fallar un `assertDontSee` por prefijo.
            ->assertDontSee('data-gate-visit="register', false); // la visita, retirada
    }

    /**
     * Dos ramas que el fixture sano NO recorre y que son justo las que importan en el mostrador:
     *  - **la ventana ±N** («tiene reserva, pero otro día» ≠ «no tiene nada», §4.6 estado 2);
     *  - **el pendiente de cobrar en puerta**, que con sistema de señal es lo que hace que el negocio
     *    cobre o no cobre (§4.7). Sin este caso, la única línea de dinero con tratamiento de alerta
     *    de toda la pantalla no se pintaba en ningún test.
     */
    public function test_the_window_and_the_money_due_at_the_gate_are_rendered(): void
    {
        [, $token] = $this->customer();

        $page = Livewire::actingAs($this->staff())->test(ValidarRegistro::class)->set('input', $token)->call('search');

        $page->set('profile.window', [[
            'order_code' => 'R-MANANA1', 'order_item_id' => 99, 'date' => '2026-09-06', 'time_window' => '11:00–12:00',
            'product' => 'Entrada 1h', 'is_entry' => true, 'quantity' => 2, 'addons' => ['Calcetines'],
            'paid_cents' => 500, 'balance_kind' => 'pay_at_park', 'balance_cents' => 1500, 'charge_method' => 'redsys',
            'paid_at' => null, 'created_at' => '2026-09-01 09:00:00', 'minors' => [],
        ]]);

        $page->assertSee('data-gate-window', false)
            ->assertSee('data-gate-reservation="R-MANANA1"', false)
            ->assertSee('data-gate-pending', false)
            ->assertSee('15,00');
    }

    public function test_the_page_still_renders_in_zh_with_the_profile_strings(): void
    {
        [, $token] = $this->customer();
        $staff = $this->staff();
        $staff->forceFill(['panel_locale' => 'zh_CN'])->save();
        // `Livewire::test` no pasa por la ruta ni por `SetAdminLocale`: se fija el idioma como haría el middleware.
        app()->setLocale('zh_CN');

        Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', $token)->call('search')
            ->assertSee('入口档案');
    }
}
