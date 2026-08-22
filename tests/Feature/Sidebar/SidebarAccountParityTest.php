<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
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

        $payload = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json();
        $rows = $this->composeInNode($payload);

        $this->assertCount(1, $rows, 'la respuesta real no ha producido una fila');
        $row = $rows[0];

        $this->assertSame($order->code, $row['code']);
        $this->assertSame(__('tickets.statuses.paid'), $row['statusLabel'], 'el rótulo del estado no sale del diccionario de la web');
        $this->assertSame(DisplayTime::format($order->created_at), $row['createdLabel']);
        $this->assertSame(Money::format((int) $order->total), $row['totalLabel']);
        $this->assertNull($row['refund'], 'sin reembolso no hay bloque de reembolso');

        // ── La línea del pack ──────────────────────────────────────────────────────────────────
        $pack = $row['lines'][0];

        $this->assertSame(
            DisplayTime::dayLabel('2026-06-10').' · 10:00–11:00', $pack['whenLabel'],
            'el día y la ventana no coinciden con lo que compone el servidor'
        );
        $this->assertSame(Money::format(9000), $pack['priceLabel']);
        $this->assertNull($pack['badge'], 'una reserva futura y viva no lleva distintivo');

        // El aviso de señal, con sus DOS importes: lo decide el servidor y aquí solo se comprueba
        // que los números que se pintan son los que él publica.
        //
        // ⚠️ **6.800 y no 6.000, y este caso lo enseñó**: `paid_online_cents` es lo pagado online **de
        // esta reserva, principal MÁS sus complementos** —el contrato lo dice, y esta paridad lo
        // confirmó contra la respuesta real—. La señal del pack son 6.000 y los calcetines 800; en el
        // aviso van juntos porque el cliente pagó los dos por adelantado. Recomponer el número aquí
        // habría dado 6.000 y nadie lo habría notado.
        $this->assertSame(
            __('tickets.deposit_card_note', ['deposit' => Money::format(6800), 'rest' => Money::format(3000)]),
            $pack['depositNote']
        );

        // El post-form pendiente, con la URL que compone el servidor (nunca el cliente).
        $this->assertSame('pending', $pack['guestForm']['state']);
        $this->assertSame(
            __('account.orders.guest_form_pending', ['product' => 'Cumple Jump']),
            $pack['guestForm']['label']
        );
        $this->assertStringContainsString('/datos-invitados', (string) $pack['guestForm']['url']);

        // El complemento va anidado con su propio importe.
        $this->assertCount(1, $pack['addons']);
        $this->assertSame('Calcetines', $pack['addons'][0]['name']);
        $this->assertSame(Money::format(800), $pack['addons'][0]['priceLabel']);

        // ── La línea cancelada ─────────────────────────────────────────────────────────────────
        $cancelled = $row['lines'][1];

        $this->assertSame('cancelled', $cancelled['badge']['key']);
        $this->assertSame(__('account.orders.item_cancelled'), $cancelled['badge']['label']);
        $this->assertNull($cancelled['depositNote'], 'una línea cancelada no debe nada en puerta');
    }

    /**
     * ⚠️ **Una reserva ya disfrutada**, que es donde el estado depende del RELOJ y donde el rótulo
     * cambia. Va en su propio caso porque el pedido rico lo tiene todo en futuro a propósito: mezclar
     * los dos ejes en un solo caso haría que un fallo de cualquiera se leyera como el otro.
     */
    public function test_a_past_reservation_is_marked_as_enjoyed_and_its_form_is_read_only(): void
    {
        [$user] = $this->pastOrder();

        $payload = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json();
        $line = $this->composeInNode($payload)[0]['lines'][0];

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

        $payload = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json();
        $row = $this->composeInNode($payload)[0];

        $this->assertSame($payload['data'][0]['can_be_retried'], $row['canRetry']);
        $this->assertSame(__('tickets.statuses.pending'), $row['statusLabel']);
        $this->assertNotSame('', $row['statusLabel'], 'un estado sin rótulo se pintaría en blanco');
    }

    /** Sin pedidos, la zona no inventa filas ni paginación. */
    public function test_an_empty_history_produces_no_rows_and_no_pagination(): void
    {
        $user = $this->user();

        $payload = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json();

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

        for ($i = 0; $i < 12; $i++) {
            $this->order($user, ['code' => 'R-PAG'.$i]);
        }

        $payload = $this->actingAs($user)->getJson(self::ROOT.'/me/orders?per_page=5')->assertOk()->json();
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

        $order = $this->order($user);

        $line = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 9000, 'seats' => 1,
        ]);
        $order->items()->create([
            'ticket_type_id' => $addon->id, 'parent_item_id' => $line->id,
            'quantity' => 2, 'unit_price' => 400, 'seats' => 0,
        ]);

        // ⚠️ **La señal NO es un campo del pedido: es un AJUSTE `deposit_remainder` por línea.** Lo
        // que queda por cobrar en puerta se conoce desde la creación (`#225`) y vive ahí, de modo que
        // `ReservationFinancials` pueda restarlo de lo cobrado online. Montarlo de otra forma daría
        // un pedido que en producción no existe, y la paridad probaría un caso imaginario.
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $line->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
            'amount_cents' => 3000, 'currency' => 'EUR', 'applied_by' => $user->id,
        ]);
        $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 9000, 'seats' => 1,
            'cancelled_at' => Carbon::now(),
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

        $order = $this->order($user);
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
            import { orderRows } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { payload, ctx } = JSON.parse(raw);
                process.stdout.write(JSON.stringify({ out: orderRows(payload, ctx) }));
            });
            JS, ['payload' => $payload, 'ctx' => $this->dictionaries()], 'account-rows.mjs')['out'];
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
