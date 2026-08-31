<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\Ticket;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountCredentials;
use App\Domain\Identity\Services\AccountPrivacy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

/**
 * **Tanda 2 · paso 8** — `DELETE /api/v1/me` (art. 17) y `GET /api/v1/me/export` (art. 20)
 * (`specs/area-cliente.md` §9.3 y §9.5).
 *
 * Lo que estas guardas protegen, más allá de «borra» y «descarga»:
 *  - que borrar **NO borre la fila** sino que la anonimice, y que la factura sobreviva sin PII: es
 *    lo que separa cumplir el art. 17 de incumplir la conservación fiscal;
 *  - que la purga alcance la **PII de terceros** —nombres y alergias de menores— y **todas** las
 *    credenciales, incluida la de la petición (`RGPD-01`, `RGPD-06`). Una superficie que hubiera
 *    reimplementado el borrado habría hecho lo obvio y se habría dejado eso;
 *  - que la reconfirmación esté **LIMITADA** y comparta contador con `PUT /me/password`: dos
 *    limitadores distintos serían cinco intentos por cada endpoint, o sea diez;
 *  - y que el export sirva **exactamente el mismo documento** que la descarga de la web. Ésa es la
 *    guarda que se pondría roja el día que alguien reescribiera uno de los dos.
 */
