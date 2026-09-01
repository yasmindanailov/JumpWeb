<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * **La zona «Mis reservas» del cajón, alimentada por la respuesta REAL del servidor**
 * (`docs/specs/area-cliente.md` §6.2).
 *
 * ⚠️⚠️ **Contra la API, NO contra la página que este cajón sustituye.** La v1 de la spec proponía
 * comparar con `/mi-cuenta/pedidos`, y estaba mal por dos motivos: esa página **está condenada**
 * (`DECISIONES #120(c)`), así que la red caducaría el día que se borre; y es literalmente el error
 * que `#67` corrigió en 4.7·2b·2·B —alimentar el gate desde el motor que se va hace que el gate nunca
 * ejercite el camino real—. Lo que sobrevive a la retirada es el contrato, y contra él se compara.
 *
 * ⚠️ **Y lo que aquí se prueba NO está en `orders.test.js`**: allí los fixtures los escribe el mismo
 * que escribe el módulo, así que un campo mal entendido sale verde en las dos mitades. Aquí la
 * entrada la produce el servidor y las expectativas se componen con **los mismos servicios de PHP**
 * que usa la web —`Money::format`, `DisplayTime::dayLabel`, `__()`—, de modo que una divergencia de
 * formato aparece como lo que es.
 */
class SidebarAccountParityTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/api/v1';

    /**
     * ⚠️ **El reloj se congela**, por lo mismo que en el resto de las paridades del cajón: el estado
     * de una línea (`active`/`finished`) depende de si su franja ya pasó, y una foto que incluye el
     * tiempo hay que tomarla con el reloj parado (`DECISIONES #64`).
     */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * **El caso completo**: un pedido pagado con un pack con señal y post-form pendiente, su
     * complemento y una línea cancelada. Es la fila más rica que la zona puede pintar.
     */
    public function test_the_zone_paints_what_the_server_says_field_by_field(): void
    {
        [$user, $order] = $this->richOrder();

        $payload = $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming')->assertOk()->json();
        $rows = $this->composeInNode($payload);

        // ⚠️ **DOS tarjetas y no una**: el pedido rico lleva un pack y una línea cancelada, y desde
        // el 2026-08-23 la pantalla lista por RESERVA. La cancelada cae en el otro ámbito, así que
        // aquí llega una sola — y eso ya es una aserción sobre el reparto del servidor.
        $this->assertCount(1, $rows, 'la respuesta real no ha producido una fila');
        $pack = $rows[0];

        $this->assertSame($order->code, $pack['orderCode']);
        $this->assertSame(__('tickets.statuses.paid'), $pack['orderStatusLabel'], 'el rótulo del estado no sale del diccionario de la web');
        $this->assertSame(DisplayTime::format($order->created_at), $pack['orderCreatedLabel']);

        $this->assertSame(
            DisplayTime::dayLabel('2026-06-10').' · 10:00–11:00', $pack['whenLabel'],
            'el día y la ventana no coinciden con lo que compone el servidor'
        );
        $this->assertSame(Money::format(9000), $pack['priceLabel']);
        $this->assertNull($pack['badge'], 'una reserva futura y viva no lleva distintivo');

        // ⚠️⚠️ **`L2`**: la cantidad se pinta CON su sustantivo, y la compone el servidor. El número
        // pelado seguido del importe —`1×90,00 €`— no distingue cantidad de importe: con 8 invitados
        // se lee 8 × 216 € = 1.728 € cuando son 216 € en total
        // (`specs/desglose-dinero-cliente.md` §17.1).
        $this->assertSame(__('tickets.guests_count', ['count' => 1]), $pack['quantityLabel']);

        // ⚠️⚠️ **AQUÍ YA NO HAY DINERO** (2026-08-24, `DECISIONES #130`, decisión del owner): el
        // importe de la línea y la nota de la señal se fueron a «Mis pedidos», que es donde el dinero
        // cuadra con lo que lo rodea. La nota ya ni se compone —`depositNoteOf()` murió con ella—.
        // ⚠️ Que la PLANTILLA no pinte importes **no puede aseverarse aquí**: esto mira la
        // composición, y `priceLabel` sigue existiendo porque `lineRow()` la comparte con «Mis
        // pedidos», que sí la pinta. Esa mitad la vigila `LedgerSingleSourceTest`, sobre el marcado.
        $this->assertArrayNotHasKey('depositNote', $pack, 'la nota de señal ha vuelto a la tarjeta de la reserva');

        // El post-form pendiente, con la URL que compone el servidor (nunca el cliente).
        $this->assertSame('pending', $pack['guestForm']['state']);
        $this->assertSame(
            __('account.orders.guest_form_pending', ['product' => 'Cumple Jump']),
            $pack['guestForm']['label']
        );
        $this->assertStringContainsString('/datos-invitados', (string) $pack['guestForm']['url']);

        // ⚠️ El complemento SÍ se queda —dice qué llevas contratado— pero **sin precio**: es parte de
        // «qué tengo», no de «cuánto cuesta» (owner, `#130`).
        $this->assertCount(1, $pack['addons']);
        $this->assertSame('Calcetines', $pack['addons'][0]['name']);
        $this->assertSame('2 unidades', $pack['addons'][0]['quantityLabel'], 'el complemento dice QUÉ llevas, no cuánto cuesta');

        // ── La línea CANCELADA está en el historial, no aquí ───────────────────────────────────
        $historial = $this->composeInNode(
            $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/past')->assertOk()->json()
        );

        $this->assertCount(1, $historial, 'la reserva cancelada no ha caído en el historial');
        $this->assertSame('cancelled', $historial[0]['badge']['key']);
        $this->assertSame(__('account.orders.item_cancelled'), $historial[0]['badge']['label']);
        $this->assertArrayNotHasKey('depositNote', $historial[0], 'la nota de señal ha vuelto al historial');

        // ── Y el LEDGER, por el camino que la tarjeta usa de verdad: bajo demanda ──────────────
        $ledger = $this->ledgerInNode(
            $this->actingAs($user)->getJson(self::ROOT.'/orders/'.$order->code)->assertOk()->json()
        );

        // El importe de cabecera es lo que el pedido VALE hoy (`ledger.total_cents`: el pack vivo +
        // los calcetines = 98,00), no lo que se facturó al nacer (188,00, con el pack que después se
        // canceló). Hasta la T1 del libro los dos coincidían en este fixture por accidente.
        $fresh = $order->fresh(['items.slot', 'items.ticketType', 'adjustments', 'payments.refunds']);
        $this->assertSame(Money::format(OrderBook::forOrder($fresh)->totalCents), $ledger['totalLabel']);
        $this->assertSame(Money::format(9800), $ledger['totalLabel']);
        $this->assertNull($ledger['refund'], 'sin reembolso no hay bloque de reembolso');
        $this->assertSame(__('tickets.statuses.paid'), $ledger['statusLabel']);

        // ⚠️⚠️ **EL LIBRO, línea a línea** (`DECISIONES #305`, T3·1): el nacimiento (188,00, lo que se
        // facturó), la cancelación del segundo pack (−90,00, con su fecha) y el Total; el cobro real
        // (128,00 el día que se pagó) y el SALDO. Este pedido tiene un pack CANCELADO cuya señal
        // (60,00) sigue en la caja del parque y un pack vivo al que le quedan 30,00 por pagar allí:
        // el libro NETEA los dos —Total 98,00 − Pagado 128,00 = −30,00— y lo dice como lo que es,
        // «a devolver en el parque», porque hay una visita por delante (D1/D2 del owner). Hasta la
        // T3·1 la pantalla decía «pendiente de devolverte 60,00» y «a pagar en el parque 30,00» en
        // dos bloques que el cliente tenía que restar de cabeza.
        // ⚠️ Este pedido tiene DOS reservas (el pack vivo y el cancelado), así que cada línea de
        // valor lleva delante el nombre de la suya (spec §4.3); el nacimiento, que es del pedido, no.
        $f = $ledger['financials'];
        $cancelLabel = __('tickets.journal.with_reservation', [
            'reservation' => 'Cumple Jump',
            'label' => __('tickets.journal.cancel', ['name' => 'Cumple Jump', 'quantity' => __('tickets.guests_count', ['count' => 1])]),
        ]);
        $this->assertSame(
            [[__('tickets.journal.booking'), '+'.Money::format(18800)], [$cancelLabel, '−'.Money::format(9000)]],
            array_map(fn (array $m): array => [$m['label'], $m['amountLabel']], $f['movements']),
            'las líneas de valor no son las del dominio, o no llevan su signo'
        );
        $this->assertSame(DisplayTime::format($order->created_at, 'd/m/Y'), $f['movements'][0]['dateLabel'], 'cada línea lleva su fecha, compuesta por el servidor');
        $this->assertSame(Money::format(9800), $f['total']['amountLabel']);
        $this->assertSame(
            [[__('tickets.journal.paid_online'), DisplayTime::format($order->paid_at, 'd/m/Y'), '+'.Money::format(12800)]],
            array_map(fn (array $s): array => [$s['label'], $s['dateLabel'], $s['amountLabel']], $f['settlements']),
            'el cobro ha perdido su fecha o su importe: deja de ser conciliable con el extracto'
        );
        $this->assertSame(Money::format(12800), $f['paid']['amountLabel']);
        $this->assertSame('refund_at_park', $f['balance']['kind']);
        $this->assertSame(__('tickets.journal.balance_refund_at_park'), $f['balance']['label']);
        $this->assertSame(Money::format(3000), $f['balance']['amountLabel'], 'el saldo neto: 60,00 que se le deben menos 30,00 que pagará');
        $this->assertNull($f['note'], 'con todo dicho por las líneas no hay frase que añadir');
    }

    /**
     * **«MIS PEDIDOS», alimentada por la respuesta REAL de `GET /me/orders`**
     * (`specs/desglose-dinero-cliente.md` §19, `DECISIONES #129`).
     *
     * ⚠️⚠️ **Lo que esta paridad protege es que las DOS pantallas digan lo mismo del mismo dinero.**
     * El pedido que se veía desplegado dentro de una reserva y el de esta lista son el mismo, y los
     * sirve el mismo `OrderResource` por dos rutas distintas —`/orders/{code}` y `/me/orders`—. Si
     * alguna vez dejaran de componerse igual, sería la novena superficie de dinero y nadie lo notaría:
     * las dos pantallas se leen perfectamente por separado.
     */
    public function test_the_purchases_zone_says_the_same_as_the_order_it_lists(): void
    {
        [$user, $order] = $this->richOrder();

        $lista = $this->purchasesInNode(
            $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json()
        );

        $this->assertCount(1, $lista, 'la respuesta real no ha producido una fila de pedido');
        $fila = $lista[0];

        $this->assertSame($order->code, $fila['code']);
        $this->assertSame(__('tickets.statuses.paid'), $fila['statusLabel']);
        $this->assertSame(DisplayTime::format($order->created_at), $fila['createdLabel']);
        // Lo que VALE hoy (98,00), no lo facturado al nacer (188,00): ver la paridad de arriba.
        $this->assertSame(Money::format(OrderBook::forOrder($order->fresh(['items.slot', 'items.ticketType', 'adjustments', 'payments.refunds']))->totalCents), $fila['totalLabel']);

        // Las reservas del pedido, con su cantidad ya compuesta (`L2`). Son DOS: el pack vivo y la
        // línea cancelada — al revés que «Mis reservas», que las reparte en dos pantallas, aquí el
        // pedido las lleva todas porque su dinero las incluye a todas.
        $this->assertCount(2, $fila['lines'], 'el pedido no lista todas sus reservas');
        $this->assertSame(__('tickets.guests_count', ['count' => 1]), $fila['lines'][0]['quantityLabel']);

        // ⚠️⚠️ **Y el COMPLEMENTO viaja con su línea.** Omitirlo rompía la única promesa de esta
        // pantalla: medido sobre `R-UPFQAB`, las reservas ponían 120,00 € y «Valor del pedido»
        // 124,00 €, y los 4,00 € que faltaban eran unos calcetines que la API sí publica. Un
        // desglose al que le falta una línea cuadra por dentro y no cuadra para quien lo lee.
        $this->assertSame('Calcetines', $fila['lines'][0]['addons'][0]['name'] ?? null);
        $this->assertSame(Money::format(800), $fila['lines'][0]['addons'][0]['priceLabel'] ?? null);

        // ⚠️⚠️ **EL MISMO desglose que se veía dentro de la reserva, campo a campo.** No es una
        // aserción de conveniencia: es la que caza el día que alguien componga el dinero aquí.
        $porCodigo = $this->ledgerInNode(
            $this->actingAs($user)->getJson(self::ROOT.'/orders/'.$order->code)->assertOk()->json()
        );

        $this->assertSame(
            $porCodigo['financials'], $fila['financials'],
            'la lista de pedidos y el pedido suelto componen el dinero de forma distinta'
        );

        // ⚠️⚠️ **Lo VERIFICABLE llega con la lista**: el cobro con su FECHA (128,00 el día que se
        // pagó) y el saldo con su clase — el mismo libro que se ve al abrir el pedido suelto.
        $this->assertSame(__('tickets.journal.paid_online'), $fila['financials']['settlements'][0]['label']);
        $this->assertSame(DisplayTime::format($order->paid_at, 'd/m/Y'), $fila['financials']['settlements'][0]['dateLabel'], 'la fecha del cobro tiene que llegar con la lista');
        $this->assertSame('+'.Money::format(12800), $fila['financials']['settlements'][0]['amountLabel']);
        $this->assertSame(__('tickets.journal.balance_refund_at_park'), $fila['financials']['balance']['label']);
    }

    /**
     * ⚠️⚠️ **EL LIBRO EXTREMO A EXTREMO: las etiquetas y la CLASE del saldo las compone el DOMINIO y
     * la pantalla solo las transporta** (`DECISIONES #305`, T3·1; la lección de `L6`/`#133` aplicada
     * al libro).
     *
     * Una gestión deja su línea con su etiqueta —«Cancelado: Calcetines · 2 unidades»— compuesta por
     * `MovementLabel` en el idioma negociado; la pantalla no tiene diccionario propio para eso. Y la
     * clase del saldo («a devolver en el parque» frente a «pendiente de devolución») depende de si
     * habrá visita, que no está en el número: si la pantalla la dedujera del signo, este caso —una
     * clase que no casa con el signo— la delataría.
     */
    public function test_the_movement_labels_and_the_balance_kind_travel_from_the_domain_to_the_screen(): void
    {
        [$user, $order] = $this->richOrder();

        // Cancelar el complemento: una gestión más en el libro, con su propia línea.
        $addon = $order->items()->whereNotNull('parent_item_id')->first();
        $addon->forceFill(['cancelled_at' => Carbon::now()])->save();

        $respuesta = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json();
        $publicado = $respuesta['data'][0]['ledger'];

        $cancelaciones = array_values(array_filter($publicado['movements'], fn (array $m): bool => $m['kind'] === 'cancel'));
        $this->assertCount(2, $cancelaciones, 'el pack cancelado y el complemento recién cancelado');
        // Con dos reservas en el pedido, la línea lleva delante el nombre de la suya (spec §4.3).
        $this->assertContains(
            __('tickets.journal.with_reservation', [
                'reservation' => 'Cumple Jump',
                'label' => __('tickets.journal.cancel', ['name' => 'Calcetines', 'quantity' => trans_choice('tickets.units_count', 2, ['count' => 2])]),
            ]),
            array_column($cancelaciones, 'label'),
            'el dominio ha dejado de etiquetar la cancelación con su producto y su cantidad',
        );

        $fila = $this->purchasesInNode($respuesta)[0];

        $this->assertSame(
            array_column($publicado['movements'], 'label'),
            array_column($fila['financials']['movements'], 'label'),
            'la pantalla compone las etiquetas por su cuenta en vez de transportar las del servidor',
        );
        // El importe viaja con su signo. Se localiza la línea por su etiqueta y no por posición: la
        // cancelación del pack (fixture) y la del complemento (este caso) caen en el MISMO segundo y
        // el orden entre ellas lo decide el desempate del libro, que no es lo que se vigila aquí.
        $calcetines = array_values(array_filter(
            $fila['financials']['movements'],
            fn (array $m): bool => str_contains($m['label'], 'Calcetines'),
        ));
        $this->assertCount(1, $calcetines, 'la cancelación del complemento tiene que salir UNA vez');
        $this->assertSame('−'.Money::format(800), $calcetines[0]['amountLabel']);

        // ⚠️ La guarda de la guarda: **la clase manda sobre el signo**. Con el mismo importe pero la
        // clase «saldado», no hay línea de saldo — obedecer al servidor es exactamente esto.
        $respuesta['data'][0]['ledger']['balance']['kind'] = 'settled';

        $this->assertNull(
            $this->purchasesInNode($respuesta)[0]['financials']['balance'],
            'la pantalla deduce la clase del saldo por su cuenta en vez de obedecer al servidor',
        );
    }

    /**
     * ⚠️⚠️ **«Ver pedido» tiene que ABRIR LA PANTALLA EN ESE PEDIDO, y sólo el servidor sabe en qué
     * página cae** (owner, spec §5·2 · `#129`). Con 12 pedidos y 5 por página, el de la reserva más
     * antigua está en la tercera: abrir la primera no fallaría nada y **no cumpliría la decisión**.
     */
    public function test_opening_an_order_from_a_reservation_lands_on_the_page_that_holds_it(): void
    {
        [$user, $order] = $this->richOrder();

        // Once pedidos MÁS RECIENTES por delante: el rico queda el último de la lista.
        for ($i = 0; $i < 11; $i++) {
            $this->order($user, ['code' => 'R-NEW'.$i])->forceFill(['created_at' => Carbon::now()->addMinutes($i + 1)])->save();
        }

        $payload = $this->actingAs($user)
            ->getJson(self::ROOT.'/me/orders?per_page=5&containing='.$order->code)
            ->assertOk()->json();

        $this->assertSame(3, $payload['meta']['current_page'], 'el servidor no ha devuelto la página que lo contiene');
        $this->assertContains(
            $order->code, array_column($this->purchasesInNode($payload), 'code'),
            'la pantalla se abriría SIN el pedido que el cliente pulsó'
        );
    }

    /**
     * ⚠️ **Una reserva ya disfrutada**, que es donde el estado depende del RELOJ y donde el rótulo
     * cambia. Va en su propio caso porque el pedido rico lo tiene todo en futuro a propósito: mezclar
     * los dos ejes en un solo caso haría que un fallo de cualquiera se leyera como el otro.
     */
    public function test_a_past_reservation_is_marked_as_enjoyed_and_its_form_is_read_only(): void
    {
        [$user] = $this->pastOrder();

        // ⚠️ **Por el ámbito `past`, y eso ya es media aserción**: desde el 2026-08-23 una reserva
        // disfrutada no está en «Mis reservas». Pedirla por `upcoming` devolvería una lista vacía y
        // el caso reventaría con un índice — que es lo que enseñó que el reparto funciona.
        $payload = $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/past')->assertOk()->json();
        $line = $this->composeInNode($payload)[0];

        $this->assertSame('finished', $line['badge']['key']);
        $this->assertSame(__('account.orders.item_finished'), $line['badge']['label']);
        $this->assertSame('past', $line['guestForm']['state']);
        $this->assertSame(
            __('account.orders.guest_form_past', ['product' => 'Cumple Jump']),
            $line['guestForm']['label']
        );
    }

    /**
     * ⚠️ **El pedido pendiente que SÍ se puede reintentar.** El botón lo decide `can_be_retried`, que
     * es dominio: deducirlo del estado ofrecería un botón que el servidor rechazaría.
     */
    public function test_a_pending_order_offers_the_retry_the_server_authorises(): void
    {
        $user = $this->user();
        $order = $this->order($user, [
            'status' => Order::STATUS_PENDING,
            'paid_at' => null,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);
        $this->reservationIn($order, '2026-06-20');

        $payload = $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming')->assertOk()->json();
        $row = $this->composeInNode($payload)[0];

        $this->assertSame($payload['data'][0]['order']['can_be_retried'], $row['canRetry']);
        $this->assertSame(__('tickets.statuses.pending'), $row['orderStatusLabel']);
        $this->assertNotSame('', $row['orderStatusLabel'], 'un estado sin rótulo se pintaría en blanco');
    }

    /** Sin pedidos, la zona no inventa filas ni paginación. */
    public function test_an_empty_history_produces_no_rows_and_no_pagination(): void
    {
        $user = $this->user();

        $payload = $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming')->assertOk()->json();

        $this->assertSame([], $this->composeInNode($payload));
        $this->assertNull($this->paginateInNode($payload));
    }

    /**
     * ⚠️ **La paginación se compone del `meta` REAL del servidor**, no de un `meta` inventado: los
     * nombres de esos campos son contrato de Laravel y equivocarse en uno deja la barra muda.
     */
    public function test_the_pagination_matches_the_real_meta_of_the_server(): void
    {
        $user = $this->user();

        // ⚠️ **Con su RESERVA dentro, y no solo el pedido**: la pantalla pagina reservas desde el
        // 2026-08-23, así que doce pedidos vacíos darían cero filas y la barra no existiría —el caso
        // pasaría a no medir nada—. Cada uno con su día para que además el orden tenga sentido.
        for ($i = 0; $i < 12; $i++) {
            $this->reservationIn($this->order($user, ['code' => 'R-PAG'.$i]), '2026-06-'.str_pad((string) ($i + 10), 2, '0', STR_PAD_LEFT));
        }

        $payload = $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming?per_page=5')->assertOk()->json();
        $page = $this->paginateInNode($payload);

        $this->assertNotNull($page, 'con 12 pedidos y 5 por página tiene que haber barra');
        $this->assertSame(1, $page['current']);
        $this->assertSame(3, $page['last']);
        $this->assertFalse($page['canPrev']);
        $this->assertTrue($page['canNext']);
        $this->assertSame(__('account.orders.pagination.page', ['current' => 1, 'last' => 3]), $page['pageLabel']);
    }

    // ── Datos ─────────────────────────────────────────────────────────────────────────────────

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /** @param array<string, mixed> $attributes */
    private function order(User $user, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $user->id,
            'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 9800, 'tax' => 0, 'total' => 9800,
            'currency' => 'EUR', 'paid_at' => Carbon::now(),
        ], $attributes));
    }

    /** Una reserva de libro dentro de un pedido: lo mínimo para que la pantalla pinte una tarjeta. */
    private function reservationIn(Order $order, string $date): void
    {
        $zone = Zone::first() ?? $this->zone();

        $order->items()->create([
            'ticket_type_id' => TicketType::create([
                'name' => ['es' => 'Entrada '.$date], 'type' => TicketType::TYPE_ENTRY,
                'zone_id' => $zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
                'is_sellable' => true, 'is_active' => true,
                'position' => (int) TicketType::max('position') + 1,
            ])->id,
            'slot_id' => Slot::firstOrCreate(
                ['zone_id' => $zone->id, 'date' => $date, 'start_time' => '10:00:00'],
                ['end_time' => '11:00:00', 'capacity' => 20, 'online_capacity' => 20],
            )->id,
            'quantity' => 1, 'unit_price' => 9800, 'seats' => 1,
        ]);
    }

    private function zone(): Zone
    {
        return Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'accent' => 'kids',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    /** @return array{0: User, 1: Order} */
    private function richOrder(): array
    {
        $user = $this->user();
        $zone = $this->zone();
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => '2026-06-10',
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);

        // Un pack con SEÑAL: es lo que dispara el aviso «señal pagada · resto en el parque».
        $pack = TicketType::create([
            'name' => ['es' => 'Cumple Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            // La señal se modela con tipo + valor, no con un importe suelto: 6.000 de 9.000.
            'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 6000,
            // ⚠️ El post-form por invitado sale de `guest_fields`, NO de `event_fields`: aquéllos son
            // los datos de cada niño y éstos los del evento (homenajeado). Confundirlos deja
            // `guest_form_status` en `null` y el bloque entero sin pintar.
            'guest_fields' => [['key' => 'child_name', 'label' => 'Nombre del niño', 'required' => true]],
            'event_fields' => [['key' => 'name', 'label' => 'Nombre', 'required' => true]],
        ]);
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'zone_id' => $zone->id, 'seats_per_unit' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);

        // ⚠️ `Order.total` es lo que NACIÓ (T1 del libro, identidad I1): el pack (90,00) + los
        // calcetines (8,00) + el pack que después se canceló (90,00) = 188,00. Hasta la T1 el fixture
        // decía 98,00 —dejaba fuera la línea cancelada, que también se compró— y con la identidad de
        // nacimiento el desglose salía «en revisión» y la zona no pintaba ninguna fila.
        $order = $this->order($user, ['subtotal' => 18800, 'total' => 18800]);

        $line = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 9000, 'seats' => 1,
        ]);
        $order->items()->create([
            'ticket_type_id' => $addon->id, 'parent_item_id' => $line->id,
            'quantity' => 2, 'unit_price' => 400, 'seats' => 0,
        ]);

        // ⚠️ **La señal NO es un campo del pedido: es un HECHO `deposit_split` por línea.** Lo que
        // queda por cobrar en el parque se conoce desde la creación (`#225`) y vive ahí, de modo que
        // el libro (`LineFacts::onlineAtBirth`) pueda restarlo de lo cobrado online. Montarlo de otra
        // forma daría un pedido que en producción no existe, y la paridad probaría un caso imaginario.
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $line->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT,
            'amount_cents' => 3000, 'currency' => 'EUR', 'applied_by' => $user->id,
        ]);
        $cancelled = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 9000, 'seats' => 1,
            'cancelled_at' => Carbon::now(),
        ]);
        // El pack cancelado se compró como el vivo: señal de 60,00 online y 30,00 en el parque. Al
        // cancelarse, esos 60,00 cobrados ya no respaldan producto → «pendiente de devolverte».
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $cancelled->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT,
            'amount_cents' => 3000, 'currency' => 'EUR', 'applied_by' => $user->id,
        ]);

        // ⚠️⚠️ **El COBRO REAL, que este fixture no tenía** (`DECISIONES #128`). Sin fila `Payment`
        // el pedido tiene la forma exacta de los 18 `DEMO-*` sucios —`paid` sin que nadie haya
        // pagado—, que el flujo real **no puede producir** y que `PAY-17` marca como imposible. Con
        // esa forma el ancla de caja vale 0 y este caso no podía ejercitarla: un fixture irreal
        // oculta defectos tan bien como los inventa (`specs/desglose-dinero-cliente.md` §16.5).
        // 12.800 = la señal de los DOS packs (6.000 + 6.000) + los calcetines (800): lo que se cobró
        // online al nacer. ⚠️ Hasta la T1 del libro este fixture cobraba 6.800 —solo el pack vivo—
        // mientras contaba el pack cancelado como pagado: un pedido que ningún cobro produce, y que
        // escondía que a este cliente se le deben 60,00 € (la señal del pack que canceló).
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'provider' => Payment::PROVIDER_REDSYS, 'amount' => 12800, 'currency' => 'EUR',
            'status' => Payment::STATUS_PAID, 'paid_at' => Carbon::now(),
        ]);

        return [$user, $order->fresh()];
    }

    /** @return array{0: User, 1: Order} */
    private function pastOrder(): array
    {
        $user = $this->user();
        $zone = $this->zone();
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => '2026-05-01',
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);
        $pack = TicketType::create([
            'name' => ['es' => 'Cumple Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            // ⚠️ El post-form por invitado sale de `guest_fields`, NO de `event_fields`: aquéllos son
            // los datos de cada niño y éstos los del evento (homenajeado). Confundirlos deja
            // `guest_form_status` en `null` y el bloque entero sin pintar.
            'guest_fields' => [['key' => 'child_name', 'label' => 'Nombre del niño', 'required' => true]],
            'event_fields' => [['key' => 'name', 'label' => 'Nombre', 'required' => true]],
        ]);

        // `Order.total` = lo que nació: el único pack, 90,00 (identidad I1 del libro).
        $order = $this->order($user, ['subtotal' => 9000, 'total' => 9000]);
        $item = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 9000, 'seats' => 1,
        ]);
        // Ya relleno: es lo que distingue «pendiente» de «solo lectura» en una reserva pasada.
        $item->forceFill(['guest_form_completed_at' => Carbon::now()->subDay()])->save();

        return [$user, $order->fresh()];
    }

    // ── El módulo, ejecutado en Node con los diccionarios REALES ──────────────────────────────

    /**
     * Los mismos diccionarios que el servidor inyecta en el montaje, **podados igual**: si aquí se
     * pasara el grupo entero, el test pasaría con una poda rota en producción.
     *
     * @return array{messages: array<string, mixed>, account: array<string, mixed>}
     */
    private function dictionaries(): array
    {
        return [
            'messages' => __('tickets'),
            'account' => [
                'account' => ['title' => __('account.account.title')],
                'orders' => Arr::only(__('account.orders'), [
                    'title', 'subtitle', 'empty', 'pagination',
                    'item_finished', 'item_cancelled',
                    'retry_payment', 'retry_hint',
                    'guest_form_pending', 'guest_form_done',
                    'guest_form_past', 'guest_form_cancelled',
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function composeInNode(array $payload): array
    {
        return $this->runInNode(<<<'JS'
            import { cardRows } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { payload, ctx } = JSON.parse(raw);
                process.stdout.write(JSON.stringify({ out: cardRows(payload, ctx) }));
            });
            JS, ['payload' => $payload, 'ctx' => $this->dictionaries()], 'account-rows.mjs')['out'];
    }

    /**
     * El LEDGER, tal como lo compone la tarjeta al desplegar «Ver pedido»: `orderRow()` sobre la
     * respuesta de `GET /orders/{code}`, que es exactamente lo que hace `store.ensureOrder()`.
     *
     * ⚠️ Va por su propio camino y no dentro de la tarjeta porque **el ledger no viaja con la
     * lista** (`specs/mis-reservas-por-reserva.md` §4.2): es del pedido y se repetiría tantas veces
     * como reservas tenga. Comparar aquí lo que la pantalla pide de verdad es lo que impide que esta
     * paridad se quede probando un camino que ya no existe — el error de `#67`.
     *
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function ledgerInNode(array $order): array
    {
        return $this->runInNode(<<<'JS'
            import { orderRow } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { payload, ctx } = JSON.parse(raw);
                process.stdout.write(JSON.stringify({ out: orderRow(payload, ctx) }));
            });
            JS, ['payload' => $order, 'ctx' => $this->dictionaries()], 'account-ledger.mjs')['out'];
    }

    /**
     * La página de «Mis pedidos», compuesta por el módulo REAL.
     *
     * @param  array<string, mixed>  $payload  la respuesta de `GET /me/orders`
     * @return list<array<string, mixed>>
     */
    private function purchasesInNode(array $payload): array
    {
        return $this->runInNode(<<<'JS'
            import { purchaseRows } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { payload, ctx } = JSON.parse(raw);
                process.stdout.write(JSON.stringify({ out: purchaseRows(payload, ctx) }));
            });
            JS, ['payload' => $payload, 'ctx' => $this->dictionaries()], 'account-purchases.mjs')['out'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function paginateInNode(array $payload): ?array
    {
        return $this->runInNode(<<<'JS'
            import { pageInfo } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { payload, ctx } = JSON.parse(raw);
                process.stdout.write(JSON.stringify({ out: pageInfo(payload, ctx.account) }));
            });
            JS, ['payload' => $payload, 'ctx' => $this->dictionaries()], 'account-page.mjs')['out'];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function runInNode(string $script, array $input, string $filename): array
    {
        $path = base_path('storage/framework/testing/'.$filename);

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/account/orders.js'), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo «account/orders.js» falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
