<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ReservationAdmissionPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Contracts\PaymentInitiationException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentInitiator;
use App\Domain\Payments\Services\RedsysResponseCode;
use App\Domain\Platform\Models\Setting;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.6, re-apuntado en 4.7·2b·2 — **el desenlace del pago, contra las fuentes que
 * sobreviven** (`DECISIONES #85`).
 *
 * ⚠️ **La compra la crea `POST /api/v1/orders`, no el motor que se va.** Ese era el nudo: `purchase()`
 * conducía el componente y los catorce casos partían de ella, así que ninguno se podía re-apuntar por
 * separado. Cambiada esa pieza, cada caso encontró su referencia — y el pedido nace además por el
 * mismo camino que usará el cajón.
 *
 * ### Lo que este fichero es hoy, y lo que ya no
 *
 *  · **Los MOTIVOS del rechazo son su razón de ser**, y está medido: renombrar
 *    `payment_failed.reasons.cvv_wrong` en `lang/es/tickets.php` deja los 2714 casos verdes **salvo
 *    dos, y los dos son de aquí**. `outcome.test.js` no puede verlo —su diccionario es fabricado—.
 *    La referencia es `RedsysResponseCode`, que es dominio: `Purchase::resolveDeclinedReason()`
 *    terminaba en esa misma llamada, o sea que era un intermediario.
 *  · **Las divergencias declaradas se quedan con su mitad viva**: el resumen acotado a la fase
 *    `booking` (RGPD, §4.4.6), el estado EFECTIVO del pedido caducado y el silencio de la API sobre
 *    un rechazo anterior con otro cobro en vuelo. En las tres, la API es la que acierta.
 *  · **Los destinos del reintento y del sondeo se VOLCARON del motor vivo** antes de retirarlo
 *    (técnica de `#81`): eran su única declaración escrita.
 *  · **Lo que se retiró, medido**: la comparación del resumen contra el view-model —su composición la
 *    cubre `outcome.test.js` y sus campos `OrderSummaryFieldsTest`/`OrderEventDataTest`, y se
 *    comprobó que un `park_cents` compuesto restando es un mutante EQUIVALENTE para este fixture— y
 *    los dos casos del enlace de registro, que `PublicConfigTest` cubre mejor, con las cuatro URLs
 *    hostiles del saneado de `SEC-07` que aquí no se probaban.
 *
 * ⚠️ **Residual DECLARADO** (`#84(c)`): que un motor PINTE las URLs del desenlace ya no lo comprueba
 * nadie —`href` no es atributo de contrato y los módulos planos no las tocan: las consumen los
 * `.vue`—. Lo que se afirma aquí es que el servidor las compone con `route()` y las publica.
 */
class SidebarOutcomeParityTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private int $rateId;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump', 'color' => '#FF5B22',
            'position' => 1, 'is_active' => true,
        ]);

        $this->date = now()->addDay()->toDateString();

        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->date,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 60, 'online_capacity' => 60,
        ]);
    }

    // ── El resumen entero ─────────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **Y este es el caso que de verdad prueba el emparejado**, porque el de arriba NO lo hace: se
     * midió por mutación que un cliente que recorriera las dos listas EN PARALELO lo pasa en verde.
     * El motivo es que hoy el endpoint devuelve las reservas en el mismo orden que las líneas del
     * pedido, así que posición y llave coinciden **por casualidad**.
     *
     * El contrato no promete ningún orden, así que aquí se le da la vuelta al sobre y se exige el
     * MISMO resumen. Es la lección de 4.0b·5 aplicada: un test de cadena puede pasar sin probar la
     * cadena, y el caso tiene que forzar el orden que obliga a usar la llave.
     */
    public function test_the_order_of_the_answers_envelope_does_not_change_the_summary(): void
    {
        [$user, $code] = $this->purchase(second: true);

        $straight = $this->summaryFromApi($user, $code);
        $reversed = $this->summaryFromApi($user, $code, reverseAnswers: true);

        $this->assertSame(
            $straight, $reversed,
            "El resumen CAMBIA si las respuestas llegan en otro orden.\n".
            '⚠️ Eso es emparejar por posición: el nombre de un niño acabaría bajo la reserva de otro, '.
            'con el árbol intacto y el gate en verde.'
        );
    }

    // ── Las divergencias DECLARADAS ───────────────────────────────────────────────────────────

    /**
     * ⚠️ **DIVERGENCIA DECLARADA (§4.4.6, `DECISIONES #39`): solo la fase `booking`.**
     *
     * `event_data` guarda juntas las respuestas de las dos fases y el endpoint publica **solo las de
     * la reserva**; el Blade las pinta todas. La consecuencia aceptada está escrita desde 4.0b·4b: lo
     * que un operador rellene del post-form desde el panel sale en el cajón Livewire y no en el SPA.
     *
     * Se fija con un caso para que sea una decisión y no un descubrimiento: el día que alguien quiera
     * cambiarla, este test la nombra en vez de dejar que se cuele por el camino de arreglar otra cosa.
     */
    public function test_the_post_form_answers_are_declared_out_of_the_api_summary(): void
    {
        [$user, $code] = $this->purchase(withGuestStage: true);

        // Lo que un operador rellenaría después, desde el panel: misma columna, otra fase.
        $item = Order::where('code', $code)->firstOrFail()->items()->whereNull('parent_item_id')->firstOrFail();
        $item->update(['event_data' => array_merge((array) $item->event_data, ['guest_name' => 'Ada'])]);

        $client = $this->summaryFromApi($user, $code);

        $this->assertSame(
            ['Mara'], array_column($client['lines'][0]['event'], 'value'),
            "La API ha dejado de acotar el resumen a la fase `booking`.\n".
            '⚠️ Es una decisión de RGPD (§4.4.6): las del post-form tienen su propio endpoint, que se '.
            'abre con firma. Dos caminos hacia el mismo dato del art. 9 es superficie que nadie pidió.'
        );
    }

    /**
     * ⚠️ **DIVERGENCIA DECLARADA: el estado que se pinta es el EFECTIVO.**
     *
     * El Blade ramifica sobre la columna `status` y la API publica `displayStatus()`, que da `expired`
     * a un pedido cuyo hold ya venció aunque el barrido no haya pasado. Con eso, un pedido pendiente y
     * caducado hace que Livewire diga «pendiente de pago» y el cajón SPA **no diga nada**.
     *
     * Se declara así a propósito: prometer un pago pendiente sobre una reserva que ya no existe es
     * peor que callar. Solo es alcanzable por el enlace de verificación de correo pulsado tarde.
     */
    public function test_an_expired_hold_is_reported_as_expired_by_the_api(): void
    {
        [$user, $code] = $this->purchase();
        Order::where('code', $code)->update(['expires_at' => now()->subMinute()]);

        $client = $this->summaryFromApi($user, $code);

        $this->assertSame(
            Order::STATUS_EXPIRED, $client['status'],
            "La API ha dejado de publicar el estado EFECTIVO del pedido.\n".
            '⚠️ Es lo que hace que el cajón no prometa «pendiente de pago» sobre una reserva cuya plaza '.
            'ya volvió al inventario.'
        );
    }

    // ── El paso 10: el motivo del rechazo ─────────────────────────────────────────────────────

    /**
     * ⚠️ **El cajón cubre el mapa ENTERO de motivos del servidor, en los tres idiomas.**
     *
     * `declined_reason` es literalmente lo que devuelve `RedsysResponseCode::reasonKey()`, que es la
     * clave bajo `tickets.payment_failed.reasons.*`, así que el cajón no tiene tabla que mantener… **y
     * ahí está el riesgo**: `i18n.js` devuelve cadena vacía cuando la clave no existe, de modo que un
     * motivo nuevo en el servidor pintaría el rótulo «Motivo:» **con nada detrás** y ningún gate lo
     * diría. Aquí se recorre `REASON_MAP` completo —por reflexión, para no copiar una tabla que ya
     * existe— y se compara con el texto que resuelve el servidor.
     */
    public function test_the_client_says_the_same_reason_as_the_server_for_every_code(): void
    {
        /** @var array<string, string> $map */
        $map = (new \ReflectionClass(RedsysResponseCode::class))->getConstant('REASON_MAP');

        $this->assertNotSame([], $map, 'el servidor tiene que publicar motivos, o este test no mira nada');

        // Un código desconocido y el nulo entran también: son los dos caminos al genérico.
        $codes = array_merge(array_keys($map), ['9999-inventado', null]);

        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            $server = array_map(fn (?string $ds): string => RedsysResponseCode::reasonText($ds), $codes);
            $keys = array_map(fn (?string $ds): string => RedsysResponseCode::reasonKey($ds), $codes);
            $client = $this->reasonsInNode($keys, __('tickets'));

            $this->assertSame(
                $server, $client,
                "El motivo del rechazo NO dice lo mismo en los dos motores, en «{$locale}».\n".
                '⚠️ Un motivo que el diccionario no tenga se pinta VACÍO: el rótulo «Motivo:» sin nada '.
                'detrás, en la pantalla en la que el cliente quiere saber por qué le han rechazado.'
            );
        }
    }

    /**
     * Y de punta a punta sobre un rechazo REAL: el código que publica la API es el que el cajón usa de
     * clave, y el texto coincide con el que compone el componente Livewire.
     */
    public function test_a_real_declined_payment_reaches_the_domains_own_text(): void
    {
        [$user, $code] = $this->purchase();
        $this->failLastPayment($code, '0129');       // CVV erróneo

        // La referencia es el DOMINIO, no el componente: `Purchase::resolveDeclinedReason()`
        // terminaba en esta misma llamada, así que era un intermediario (misma re-apuntada que #81).
        $server = RedsysResponseCode::reasonText('0129');

        $status = $this->paymentStatus($user, $code);

        $this->assertSame('cvv_wrong', $status['declined_reason'], 'la API publica la CLAVE del motivo');
        $this->assertSame(
            $server, $this->reasonsInNode([$status['declined_reason']], __('tickets'))[0],
            'los dos motores tienen que decir el mismo porqué'
        );
    }

    /**
     * ⚠️ **DIVERGENCIA DECLARADA: el motivo de un rechazo ANTERIOR no se enseña si hay otro cobro en
     * curso.**
     *
     * Livewire busca el último `Payment` en estado `failed` del pedido; la API solo publica el motivo
     * si el ÚLTIMO intento es el rechazado. Con un reintento en vuelo, el Blade sigue diciendo «tarjeta
     * caducada» sobre un cobro que está esperando respuesta, y el cajón cae al genérico.
     *
     * La API es la que acierta —lo dice su propio contrato— y por eso la divergencia se declara en vez
     * de copiarse. No es alcanzable desde la pantalla: al reintentar se sale al paso 9.
     */
    public function test_a_previous_decline_is_not_reported_while_another_payment_is_in_flight(): void
    {
        [$user, $code] = $this->purchase();
        $this->failLastPayment($code, '0101');

        // Un cobro NUEVO sobre el mismo pedido: el rechazo anterior queda atrás.
        $order = Order::where('code', $code)->firstOrFail();
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'provider' => 'redsys', 'gateway_order' => 'GO'.$order->id.'B',
            'amount' => $order->total, 'currency' => 'EUR', 'status' => Payment::STATUS_PENDING,
        ]);

        // El motivo del intento ANTERIOR sigue en la base de datos —`RedsysResponseCode` lo
        // traduciría—, y aun así la API calla porque el ÚLTIMO intento no es el rechazado.
        $this->assertSame(
            __('tickets.payment_failed.reasons.card_expired'),
            RedsysResponseCode::reasonText('0101'),
            'el caso necesita que ese código siga siendo «tarjeta caducada»'
        );
        $this->assertNull(
            $this->paymentStatus($user, $code)['declined_reason'],
            "La API ha empezado a publicar el motivo de un rechazo ANTERIOR.\n".
            '⚠️ Con otro cobro en curso, eso le diría al cliente que su tarjeta ha fallado cuando en '.
            'realidad está esperando respuesta.'
        );
    }

    // ── El paso 10: el reintento ──────────────────────────────────────────────────────────────

    /**
     * ⚠️ **Los CUATRO «no» del reintento, provocados de VERDAD contra la API**, y el destino de cada uno
     * comparado con el del componente Livewire.
     *
     * La distinción que importa es la del paso 2: solo `order_not_retryable` significa que ya no hay
     * nada que pagar. La pausa y el límite de frecuencia dejan la reserva **intacta**, y confundirlos
     * le diría a quien pulsó dos veces seguidas que ha perdido su plaza.
     */
    public function test_every_retry_denial_sends_the_client_where_it_must(): void
    {
        // ⚠️ **Los destinos se VOLCARON del motor vivo antes de retirarlo** (`DECISIONES #85`), que es
        // la técnica de `#81`: eran su única declaración y después no habría contra qué contrastarlos.
        $expected = [
            // La pausa y el límite dejan la reserva INTACTA: no se mueve a nadie de sitio.
            'paused' => null,
            'rate_limited' => null,
            // Solo este significa que ya no hay nada que pagar, y por eso es el único que devuelve
            // al catálogo. Confundirlo con los otros le diría a quien pulsó dos veces seguidas que
            // ha perdido su plaza.
            'not_retryable' => 'catalog',
        ];

        foreach ($expected as $scenario => $goTo) {
            $this->assertSame(
                $goTo, $this->retryBoth($scenario)['goTo'],
                "El reintento denegado por «{$scenario}» no deja al cliente donde debe.\n".
                '⚠️ Solo `order_not_retryable` obliga a rehacer la reserva; los otros la dejan viva.'
            );
        }
    }

    /**
     * El cuarto «no» va aparte porque su avería NO se puede deshacer: sustituye la pasarela en el
     * contenedor, y una compra posterior en el mismo test se encontraría el sustituto.
     *
     * ⚠️ Y su regla es la contraria a la de crear: **el pedido NO se toca**. Sigue vivo con su hold
     * recién extendido, así que los dos motores dejan al cliente donde está para que pueda repetir.
     */
    public function test_a_gateway_failure_on_retry_leaves_the_client_where_it_was(): void
    {
        $client = $this->retryBoth('gateway');

        $this->assertNull($client['goTo'], 'el cajón no se mueve: la reserva sigue viva');
        $this->assertSame(__('tickets.errors.payment_unavailable'), $client['error']);
    }

    /** Y el reintento que SÍ se admite sale a la pasarela, con la URL que publica la propia API. */
    public function test_an_admitted_retry_leaves_for_the_gateway(): void
    {
        [$user, $code] = $this->purchase();
        $this->failLastPayment($code, '0101');

        [$user2, $code2] = $this->purchase();
        $this->failLastPayment($code2, '0101');
        $client = $this->retryInNode($this->retryResponse($user2, $code2));

        $this->assertTrue($client['ok'], 'y el del cajón también');
        $this->assertSame(
            $this->retryResponse($user, $code)['data']['payment']['url'] ?? null, $client['form']['url'],
            'los dos motores tienen que mandar el reintento al MISMO sitio'
        );
    }

    // ── El paso 11: el sondeo ─────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **El sondeo se mueve con las MISMAS dos salidas que `checkPaymentStatus()`**, y las respuestas
     * son las de la API real.
     *
     * El caso que importa es el tercero: un pedido cuyo último intento está `failed` pero que sigue
     * `pending` **no mueve el cajón**. Es el corazón de esta pantalla —la notificación de la pasarela
     * puede estar todavía en vuelo— y ampliar la acotación diría «no has pagado» a quien sí pagó.
     */
    public function test_the_poll_moves_the_drawer_exactly_where_it_must(): void
    {
        // (1) Pagado → paso 6, en los dos motores.
        [$user, $code] = $this->purchase();
        Order::where('code', $code)->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $this->assertSame('confirmed', $this->pollVerdictInNode($this->paymentStatus($user, $code)));

        // (2) Caducado → vuelta al catálogo, con el mismo aviso.
        [$user2, $code2] = $this->purchase();
        Order::where('code', $code2)->update(['status' => Order::STATUS_EXPIRED]);

        $this->assertSame('expired', $this->pollVerdictInNode($this->paymentStatus($user2, $code2)));

        // (3) Un intento FALLIDO con el pedido todavía pendiente: nadie se mueve.
        [$user3, $code3] = $this->purchase();
        $this->failLastPayment($code3, '0101');

        $status = $this->paymentStatus($user3, $code3);

        $this->assertSame('failed', $status['payment_status'], 'el caso necesita un intento rechazado');
        $this->assertSame(
            'wait', $this->pollVerdictInNode($status),
            "El cajón se mueve con un intento fallido y Livewire no.\n".
            '⚠️ La notificación de la pasarela puede estar en vuelo: salir de aquí le diría «no has '.
            'pagado» a quien sí pagó.'
        );
    }

    // ── Los dos enlaces que el diff de árbol NO ve ────────────────────────────────────────────

    /**
     * ⚠️ **`href` no es atributo de contrato**, así que un cajón que mandara «escribirnos» y «ver mis
     * reservas» a cualquier otro sitio —o a un 404— pasaría el gate en VERDE. Es la misma lección del
     * WhatsApp del aviso de pausa y del enlace de registro, y por eso las dos URLs las compone el
     * SERVIDOR con `route()` y viajan en el payload de montaje.
     */
    public function test_the_server_composes_the_outcome_links(): void
    {
        [, $code] = $this->purchase();

        $boot = $this->bootPayload();

        $this->assertSame(route('contacto'), $boot['urls']['contact'] ?? null);
        $this->assertSame(route('account.orders'), $boot['urls']['my_orders'] ?? null);

        // ⚠️ **Lo que se comprobaba sobre el Blade se fue con él, y es un residual DECLARADO**
        // (`DECISIONES #84(c)`): que un motor PINTE esas URLs no lo puede ver el diff de árbol
        // —`href` no es atributo de contrato— ni los módulos planos, que no las tocan: las consumen
        // los `.vue`. Lo que se queda es que el servidor las COMPONE con `route()` y las publica.
        $this->assertNotSame('', (string) ($boot['urls']['contact'] ?? ''), 'la URL tiene que viajar compuesta');
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /**
     * Los cuatro escenarios de reintento denegado: se provoca el MISMO estado para los dos motores y se
     * devuelve a dónde va cada uno.
     *
     * @return array{0: string|null, 1: array<string, mixed>} destino de Livewire, veredicto del cajón
     */
    private function retryBoth(string $scenario): array
    {
        // ⚠️ La pausa es estado GLOBAL y se pega entre escenarios: sin este reseteo, el segundo del
        // bucle no podría ni comprar y el caso fallaría por el sitio equivocado.
        Setting::where('key', 'reservations.paused')->delete();
        Setting::flushMemo();

        // ⚠️ **Los DOS pedidos se crean ANTES de romper nada**, y no es un detalle: con las reservas
        // pausadas no se puede comprar, así que aplicar el escenario en medio dejaría al segundo motor
        // sin pedido que reintentar y el caso fallaría por el sitio equivocado.
        // Y son DOS pedidos de dos titulares porque el reintento CONSUME estado —ficha del limitador,
        // hold extendido—: compartirlo mediría dos situaciones distintas.
        [$user, $code] = $this->purchase();
        [$user2, $code2] = $this->purchase();
        $this->failLastPayment($code, '0101');
        $this->failLastPayment($code2, '0101');

        $this->applyRetryScenario($scenario, $user, $code);
        $this->applyRetryScenario($scenario, $user2, $code2);

        return $this->retryInNode($this->retryResponse($user2, $code2));
    }

    private function applyRetryScenario(string $scenario, User $user, string $code): void
    {
        match ($scenario) {
            'paused' => $this->pauseReservations(),
            // El hold ya cruzó: la plaza pudo cederse, así que no hay nada que reabrir.
            'not_retryable' => Order::where('code', $code)->update(['expires_at' => now()->subMinute()]),
            'rate_limited' => $this->exhaustRetryLimiter($user),
            'gateway' => $this->breakGateway(),
            default => null,
        };
    }

    private function pauseReservations(): void
    {
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1', 'group' => 'general']);
        Setting::flushMemo();
    }

    private function exhaustRetryLimiter(User $user): void
    {
        for ($i = 0; $i < ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE; $i++) {
            RateLimiter::hit('reservation-confirm:'.$user->id, 60);
        }
    }

    /** Una pasarela que no abre. El pedido NO se toca: sigue vivo con su hold recién extendido. */
    private function breakGateway(): void
    {
        $this->app->bind(PaymentInitiator::class, fn () => new class extends PaymentInitiator
        {
            public function __construct() {}

            public function reopen($order, ?string $preferredLocale = null, string $source = self::SOURCE_RETRY_SIDEBAR): never
            {
                throw new PaymentInitiationException('la pasarela no responde');
            }
        });
    }

    /** La respuesta REAL de `POST /orders/{code}/payment`, en la forma que ve `api.js`. */
    private function retryResponse(User $user, string $code): array
    {
        $response = $this->actingAs($user)
            ->postJson("/api/v1/orders/{$code}/payment", [], ['Origin' => config('app.url')]);

        return [
            'ok' => $response->isSuccessful(),
            'status' => $response->getStatusCode(),
            'data' => $response->json(),
            'error' => $response->json('error'),
        ];
    }

    /** La respuesta REAL de `GET /orders/{code}/payment-status`. */
    private function paymentStatus(User $user, string $code): array
    {
        return $this->actingAs($user)
            ->getJson("/api/v1/orders/{$code}/payment-status", ['Origin' => config('app.url')])
            ->assertOk()
            ->json();
    }

    private function failLastPayment(string $code, string $responseCode): void
    {
        Payment::whereHas('payable', fn ($q) => $q->where('code', $code))
            ->latest('id')
            ->firstOrFail()
            ->update(['status' => Payment::STATUS_FAILED, 'raw_response' => ['Ds_Response' => $responseCode]]);
    }

    /** @return array<string, mixed> */
    private function bootPayload(): array
    {
        Setting::updateOrCreate(['key' => 'sidebar.engine'], ['value' => 'spa', 'group' => 'general']);
        Setting::flushMemo();

        $html = $this->get('/')->getContent();

        if (preg_match('/id="sidecart-spa" data-boot="([^"]*)"/', $html, $matches) !== 1) {
            $this->fail('no se ha encontrado el punto de montaje de la SPA en la página');
        }

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $messages
     * @return list<string>
     */
    private function reasonsInNode(array $keys, array $messages): array
    {
        return $this->runInNode(<<<'JS'
            import { declinedReasonText } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { keys, messages } = JSON.parse(raw);
                process.stdout.write(JSON.stringify({ out: keys.map((k) => declinedReasonText(messages, k)) }));
            });
            JS, ['keys' => $keys, 'messages' => $messages])['out'];
    }

    /** @param array<string, mixed> $status */
    private function pollVerdictInNode(array $status): string
    {
        return $this->runInNode(<<<'JS'
            import { pollVerdict } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify({ out: pollVerdict(JSON.parse(raw).status) }));
            });
            JS, ['status' => $status])['out'];
    }

    /**
     * El reintento pasado por el módulo REAL, con la respuesta REAL de la API.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function retryInNode(array $response): array
    {
        return $this->runInNode(<<<'JS'
            import { runRetry } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', async () => {
                const { response, messages } = JSON.parse(raw);
                const api = { post: async () => response };
                process.stdout.write(JSON.stringify({ out: await runRetry({ orderCode: 'R-X', api, messages }) }));
            });
            JS, ['response' => $response, 'messages' => __('tickets')])['out'];
    }

    /**
     * Una compra REAL, creada por **`POST /api/v1/orders`** (re-apuntada en 4.7·2b·2,
     * `DECISIONES #85`).
     *
     * ⚠️ **Esta pieza era el nudo del fichero**: conducía el componente Livewire y los catorce casos
     * partían de ella, así que ninguno se podía re-apuntar por separado. Ahora la compra se crea por
     * el mismo camino que usa el cajón —receta de `#65`, con `Origin` y la cesta en el cuerpo—, que
     * además es **más fiel**: el pedido nace exactamente como nacerá en producción.
     *
     * El pack lleva señal, respuestas de evento y un complemento INCLUIDO con una unidad extra: es la
     * línea más rica que el resumen puede tener, y por eso es la que se compra.
     *
     * @return array{0: User, 1: string} titular y código del pedido
     */
    private function purchase(bool $second = false, bool $withGuestStage = false): array
    {
        $pack = $this->pack('Cumpleaños', $withGuestStage);
        $cake = $this->addon($pack, 'Tarta', 1000);

        $user = User::factory()->create();
        $this->actingAs($user);

        $items = [[
            'product_id' => $pack->id, 'date' => $this->date, 'time' => '10:00:00',
            'quantity' => $pack->min_qty,
            'event_data' => ['celebrant' => 'Mara'],
            // Dos unidades de un incluido con `allow_extra`: una gratis y otra cobrada.
            'addons' => [['product_id' => $cake->id, 'quantity' => 2]],
        ]];

        if ($second) {
            // Un SEGUNDO pack en el mismo pedido: es lo único que puede destapar un emparejado por
            // posición, y con una línea sola el test pasaría con el fallo dentro.
            $other = $this->pack('Aniversario');
            $items[] = [
                'product_id' => $other->id, 'date' => $this->date, 'time' => '10:00:00',
                'quantity' => $other->min_qty,
                'event_data' => ['celebrant' => 'Nil'],
            ];
        }

        $response = $this->postJson('/api/v1/orders', ['items' => $items], ['Origin' => config('app.url')])
            ->assertCreated();

        $code = (string) $response->json('order.code');
        $this->assertNotSame('', $code, 'el caso tiene que haber creado el pedido');

        return [$user, $code];
    }

    private function pack(string $name, bool $withGuestStage = false): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'min_qty' => 6, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => (int) TicketType::max('position') + 1,
            'deposit_type' => 'fixed', 'deposit_value' => 3000,
            'event_fields' => array_values(array_filter([
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Homenajeado']],
                // ⚠️ La fase se escribe con su CONSTANTE. Un valor inventado (`guest_form`) no falla:
                // `normalizeFieldSchema()` lo normaliza a `booking`, así que el campo se pintaría en
                // las dos superficies y el caso pasaría **probando lo contrario de lo que dice**.
                $withGuestStage
                    ? ['key' => 'guest_name', 'type' => 'text', 'required' => false, 'stage' => TicketType::EVENT_STAGE_POSTFORM, 'label' => ['es' => 'Invitado']]
                    : null,
            ])),
        ]);
        $pack->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => 5000]);

        return $pack;
    }

    private function addon(TicketType $parent, string $name, int $priceCents): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON, 'zone_id' => $this->zone->id,
            'duration_min' => 0, 'min_qty' => 1, 'seats_per_unit' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => (int) TicketType::max('position') + 1,
        ]);
        $addon->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => $priceCents]);
        $parent->addons()->attach($addon->id, [
            'is_included' => true, 'included_quantity' => 1, 'allow_extra' => true, 'position' => 1,
        ]);

        return $addon;
    }

    /**
     * Lo que el cajón compone de verdad: las DOS respuestas de la API pasadas por el módulo REAL,
     * ejecutado en Node.
     *
     * @return array<string, mixed>
     */
    private function summaryFromApi(User $user, string $code, bool $reverseAnswers = false): array
    {
        $headers = ['Origin' => config('app.url')];

        $order = $this->actingAs($user)->getJson("/api/v1/orders/{$code}", $headers)->assertOk()->json();
        $eventData = $this->actingAs($user)->getJson("/api/v1/orders/{$code}/event-data", $headers)->assertOk()->json();

        // El contrato no promete ningún orden en `reservations`, y hoy coincide con el de las líneas.
        // Darle la vuelta es lo único que distingue emparejar por LLAVE de emparejar por posición.
        if ($reverseAnswers) {
            $eventData['reservations'] = array_reverse($eventData['reservations']);
        }

        return $this->runInNode(<<<'JS'
            import { buildConfirmation } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { order, eventData } = JSON.parse(raw);
                process.stdout.write(JSON.stringify({ out: buildConfirmation(order, eventData) }));
            });
            JS, ['order' => $order, 'eventData' => $eventData])['out'];
    }

    /**
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     */
    private function runInNode(string $script, array $input): array
    {
        $path = base_path('storage/framework/testing/outcome-parity.mjs');

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/outcome.js'), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo del desenlace falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