class MePrivacyTest extends ApiTestCase
{
    private const PASSWORD = 'contrasena-actual-9';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear($this->limiterKey());
    }

    private function holder(): User
    {
        $user = new User;
        $user->name = 'Titular';
        $user->email = 'titular@ejemplo.test';
        $user->password = self::PASSWORD;
        $user->phone = '600111222';
        $user->locale = 'es';
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function limiterKey(int $id = 1): string
    {
        return 'account-credentials:'.$id.'|127.0.0.1';
    }

    /**
     * Un pedido con todo lo que el documento recorre: línea principal con franja y datos de evento,
     * un complemento anidado y una entrada emitida. Sin esto, la mitad del export se probaría vacía.
     *
     * ⚠️⚠️ **La franja es RELATIVA y por defecto PASADA** (T5 · D8, `cumple-mixto.md` §25.4). Hasta
     * el 2026-08-31 llevaba `2026-09-05` clavado: tres tests borraban una cuenta con una reserva
     * pagada FUTURA — la puerta de D8 los habría puesto en rojo HOY y en verde el 05-09, un verde
     * que cambia de significado con el calendario. La fecha se elige aquí, nunca la elige el reloj:
     * el pasado para lo que prueba la purga, `$slotDate` explícito para lo que prueba la puerta.
     */
    private function orderFor(User $user, string $code = 'JW-EXPORT', ?string $slotDate = null): Order
    {
        $zone = Zone::create(['slug' => 'z-'.Str::lower(Str::random(5)), 'name' => ['es' => 'Zona']]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => $slotDate ?? now()->subDays(7)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 50, 'online_capacity' => 50,
        ]);
        $pack = TicketType::create([
            'name' => ['es' => 'Cumple Jump'], 'zone_id' => $zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'zone_id' => $zone->id,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2,
        ]);

        $order = Order::create([
            'user_id' => $user->id, 'code' => $code, 'status' => Order::STATUS_PAID,
            'subtotal' => 9800, 'tax' => 0, 'total' => 9800, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);

        $line = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 9000, 'seats' => 1,
            'guest_data' => [['name' => 'Lucía', 'allergy' => 'frutos secos']],
            'event_data' => ['birthday_child' => 'Lucía', 'allergies' => 'frutos secos'],
        ]);
        $order->items()->create([
            'ticket_type_id' => $addon->id, 'slot_id' => $slot->id, 'parent_item_id' => $line->id,
            'quantity' => 2, 'unit_price' => 400, 'seats' => 0,
        ]);

        Ticket::create([
            'order_id' => $order->id, 'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'qr_token' => Str::random(32), 'status' => Ticket::STATUS_PURCHASED,
        ]);

        return $order;
    }

    // ── DELETE /me — el derecho de supresión ──────────────────────────────────────────────────

    /**
     * ⚠️ **La mitad que se lee mal si no se dice**: «borrar la cuenta» conserva la fila. La FK
     * `orders.user_id` es RESTRICT y la factura tiene que seguir vinculada (AEAT, ≥4 años).
     */
    public function test_it_anonymizes_the_holder_and_keeps_the_invoice(): void
    {
        $user = $this->holder();
        $this->orderFor($user);

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['current_password' => self::PASSWORD])
            ->assertNoContent();

        $fresh = User::find($user->id);

        $this->assertNotNull($fresh, 'la fila de `users` se ha borrado: la factura queda huérfana');
        $this->assertTrue($fresh->isAnonymized());
        $this->assertNotSame('titular@ejemplo.test', $fresh->email);
        $this->assertNull($fresh->phone);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'code' => 'JW-EXPORT', 'total' => 9800]);
    }

    /**
     * ⚠️⚠️ **T5 · D8** (`cumple-mixto.md` §25.4, `#284`): con una reserva POR CELEBRAR la supresión
     * NO se ejecuta — `409 account_has_upcoming_reservations` y NADA purgado. Es la puerta que
     * cierra por consecuencia la ficha del «techo tras anonimizar»: si ninguna cuenta con reserva
     * viva puede anonimizarse, ninguna reserva anonimizada se reconcilia.
     *
     * Mutación que la valida: quitar la llamada a `hasUpcomingFor()` en `AccountPrivacy` la pone
     * en rojo (y la de abajo, la del panel, cae con la suya).
     */
    public function test_it_refuses_to_delete_while_a_reservation_is_still_to_be_held(): void
    {
        $user = $this->holder();
        $order = $this->orderFor($user, 'JW-FUTURO', now()->addDays(14)->toDateString());
        $line = $order->items()->whereNull('parent_item_id')->firstOrFail();

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['current_password' => self::PASSWORD])
            ->assertStatus(409)
            ->assertValidResponse(409)
            ->assertJsonPath('error.code', 'account_has_upcoming_reservations');

        $fresh = User::find($user->id);
        $line->refresh();

        $this->assertFalse($fresh->isAnonymized(), 'la puerta de D8 no frenó la purga');
        $this->assertSame('titular@ejemplo.test', $fresh->email);
        $this->assertNotNull($line->guest_data, 'con la supresión bloqueada, la PII de terceros no se toca');
    }

    /** El escape de la puerta: una reserva futura CANCELADA ya no está «por celebrar». */
    public function test_a_cancelled_upcoming_reservation_does_not_block_the_deletion(): void
    {
        $user = $this->holder();
        $order = $this->orderFor($user, 'JW-CANCEL', now()->addDays(14)->toDateString());
        $order->items()->whereNull('parent_item_id')->firstOrFail()
            ->forceFill(['cancelled_at' => now()])->save();

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['current_password' => self::PASSWORD])
            ->assertNoContent();

        $this->assertTrue(User::find($user->id)->isAnonymized());
    }

    /**
     * ⚠️ **Una línea pagada SIN franja no bloquea, a propósito** (`cumple-mixto.md` §25.4): sin
     * slot, `isFinishedInPractice()` es `false` PARA SIEMPRE — bloquear por ella sería negar el
     * art. 17 sin fecha de fin. Y sin franja no hay fiesta que reconciliar, así que la
     * consecuencia de D8 se sostiene igual.
     */
    public function test_a_paid_line_without_a_slot_does_not_block_the_deletion(): void
    {
        $user = $this->holder();
        $zone = Zone::create(['slug' => 'z-'.Str::lower(Str::random(5)), 'name' => ['es' => 'Zona']]);
        $type = TicketType::create([
            'name' => ['es' => 'Bono'], 'zone_id' => $zone->id,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JW-SINSLOT', 'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => null,
            'quantity' => 1, 'unit_price' => 1000, 'seats' => 1,
        ]);

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['current_password' => self::PASSWORD])
            ->assertNoContent();

        $this->assertTrue(User::find($user->id)->isAnonymized());
    }

    /** Una cesta PENDING no es un compromiso del parque: caduca sola y no retiene la cuenta. */
    public function test_a_pending_cart_does_not_block_the_deletion(): void
    {
        $user = $this->holder();
        $order = $this->orderFor($user, 'JW-CESTA', now()->addDays(14)->toDateString());
        $order->forceFill(['status' => Order::STATUS_PENDING, 'paid_at' => null])->save();

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['current_password' => self::PASSWORD])
            ->assertNoContent();

        $this->assertTrue(User::find($user->id)->isAnonymized());
    }

    /**
     * ⚠️ **La parte que una reimplementación se habría dejado**: la PII de TERCEROS. Los nombres y
     * las **alergias** de los invitados (art. 9) viven en `order_items`, no en `users`, y borrar la
     * identidad del titular sin vaciarlos dejaría el dato de salud de un menor en la base.
     */
    public function test_it_purges_the_pii_of_the_guests_too(): void
    {
        $user = $this->holder();
        $order = $this->orderFor($user);
        $line = $order->items()->whereNull('parent_item_id')->firstOrFail();

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['current_password' => self::PASSWORD])
            ->assertNoContent();

        $line->refresh();

        $this->assertNull($line->guest_data);
        $this->assertNull($line->event_data);
    }

    /**
     * ⚠️ **En el art. 17 no hay ninguna credencial que conservar**, al revés que en «cerrar las
     * demás sesiones». Si sobreviviera un Bearer, la cuenta suprimida seguiría siendo utilizable.
     */
    public function test_it_revokes_every_credential_including_the_current_one(): void
    {
        $user = $this->holder();
        $user->createToken('movil');
        $user->createToken('tablet');

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['current_password' => self::PASSWORD])
            ->assertNoContent();

        $this->assertSame(
            0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count(),
            'un token ha sobrevivido a la supresión del art. 17'
        );
    }

    public function test_a_wrong_password_is_a_422_on_its_field_and_deletes_nothing(): void
    {
        $user = $this->holder();

        $response = $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['current_password' => 'no-es-esta']);

        $response->assertStatus(422)->assertValidResponse(422);
        $this->assertNotEmpty($response->json('error.fields.current_password'));

        $this->assertFalse(User::find($user->id)->isAnonymized(), 'se ha borrado la cuenta sin la contraseña');
    }

    public function test_it_blocks_after_five_wrong_attempts_and_says_how_long(): void
    {
        $user = $this->holder();

        for ($i = 0; $i < AccountCredentials::MAX_ATTEMPTS; $i++) {
            $this->actingAs($user)
                ->deleteJson(self::ROOT.'/me', ['current_password' => 'mal-'.$i])
                ->assertStatus(422);
        }

        $blocked = $this->actingAs($user)->deleteJson(self::ROOT.'/me', ['current_password' => 'mal-otra']);

        $blocked->assertStatus(429)->assertValidResponse(429);
        $this->assertNotEmpty($blocked->headers->get('Retry-After'));

        // ⚠️ Y con la BUENA también corta: el techo es del intento, no del acierto. Si se levantara
        // al acertar, quien va probando podría seguir indefinidamente el día que diera con ella.
        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['current_password' => self::PASSWORD])
            ->assertStatus(429);

        $this->assertFalse(User::find($user->id)->isAnonymized());
    }

    /**
     * ⚠️ **El limitador es el MISMO que el del cambio de contraseña, y eso es la decisión.** Un
     * contador propio por endpoint daría cinco intentos aquí *más* cinco allí sobre la misma cuenta
     * y la misma IP: el techo real sería el doble sin que nadie lo hubiera decidido.
     */
    public function test_it_shares_the_limiter_with_the_password_change(): void
    {
        $user = $this->holder();

        for ($i = 0; $i < AccountCredentials::MAX_ATTEMPTS; $i++) {
            $this->actingAs($user)
                ->putJson(self::ROOT.'/me/password', ['current_password' => 'mal-'.$i, 'password' => 'Rd8!zqLm4-Vt7wXe'])
                ->assertStatus(422);
        }

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['current_password' => self::PASSWORD])
            ->assertStatus(429);
    }

    /**
     * ⚠️ **La despedida, y por qué tiene caso propio.** La web termina en
     * `redirect('/')->with('status', …)` y el layout pinta ese aviso; el cajón sale a la home por su
     * cuenta, así que sin dejarlo en la sesión NUEVA el cliente aterrizaría en una home muda sin
     * saber si su cuenta se ha borrado de verdad. Lo cazó el navegador (`V10`), no la suite.
     *
     * ⚠️ Y el orden es lo que se está probando: el aviso va **después** de `invalidate()`, que vacía
     * la sesión. Puesto antes, se perdería — y nada lo diría.
     */
    public function test_it_leaves_the_farewell_notice_for_the_home_page(): void
    {
        $user = $this->holder();

        // ⚠️ **Con `Origin`, que es lo que convierte la petición en *stateful***: sin él,
        // `EnsureFrontendRequestsAreStateful` no monta `StartSession` y no hay sesión donde dejar
        // nada — el caso pasaría por el otro lado del `if` y no mediría el camino de la SPA
        // (`api-v1.md` §10.sexies 28). Un cliente Bearer llega justo así, y por eso el aviso es
        // opcional en el código.
        $this->actingAs($user)
            ->withHeader('Origin', (string) config('app.url'))
            ->deleteJson(self::ROOT.'/me', ['current_password' => self::PASSWORD])
            ->assertNoContent()
            ->assertSessionHas('status', AccountPrivacy::FAREWELL_STATUS);

        // Y es el MISMO aviso que deja la web: una clave distinta para el mismo momento serían dos
        // textos que alguien tendría que acordarse de cambiar a la vez.
        $this->assertNotSame(
            'account.status.'.AccountPrivacy::FAREWELL_STATUS,
            __('account.status.'.AccountPrivacy::FAREWELL_STATUS),
            'la clave de despedida no tiene texto en `lang/`: la home saldría muda'
        );
    }

    public function test_deleting_rejects_an_anonymous_request(): void
    {
        $this->deleteJson(self::ROOT.'/me', ['current_password' => 'x'])->assertStatus(401);
    }

    public function test_the_current_password_is_required_to_delete(): void
    {
        $this->actingAs($this->holder())->deleteJson(self::ROOT.'/me', [])->assertStatus(422);
    }

    // ── GET /me/export — el derecho de portabilidad ───────────────────────────────────────────

    public function test_it_serves_the_whole_document(): void
    {
        $user = $this->holder();
        $user->consents()->create([
            'type' => 'privacy', 'accepted_at' => now(), 'ip' => '127.0.0.1',
            'version' => Consent::CURRENT_VERSION,
        ]);
        $this->orderFor($user);

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/export');

        $response->assertOk()->assertValidResponse(200);

        $this->assertSame('titular@ejemplo.test', $response->json('profile.email'));
        $this->assertSame('600111222', $response->json('profile.phone'));
        $this->assertSame('privacy', $response->json('consents.0.type'));
        $this->assertSame('127.0.0.1', $response->json('consents.0.ip'));

        $this->assertSame('JW-EXPORT', $response->json('orders.0.code'));
        $this->assertSame(9800, $response->json('orders.0.total_cents'));
        $this->assertSame('Cumple Jump', $response->json('orders.0.items.0.product'));
        $this->assertSame(now()->subDays(7)->toDateString(), $response->json('orders.0.items.0.date'));
        $this->assertSame('10:00:00', $response->json('orders.0.items.0.time'));
        $this->assertSame('Calcetines', $response->json('orders.0.items.0.addons.0.product'));
        $this->assertSame(400, $response->json('orders.0.items.0.addons.0.unit_price_cents'));
    }

    /**
     * ⚠️⚠️ **Lo que este endpoint hace y `GET /me/orders` NO**: entregar el art. 9. La lista de
     * pedidos excluye `event_data` a propósito —se pinta sola en cada página— y el export lo lleva,
     * porque es el titular pidiendo su copia. Si alguien «armonizara» los dos, uno de los dos
     * derechos se rompería, y este caso dice cuál.
     */
    public function test_it_carries_the_event_data_that_the_order_list_hides(): void
    {
        $user = $this->holder();
        $this->orderFor($user);

        $export = $this->actingAs($user)->getJson(self::ROOT.'/me/export');
        $export->assertOk();
        $this->assertSame('Lucía', $export->json('orders.0.items.0.event_data.birthday_child'));

        $list = $this->actingAs($user)->getJson(self::ROOT.'/me/orders');
        $list->assertOk();
        $this->assertStringNotContainsString(
            'Lucía', $list->getContent(),
            'la LISTA de pedidos ha empezado a llevar datos del art. 9 en cada página'
        );
    }

    /** `RGPD-04`: es el cuerpo con más PII del producto y no puede quedar en ninguna caché. */
    public function test_the_export_is_not_stored(): void
    {
        $user = $this->holder();

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/export');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /** La identidad sale del guard: no hay parámetro con el que pedir el export de otro. */
    public function test_it_only_serves_the_holders_own_orders(): void
    {
        $user = $this->holder();
        $this->orderFor($user, 'JW-MIO');

        $other = User::factory()->create(['email' => 'otro@ejemplo.test']);
        $this->orderFor($other, 'JW-AJENO');

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/export');

        $this->assertSame(['JW-MIO'], array_column((array) $response->json('orders'), 'code'));
    }

    /**
     * ⚠️ **El hallazgo del paso 8, convertido en caso** (`DECISIONES #120(s)`): el bloque `tickets`
     * publicaba `code`, y `tickets` **no tiene esa columna** — salía `null` desde el commit
     * fundacional. Ahora lleva lo que la entrada es. Y **`qr_token` no sale**: es la credencial que
     * la canjea en la puerta, y un export es un fichero que se guarda y se reenvía.
     */
    public function test_the_tickets_carry_what_they_are_and_never_their_qr_token(): void
    {
        $user = $this->holder();
        $order = $this->orderFor($user);
        $qr = (string) $order->tickets()->value('qr_token');

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/export');

        $response->assertOk();
        $this->assertSame(Ticket::STATUS_PURCHASED, $response->json('orders.0.tickets.0.status'));
        $this->assertNotNull($response->json('orders.0.tickets.0.issued_at'));

        $this->assertNotSame('', $qr);
        $this->assertStringNotContainsString($qr, $response->getContent(), 'el export publica el QR de canje');
    }

    /**
     * ⚠️ **El orden es parte del contrato desde que esto es una respuesta de API.** Antes lo decidía
     * el motor —`$user->orders` no declaraba ninguno—, así que MySQL y el SQLite de la suite podían
     * no coincidir. Van del más antiguo al más reciente.
     */
    public function test_the_orders_come_oldest_first(): void
    {
        $user = $this->holder();
        $this->orderFor($user, 'JW-PRIMERO');
        $this->orderFor($user, 'JW-SEGUNDO');

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/export');

        $this->assertSame(['JW-PRIMERO', 'JW-SEGUNDO'], array_column((array) $response->json('orders'), 'code'));
    }

    /**
     * ⚠️⚠️ **La guarda que justifica el paso entero.** Las dos superficies sirven el MISMO
     * documento porque comparten `Identity\Services\AccountPrivacy`; el día que alguien reescriba
     * una de las dos —para «arreglar un campo» o para añadirle algo— este caso se pone rojo. Sin
     * él, la duplicación que el paso 8 evita podría volver sin que nada la delatara.
     */
    public function test_the_web_download_and_the_api_serve_the_same_document(): void
    {
        $user = $this->holder();
        $user->consents()->create([
            'type' => 'privacy', 'accepted_at' => now(), 'ip' => '10.0.0.9',
            'version' => Consent::CURRENT_VERSION,
        ]);
        $this->orderFor($user);

        $fromApi = (array) $this->actingAs($user)->getJson(self::ROOT.'/me/export')->json();
        $fromWeb = (array) json_decode(
            $this->actingAs($user)->get(route('account.export'))->getContent(), true
        );

        // `exported_at` es el sello del momento, lo único que legítimamente difiere entre dos
        // descargas. Todo lo demás tiene que ser idéntico, campo a campo.
        unset($fromApi['exported_at'], $fromWeb['exported_at']);

        $this->assertSame($fromApi, $fromWeb);
        $this->assertNotSame([], $fromApi['orders'], 'la comparación se ha hecho sobre un documento vacío');
    }

    // ── GET /me/consents — la prueba visible del art. 7.1 ─────────────────────────────────────

    /**
     * ⚠️ **Existe porque `/mi-cuenta` los enseña y esa página se retira** (tanda 3): sin publicarlos,
     * el borrado le quitaría al cliente información que hoy tiene.
     */
    public function test_it_lists_the_consents_newest_first(): void
    {
        $user = $this->holder();

        $user->consents()->create([
            'type' => 'privacy', 'accepted_at' => now()->subDays(3), 'ip' => '10.0.0.1', 'version' => '2026-05-23',
        ]);
        $user->consents()->create([
            'type' => 'waiver', 'accepted_at' => now()->subDay(), 'ip' => '10.0.0.2', 'version' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/consents');

        $response->assertOk()->assertValidResponse(200);

        $this->assertSame(['waiver', 'privacy'], array_column((array) $response->json('data'), 'type'), 'el orden no es del más reciente al más antiguo');
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame('2026-06-01', $response->json('data.0.version'));
        $this->assertNotSame('', (string) $response->json('data.0.accepted_label'), 'la fecha llega sin componer');
    }

    /**
     * ⚠️ **El rótulo lo compone el SERVIDOR**, para que el cliente no lleve su propia tabla de cuatro
     * nombres — una segunda lista que envejece sola el día que se añada un quinto tipo.
     */
    public function test_it_publishes_the_document_name_already_translated(): void
    {
        $user = $this->holder();
        $user->consents()->create([
            'type' => 'privacy', 'accepted_at' => now(), 'ip' => '10.0.0.1', 'version' => '1',
        ]);

        $label = $this->actingAs($user)->getJson(self::ROOT.'/me/consents')->assertOk()->json('data.0.type_label');

        $this->assertSame(__('account.account.privacy.consent_types.privacy'), $label);
        $this->assertNotSame('privacy', $label, 'se está publicando el identificador en vez del nombre');
    }

    /**
     * ⚠️⚠️ **Un tipo desconocido devuelve su identificador, nunca cadena vacía ni la clave cruda.**
     * `__()` sobre una clave que falta devuelve la clave entera —«account.account.privacy.
     * consent_types.foo»—, que en pantalla se lee como un error; y `''` sería peor, porque una fila
     * con fecha y sin nombre parece que no hay nada (familia de `DECISIONES #113`). Este caso es el
     * único que lo distingue: con los cuatro tipos conocidos, las tres implementaciones coinciden.
     */
    public function test_an_unknown_consent_type_never_shows_a_raw_key_or_an_empty_label(): void
    {
        $user = $this->holder();
        $user->consents()->create([
            'type' => 'inventado', 'accepted_at' => now(), 'ip' => '10.0.0.1', 'version' => '1',
        ]);

        $label = (string) $this->actingAs($user)->getJson(self::ROOT.'/me/consents')->assertOk()->json('data.0.type_label');

        $this->assertSame('inventado', $label);
        $this->assertStringNotContainsString('account.account', $label);
    }

    /**
     * ⚠️ **La IP NO sale**, igual que en la página: es parte de la prueba y viaja en el export, que
     * es un acto explícito del titular. Publicarla aquí sería añadirla a una lista que se pinta sola.
     */
    public function test_the_consent_list_never_carries_the_ip(): void
    {
        $user = $this->holder();
        $user->consents()->create([
            'type' => 'privacy', 'accepted_at' => now(), 'ip' => '203.0.113.77', 'version' => '1',
        ]);

        $body = (string) $this->actingAs($user)->getJson(self::ROOT.'/me/consents')->assertOk()->getContent();

        $this->assertStringNotContainsString('203.0.113.77', $body);

        // Y sí está donde el titular la pide expresamente.
        $export = (string) $this->actingAs($user)->getJson(self::ROOT.'/me/export')->assertOk()->getContent();

        $this->assertStringContainsString('203.0.113.77', $export, 'la IP ha desaparecido también del export');
    }

    public function test_the_consent_list_rejects_an_anonymous_request(): void
    {
        $this->getJson(self::ROOT.'/me/consents')->assertStatus(401);
    }

    public function test_the_export_rejects_an_anonymous_request(): void
    {
        $this->getJson(self::ROOT.'/me/export')->assertStatus(401);
    }

    /**
     * La cuenta anonimizada ya no tiene credencial con la que pedir nada, pero si alguna vía la
     * conservara, el export no puede seguir entregando la PII de sus pedidos: `anonymize()` ya la
     * vació, y este caso lo comprueba de punta a punta.
     */
    public function test_after_deleting_the_export_no_longer_carries_the_guest_pii(): void
    {
        $user = $this->holder();
        $this->orderFor($user);

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['current_password' => self::PASSWORD])
            ->assertNoContent();

        $response = $this->actingAs(User::find($user->id))->getJson(self::ROOT.'/me/export');

        $response->assertOk();
        $this->assertStringNotContainsString('Lucía', $response->getContent());
        $this->assertSame('JW-EXPORT', $response->json('orders.0.code'), 'la factura tiene que seguir ahí');
    }
}
