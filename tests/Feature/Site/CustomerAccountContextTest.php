<?php

namespace Tests\Feature\Site;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerAccountContext;
use App\Domain\Platform\Services\DisplayTime;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cuenta migrada al sidebar (#221): el servicio `CustomerAccountContext` (saludo + próxima
 * reserva + formularios #217 pendientes) y el render del bloque del sidecart, el icono del nav
 * y el logout en /mi-cuenta. El grueso es sobre el servicio (locale-independiente); unos pocos
 * comprueban el render real.
 */
class CustomerAccountContextTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    private function pack(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
            ],
        ]);
    }

    private function entry(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
    }

    /**
     * Crea una reserva pagada (pedido + item principal + franja). Devuelve el item.
     *
     * @param  array<int, array<string, mixed>>  $guestData
     */
    private function reservation(
        User $user,
        TicketType $type,
        string $date,
        int $qty = 2,
        array $guestData = [],
        bool $cancelled = false,
        string $status = Order::STATUS_PAID,
    ): OrderItem {
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => $status, 'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);

        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => '10:00:00', 'end_time' => '20:00:00',
            'capacity' => 50, 'online_capacity' => 50,
        ]);

        return $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'parent_item_id' => null,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => 1000,
            'guest_data' => $guestData === [] ? null : $guestData,
            'cancelled_at' => $cancelled ? now() : null,
        ]);
    }

    private function ctx(User $user): array
    {
        return app(CustomerAccountContext::class)->for($user);
    }

    // ─── Servicio: saludo y vacío seguro ─────────────────────────────────────

    public function test_first_name_is_the_first_token_of_the_name(): void
    {
        $user = User::factory()->create(['name' => 'Mara López']);

        $this->assertSame('Mara', $user->firstName());
        $this->assertSame('Mara', $this->ctx($user)['firstName']);
    }

    public function test_user_without_orders_gets_a_safe_empty_context(): void
    {
        $user = User::factory()->create(['name' => 'Mara']);

        $ctx = $this->ctx($user);

        $this->assertSame('Mara', $ctx['firstName']);
        $this->assertNull($ctx['nextReservation']);
        $this->assertSame([], $ctx['pendingForms']);
        $this->assertSame(0, $ctx['pendingFormsCount']);
        $this->assertFalse($ctx['hasPendingForm']);
    }

    // ─── Servicio: próxima reserva ───────────────────────────────────────────

    public function test_next_reservation_returns_future_paid_principal_with_product_and_window(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'));

        $next = $this->ctx($user)['nextReservation'];

        $this->assertNotNull($next);
        $this->assertSame('Cumpleaños Jump', $next->productName);
        // ⚠️ **`date` viaja en `Y-m-d`, SIN formatear** (2026-08-23): la etiqueta la compone quien la
        // pinta, con `DisplayTime::dayLabel`. Antes aquí se aseveraba `dateLabel !== ''`, que pasaba
        // con cualquier cadena; esto fija el DATO.
        $this->assertSame(now()->addDays(3)->format('Y-m-d'), $next->date);
        $this->assertStringContainsString('10:00', (string) $next->timeWindow);
        $this->assertStringContainsString('12:00', (string) $next->timeWindow); // 10:00 + 120 min
    }

    public function test_finished_reservation_is_excluded(): void
    {
        $user = User::factory()->create();
        // Franja de ayer: end_time ya pasó → isFinishedInPractice().
        $item = $this->reservation($user, $this->pack(), now()->subDays(2)->format('Y-m-d'));
        $item->slot->update(['end_time' => '11:00:00']);

        $this->assertNull($this->ctx($user)['nextReservation']);
    }

    public function test_cancelled_item_is_excluded(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'), cancelled: true);

        $this->assertNull($this->ctx($user)['nextReservation']);
    }

    public function test_addon_child_is_not_treated_as_a_reservation(): void
    {
        $user = User::factory()->create();
        $principal = $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'));
        // Un addon (parent_item_id no nulo) nunca es "la próxima reserva".
        $principal->order->items()->create([
            'ticket_type_id' => $this->entry()->id, 'slot_id' => $principal->slot_id,
            'parent_item_id' => $principal->id, 'quantity' => 1, 'seats' => 0, 'unit_price' => 500,
        ]);

        $next = $this->ctx($user)['nextReservation'];
        $this->assertSame('Cumpleaños Jump', $next->productName); // el principal, no el addon
    }

    public function test_earliest_future_reservation_is_chosen(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(10)->format('Y-m-d'));
        $this->reservation($user, $this->entry(), now()->addDays(2)->format('Y-m-d'));

        $this->assertSame('Entrada 1h', $this->ctx($user)['nextReservation']->productName);
    }

    public function test_only_paid_orders_are_considered(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'), status: Order::STATUS_PENDING);

        $this->assertNull($this->ctx($user)['nextReservation']);
    }

    public function test_upcoming_count_counts_only_future_principal_reservations(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'));
        $this->reservation($user, $this->entry(), now()->addDays(5)->format('Y-m-d'));
        $past = $this->reservation($user, $this->entry(), now()->subDays(2)->format('Y-m-d'));
        $past->slot->update(['end_time' => '11:00:00']);   // finalizada → no cuenta

        $this->assertSame(2, $this->ctx($user)['upcomingCount']);
    }

    // ─── Servicio: formularios pendientes (#217) ─────────────────────────────

    public function test_pending_guest_form_is_flagged_with_product_and_url(): void
    {
        $user = User::factory()->create();
        // Pack con guest_fields y SIN datos → formulario pendiente.
        $item = $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'), qty: 2);

        $ctx = $this->ctx($user);

        $this->assertTrue($ctx['hasPendingForm']);
        $this->assertSame(1, $ctx['pendingFormsCount']);
        $this->assertSame('Cumpleaños Jump', $ctx['pendingForms'][0]['productName']);
        // Individualizado POR RESERVA (#217): la url apunta al post-form de ESA reserva (el OrderItem),
        // no al pedido.
        $this->assertSame(route('reservation.guests', $item), $ctx['pendingForms'][0]['url']);
    }

    public function test_two_packs_in_one_order_yield_two_pending_forms(): void
    {
        // Individualizado POR RESERVA (#217): un pedido con DOS cumpleaños pendientes produce DOS
        // avisos (uno por reserva), cada uno con su url. (Antes el servicio emitía solo uno por pedido.)
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-MULTI', 'status' => Order::STATUS_PAID, 'paid_at' => now(),
        ]);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '10:00:00', 'end_time' => '20:00:00', 'capacity' => 50, 'online_capacity' => 50,
        ]);
        $packB = TicketType::create([
            'name' => ['es' => 'Cumpleaños Kids'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 90, 'min_qty' => 2, 'max_qty' => 15, 'seats_per_unit' => 1, 'is_sellable' => true,
            'is_active' => true, 'position' => 10,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
        $a = $order->items()->create(['ticket_type_id' => $this->pack()->id, 'slot_id' => $slot->id, 'quantity' => 2, 'seats' => 2, 'unit_price' => 1000]);
        $b = $order->items()->create(['ticket_type_id' => $packB->id, 'slot_id' => $slot->id, 'quantity' => 3, 'seats' => 3, 'unit_price' => 800]);

        $ctx = $this->ctx($user);

        $this->assertSame(2, $ctx['pendingFormsCount']);
        $urls = array_column($ctx['pendingForms'], 'url');
        $this->assertContains(route('reservation.guests', $a), $urls);
        $this->assertContains(route('reservation.guests', $b), $urls);
    }

    public function test_completed_guest_form_is_not_pending(): void
    {
        $user = User::factory()->create();
        $this->reservation(
            $user, $this->pack(), now()->addDays(3)->format('Y-m-d'),
            qty: 2, guestData: [['name' => 'Ana'], ['name' => 'Leo']],
        );

        $ctx = $this->ctx($user);
        $this->assertFalse($ctx['hasPendingForm']);
        $this->assertSame(0, $ctx['pendingFormsCount']);
    }

    public function test_entry_without_guest_fields_never_pends_a_form(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->entry(), now()->addDays(3)->format('Y-m-d'));

        $this->assertFalse($this->ctx($user)['hasPendingForm']);
    }

    public function test_finished_pack_does_not_flag_a_pending_form(): void
    {
        // Simétrico a test_finished_reservation_is_excluded pero en el eje de formularios: un pack
        // pagado con el formulario sin rellenar y la franja YA pasada (cumpleaños celebrado) NO
        // debe dejar el puntito/aviso encendidos para siempre (#221, hallazgo de la revisión).
        $user = User::factory()->create();
        $item = $this->reservation($user, $this->pack(), now()->subDays(2)->format('Y-m-d'), qty: 2);
        $item->slot->update(['end_time' => '11:00:00']);

        $ctx = $this->ctx($user);
        $this->assertFalse($ctx['hasPendingForm']);
        $this->assertSame(0, $ctx['pendingFormsCount']);
    }

    public function test_cancelled_pack_does_not_flag_a_pending_form(): void
    {
        // Bug clienta (2026-06-15): una reserva CANCELADA debe dejar de avisar de su post-form en el
        // sidebar/nav. Es el eje de formularios, simétrico a `test_cancelled_item_is_excluded` (que solo
        // cubría `nextReservation`). El item cancelado lo excluye `Order::guestFormItems()`.
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'), qty: 2, cancelled: true);

        $ctx = $this->ctx($user);
        $this->assertFalse($ctx['hasPendingForm']);
        $this->assertSame(0, $ctx['pendingFormsCount']);
        $this->assertSame([], $ctx['pendingForms']);
    }

    /**
     * ⚠️⚠️ **UN PACK SIN FRANJA SIGUE AVISANDO, y hasta hoy no lo aseveraba nadie.**
     *
     * La conducta estaba escrita en el docblock de `CustomerReservationsReader::pendingGuestFormsFor()`
     * —«un pack sin franja (`isFinishedInPractice` → false) sigue avisando»— y comprobada en el
     * dominio (`OrderItem::isFinishedInPractice()` sale por `false` en cuanto no hay `slot`), pero
     * **ninguna prueba la fijaba**.
     *
     * ▶ Se escribe al añadir el SUELO TEMPORAL a esa consulta (2026-08-23), porque es exactamente lo
     * que un filtro por fecha rompe sin hacer ruido: `fecha >= suelo` deja fuera una fila cuya fecha
     * es `NULL`. Es una conducta documentada que solo la sostenía su comentario.
     */
    public function test_a_pack_without_a_slot_still_pends_its_form(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack()->id, 'slot_id' => null, 'parent_item_id' => null,
            'quantity' => 2, 'seats' => 2, 'unit_price' => 1000, 'guest_data' => null,
        ]);

        $ctx = $this->ctx($user);

        $this->assertTrue(
            $ctx['hasPendingForm'],
            'Un pack sin franja ha dejado de avisar de su formulario. Lo más probable es que el '.
            'suelo temporal de la consulta se haya escrito sin el `OR slot_id IS NULL`: una fila '.
            'con fecha NULA no satisface `fecha >= suelo` y desaparece en silencio.'
        );
        $this->assertSame(1, $ctx['pendingFormsCount']);
    }

    /**
     * ⚠️⚠️ **La consulta de formularios pendientes NO materializa el histórico**, y esto se mide en
     * FILAS y no en consultas: el suelo temporal no cambia cuántas consultas se hacen —siguen siendo
     * las mismas, con su eager loading— sino **cuántos pedidos se hidratan**. Un contador de consultas
     * daría verde con y sin suelo.
     *
     * Antes del 2026-08-23 esta consulta traía **todos** los pedidos con pack del cliente y hacía el
     * corte de «ya celebrado» entero en PHP. Y lo paga cada página pública, porque el nav pide este
     * contexto siempre que hay sesión.
     */
    public function test_the_pending_forms_query_does_not_materialise_the_whole_history(): void
    {
        $user = User::factory()->create();

        // Cinco cumpleaños ya celebrados (franja de hace meses, con su `end_time` pasado) …
        foreach (range(1, 5) as $i) {
            $item = $this->reservation($user, $this->pack(), now()->subMonths($i)->format('Y-m-d'));
            $item->slot->update(['end_time' => '11:00:00']);
        }

        // … y uno futuro, que es el único que debe avisar.
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'));

        $hidratados = 0;
        Order::retrieved(function () use (&$hidratados): void {
            $hidratados++;
        });

        $ctx = $this->ctx($user);

        $this->assertSame(1, $ctx['pendingFormsCount'], 'la conducta tiene que ser la de siempre');
        $this->assertSame(
            1, $hidratados,
            "Se han hidratado {$hidratados} pedidos para responder por UNO pendiente: la consulta ha ".
            'vuelto a traer el histórico y a filtrarlo en PHP. El suelo por fecha existe para que el '.
            'coste no crezca con los años de cliente — y lo paga cada página pública con sesión.'
        );
    }

    public function test_cancelled_order_does_not_flag_a_pending_form(): void
    {
        // Cancelar el PEDIDO entero (status=CANCELLED; sin marcar los items uno a uno) también retira
        // el aviso: la consulta de formularios pendientes solo considera pedidos PAGADOS.
        $user = User::factory()->create();
        $item = $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'), qty: 2);
        $item->order->update(['status' => Order::STATUS_CANCELLED]);

        $this->assertFalse($this->ctx($user)['hasPendingForm']);
    }

    public function test_first_name_trims_surrounding_whitespace(): void
    {
        $this->assertSame('Mara', User::factory()->create(['name' => '  Mara López '])->firstName());
        // Nombre solo-espacios → cadena vacía sin espacios colgando (no «Hola,    »).
        $this->assertSame('', User::factory()->create(['name' => '   '])->firstName());
    }

    // ─── Render: bloque del sidecart, icono del nav, logout ──────────────────

    /**
     * ⚠️⚠️ **RE-APUNTADO el 2026-08-23, y era el GUARDIÁN ÚNICO de `identifying` en toda la suite**
     * (`specs/account-context-vue.md` §4.11).
     *
     * Aseveraba sobre el HTML servido que los dos botones de la cara de invitado llevaban el
     * `disabled` atado a `$store.purchase.identifying`: el paso 5 del embudo ya está pidiendo
     * identificarse abajo, y ofrecer lo mismo arriba es ruido con botones muertos. El bloque lo pinta
     * ahora Vue, así que ese marcado no viaja en la página — **pero el sujeto sobrevive entero**, y
     * dejarlo morir habría retirado la única guarda de una señal que ya llegó MUERTA una vez
     * (`DECISIONES #118`: panel congelado en `is-catalog` y botones de invitado activos durante la
     * identificación).
     *
     * ⚠️ **Se cuentan los dos**, como antes: con `assertStringContains` bastaría uno, y quitarle el
     * `disabled` al segundo pasaría en verde.
     *
     * ⚠️ Y se comprueba que la señal se lee del **store de Pinia**, no de Alpine: el bloque está
     * ahora DENTRO del motor, así que dar el viaje Vue→Alpine→DOM→Vue sería reintroducir a mano la
     * frontera que este trabajo retira.
     */
    public function test_the_guest_face_blocks_its_buttons_while_the_funnel_asks_for_identity(): void
    {
        $panel = (string) file_get_contents(resource_path('js/sidebar/account/AccountPanel.vue'));

        $this->assertSame(
            2, substr_count($panel, ':disabled="identifying"'),
            'La cara de invitado tiene DOS botones y los dos se bloquean durante el paso 5. Si solo '.
            'uno lo hace, el otro ofrece entrar a quien el flujo ya está haciendo entrar.'
        );

        $this->assertStringContainsString(
            'publishedIdentifying(section.active, purchase.identifying)', $panel,
            'La señal ha dejado de leerse del store. Si vuelve a leerse de Alpine, el bloque depende '.
            'de un viaje Vue→Pinia→Alpine→DOM→Vue que este trabajo existe para retirar — y es donde '.
            'esa señal ya llegó muerta una vez (`DECISIONES #118`).'
        );
    }

    /**
     * Y los rótulos de la cara de invitado llegan en el montaje, **no vacíos**.
     *
     * ⚠️ Es la otra mitad del caso anterior: antes se aseveraban contra el HTML porque el servidor los
     * pintaba. Ahora los pinta Vue leyéndolos del `data-boot`, y un rótulo que faltara se pintaría
     * **vacío** — `i18n.js` devuelve cadena vacía cuando falta una clave, y en producción un texto
     * ausente no puede tumbar el cajón. Nada avisaría.
     */
    public function test_the_guest_labels_travel_in_the_mount_payload(): void
    {
        $this->seed(LandingContentSeeder::class);

        $html = (string) $this->withSession(['locale' => 'es'])->get('/')->assertOk()->getContent();

        $this->assertSame(1, preg_match('/id="sidecart-spa" data-boot="([^"]*)"/', $html, $m), 'no hay montaje');

        $boot = json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);

        foreach (['guest_hello' => 'sidecart', 'guest_sub' => 'sidecart'] as $key => $group) {
            $this->assertNotSame('', (string) ($boot['account'][$group][$key] ?? ''), "falta `account.{$group}.{$key}`");
        }

        $this->assertNotSame('', (string) ($boot['account']['nav']['login'] ?? ''), 'falta `account.nav.login`');
        $this->assertNotSame('', (string) ($boot['messages']['my_reservations'] ?? ''), 'falta `tickets.my_reservations`');
    }

    public function test_authenticated_account_page_shows_block_icon_and_logout(): void
    {
        $user = User::factory()->create(['name' => 'Mara', 'locale' => 'es']);
        // ⚠️ **UNA sola lectura del reloj**, reutilizada para el fixture y para lo esperado. Con dos
        // `now()` el cruce de medianoche entre ellas daría un rojo que no viene del código — es el
        // fallo que esta suite ya pagó dos veces (`DECISIONES #64`, `#97`).
        $date = now()->addDays(3)->format('Y-m-d');
        $this->reservation($user, $this->pack(), $date, qty: 2);

        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertSee('Hola, Mara')                       // saludo (bloque del sidecart)
            // ⚠️ **`nav__acct-greet` se RETIRÓ el 2026-08-27** (armazón · tanda 2c·3, `DECISIONES
            // #203+`): el saludo VISIBLE del chip del nav desaparece por decisión del owner —sin
            // barra detrás, un botón que cambia de ancho con el nombre de cada visitante desalinea
            // el racimo—. Lo que NO podía perderse es que el nombre accesible siguiera **en
            // texto**, y eso se asevera aquí abajo y, acotado al elemento, en
            // `ArmazonContractTest::the_account_button_keeps_its_accessible_name_in_text`.
            // ⚠️ **RE-APUNTADO en la 2c·7**: el nombre accesible ya no EMPIEZA por el saludo, porque
            // el botón recuperó rótulo visible («Mi cuenta») al volverse una mitad expandible del
            // par, y «label in name» (WCAG 2.5.3) exige que el visible sea el PREFIJO. El saludo
            // sigue ahí —que es lo que no podía perderse— pero detrás.
            ->assertSee('Hola, Mara', false)               // el nombre accesible, en TEXTO
            ->assertSee('nav__acct-icon', false)            // icono de cuenta en el nav
            ->assertSee('nav__acct-dot', false)             // puntito (hay form pendiente)
            // ⚠️ El SUELO del hueco: la única salida de sesión servida de la aplicación (§4.8).
            ->assertSee('Cerrar sesión')
            ->assertSee(route('logout'), false);
    }

    // ⚠️⚠️ **Este caso era de sujeto MIXTO y se PARTIÓ el 2026-08-23** (`CONVENCIONES §3.quater`,
    // trampa 1). Aseveraba el chip del NAV —que sobrevive y sigue arriba, junto al suelo servido— y,
    // en las mismas líneas, el AVATAR, el CONTADOR, la sub-línea con su fecha y el aviso de
    // formulario, que son del bloque de cuenta y desde `specs/account-context-vue.md` los pinta Vue.
    // ▶ Dónde vive ahora esa mitad, y se comprobó ANTES de borrar: la SEMILLA con esos datos la
    // asevera `SidebarMountTest` —incluida la comparación con el endpoint—; qué compone cada rótulo,
    // `account/panel.test.js` (18 casos, con la fecha entre ellos); y que el servicio siga dando lo
    // que da, los ~20 casos de este mismo fichero, que **no se tocan**.

    public function test_account_icon_has_no_dot_without_pending_form(): void
    {
        $user = User::factory()->create(['name' => 'Mara', 'locale' => 'es']);
        // Reserva sin formulario pendiente (entrada, sin guest_fields).
        $this->reservation($user, $this->entry(), now()->addDays(3)->format('Y-m-d'));

        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertSee('nav__acct-icon', false)
            ->assertDontSee('nav__acct-dot', false);
    }
}
