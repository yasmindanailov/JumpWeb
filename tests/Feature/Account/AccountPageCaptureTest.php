<?php

namespace Tests\Feature\Account;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * # ⏳ TEST TEMPORAL — MUERE CON LA PÁGINA `/mi-cuenta/pedidos`
 *
 * **Fecha de caducidad: la tanda 3 del área de cliente**, cuando esa página se retire
 * (`DECISIONES #120(c)`). Este fichero **se borra entero** ese día; no hay que migrarlo ni adaptarlo.
 * Si sigue aquí y la página ya no existe, es que alguien se lo saltó.
 *
 * ## Para qué existe
 *
 * `DECISIONES #111` dejó la regla escrita con sangre: **independizar el contrato ANTES de borrar**.
 * La página «Mis reservas» lleva años acumulando cosas que solo ella sabe, y el día que se borre se
 * llevará por delante la única referencia de lo que enseñaba. Este test **inventaría** lo que pinta y
 * lo clasifica en tres:
 *
 *  1. lo que la API **ya publica** → se asevera equivalencia, para que no se pierda antes del borrado;
 *  2. lo que la API **NO publica** → queda declarado como **HUECO CON NOMBRE**, y la lista **solo
 *     puede encoger**: publicar uno obliga a quitarlo de aquí en el mismo commit;
 *  3. lo que está fuera **a propósito** → declarado aparte, para que nadie lo confunda con un hueco.
 *
 * ⚠️⚠️ **La lista de huecos es la condición de entrada EJECUTABLE de la retirada**: mientras no esté
 * vacía, borrar la página pierde información del cliente. No es una nota en un documento que alguien
 * tiene que acordarse de leer; es un test que enumera exactamente qué falta.
 *
 * ⚠️ **Cómo se sondea un hueco, y por qué no por nombre de campo.** Comprobar «no existe la clave
 * `gate_lines`» sería frágil: mañana alguien la publica como `breakdown` y el test seguiría verde
 * mintiendo. Se sondea por **VALOR**: se monta un pedido cuyos importes son únicos y se comprueba que
 * el número que la página pinta **no aparece en ninguna parte** de la respuesta. Si aparece, es que
 * alguien lo publicó —da igual con qué nombre— y hay que actualizar la lista.
 */
class AccountPageCaptureTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/api/v1';

    /**
     * **Los HUECOS**: lo que la página enseña y la API no publica. Solo puede encoger.
     *
     * La clave es el helper del dominio que lo produce; el valor, qué se pierde si se borra la página
     * sin publicarlo.
     *
     * @var array<string, string>
     */
    private const GAPS = [
        'Order::depositRemainderPendingByProduct()' => 'El resto de la señal DESGLOSADO POR PRODUCTO («Resto de la señal de Cumpleaños Jump: '.
            '+30,00 €»). La API publica el agregado `pending_at_gate_cents` y el aviso por línea, '.
            'pero no esta lista. Con dos productos con señal, el cliente hoy ve dos líneas y en el '.
            'cajón vería un único número sin saber de qué es.',

        'Order::pendingAtGateLines()' => 'El desglose de «a cobrar en el parque» línea a línea, con su ETIQUETA (los cambios de '.
            'producto y los extras añadidos en gestión). La API publica el total, no de qué se compone.',

        'OrderFinancialSummary::totalFinalNeto()' => 'El «Total» final tras los cambios, que NO es `total_cents` en cuanto hay una '.
            'cancelación o un cambio: `total` es inmutable y esto es lo que el cliente acaba pagando.',

        'OrderFinancialSummary::pendienteDevolucion()' => 'Lo que se le debe al cliente y todavía no se ha reembolsado, con su explicación. Es '.
            'distinto de `refund`, que es lo ya devuelto: aquí el dinero aún no ha salido.',
    ];

    /**
     * **Huecos que NO se pueden sondear por valor**, y por qué.
     *
     * ⚠️ Se declaran aparte en vez de fingir que están cubiertos: una sonda que no mide nada es peor
     * que no tenerla (`DECISIONES #115`). A éstos los vigila la congelación del contrato —si el
     * esquema `Order` crece, hay que volver aquí—, que es una red más floja y se dice.
     *
     * @var array<string, string>
     */
    private const WITHOUT_VALUE_PROBE = [
        'OrderFinancialSummary::pendienteDevolucion()' => 'Ejercitarlo exige un reembolso a medio procesar —una cancelación con dinero cobrado '.
            'online y el abono sin completar—, que no se puede montar con un fixture honesto sin '.
            'reproducir medio flujo de gestión. Con cualquier pedido normal vale 0, y sondear con 0 '.
            'daría verde contra cualquier respuesta.',
    ];

    /**
     * **Fuera a propósito**, que NO es lo mismo que un hueco.
     *
     * @var array<string, string>
     */
    private const BY_DESIGN = [
        'OrderItem::event_data' => 'Las respuestas del evento (nombre del homenajeado, alergias del menor — art. 9). '.
            '`me/orders` NO las lleva a propósito y tiene test de ello: viajarían en cada página de '.
            'una lista paginada. Se piden aparte con `GET orders/{code}/event-data`, que es un acto '.
            'deliberado del cliente. Publicarlas aquí sería una regresión de privacidad, no un avance.',
    ];

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
     * **Lo que los DOS publican dice lo mismo.** Protege de que la API deje de publicar algo antes de
     * que la página muera — que dejaría al cajón sin referencia y sin aviso.
     */
    public function test_what_both_publish_agrees(): void
    {
        [$user, $order] = $this->orderWithEverything();

        $json = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0');
        $summary = $order->financialSummary();

        $this->assertSame((int) $order->total, $json['total_cents'], 'el importe facturado');
        $this->assertSame($order->onlineDueCents(), $json['online_amount_cents'], 'la señal pagada online');
        $this->assertSame($summary->pendingAtGate(), $json['pending_at_gate_cents'], 'el agregado de puerta');
        $this->assertSame($order->canBeRetried(), $json['can_be_retried'], 'si se puede reintentar');
        $this->assertSame((int) ($order->refund_amount_cents ?? 0), $json['refund']['amount_cents'], 'lo reembolsado');
    }

    /**
     * **Cada hueco declarado SIGUE siendo un hueco.**
     *
     * ⚠️ Si este caso se pone rojo, **la noticia es buena**: alguien ha publicado el dato. Lo que hay
     * que hacer es quitar su entrada de `GAPS` en ese mismo commit — no relajar el test.
     */
    #[DataProvider('gapProvider')]
    public function test_the_declared_gaps_are_still_missing_from_the_api(string $source, string $what): void
    {
        [$user, $order] = $this->orderWithEverything();

        $body = (string) $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->getContent();

        if (isset(self::WITHOUT_VALUE_PROBE[$source])) {
            // Declarado sin sonda de valor: lo vigila la congelación del contrato, más abajo.
            $this->assertNotSame('', self::WITHOUT_VALUE_PROBE[$source]);

            return;
        }

        foreach ($this->probesFor($source, $order) as $probe) {
            $this->assertStringNotContainsString(
                (string) $probe, $body,
                "«{$source}» YA se publica en la API (se encontró «{$probe}»).\n\n".
                "▶ Eso es una BUENA noticia: quita su entrada de `GAPS` en este mismo commit, para que\n".
                "la lista siga diciendo la verdad. Lo que describe es:\n{$what}"
            );
        }
    }

    /**
     * **La guarda de la guarda**: las sondas de verdad miden algo.
     *
     * ⚠️ Sin esto, una sonda que devolviera una lista vacía —porque el fixture dejó de producir ese
     * dato— dejaría el caso de arriba pasando solo y para siempre, y la lista de huecos parecería
     * verificada sin serlo. Es lo que le pasó al contador de `PurchaseRetirementTest` en `#63`.
     */
    public function test_every_probe_actually_measures_something(): void
    {
        [, $order] = $this->orderWithEverything();

        foreach (array_keys(self::GAPS) as $source) {
            if (isset(self::WITHOUT_VALUE_PROBE[$source])) {
                continue;
            }

            $probes = $this->probesFor($source, $order);

            $this->assertNotEmpty($probes, "«{$source}» no produce ninguna sonda: el fixture ya no lo ejercita");

            foreach ($probes as $probe) {
                $this->assertNotSame('', (string) $probe, "«{$source}» produce una sonda vacía");
                $this->assertNotSame('0', (string) $probe, "«{$source}» sondea con 0: cualquier respuesta lo contiene");
            }
        }
    }

    /**
     * **Y la página SIGUE enseñándolos**: un hueco solo importa mientras alguien lo esté enseñando.
     *
     * ⚠️ Si la página deja de pintar uno, deja de ser un hueco —no hay nada que perder— y su entrada
     * sobra. Sin este caso, la lista podría envejecer describiendo cosas que ya nadie ve.
     */
    public function test_the_page_still_paints_what_the_gaps_describe(): void
    {
        [$user, $order] = $this->orderWithEverything();

        $html = (string) $this->actingAs($user)->get('/mi-cuenta/pedidos')->assertOk()->getContent();

        foreach (array_keys(self::GAPS) as $source) {
            if (isset(self::WITHOUT_VALUE_PROBE[$source])) {
                continue;
            }

            foreach ($this->probesFor($source, $order) as $probe) {
                // ⚠️ **La página pinta importes FORMATEADOS y la API céntimos**, así que el mismo dato
                // se sondea de dos formas. Buscar céntimos en el HTML habría dado un rojo que se lee
                // como «la página ya no lo pinta» cuando en realidad lo pinta como «31,00 €».
                $onPage = Money::format((int) $probe);

                $this->assertStringContainsString(
                    $onPage, $html,
                    "«{$source}» ya no se pinta en la página (falta «{$onPage}»). Si de verdad se ha ".
                    'retirado, quita su entrada de `GAPS`: dejó de haber algo que perder.'
                );
            }
        }
    }

    /**
     * **El contrato de `Order` está CONGELADO mientras queden huecos.**
     *
     * ⚠️⚠️ Ésta es la red que cubre lo que las sondas por valor no alcanzan —empezando por
     * `pendienteDevolucion()`, que no se puede ejercitar con un fixture honesto—. El razonamiento es
     * indirecto pero sólido: **publicar un dato obliga a declararlo en `openapi/v1.yaml`**, porque el
     * contrato manda sobre el código y `ApiContractTest` exige que todo campo del esquema esté en
     * `required`. Así que si el esquema crece, o bien se ha cerrado un hueco —y hay que quitarlo de la
     * lista— o bien se ha añadido algo que nadie ha cruzado con ella.
     *
     * ▶ Cuando este caso se ponga rojo, la pregunta no es «¿cómo lo arreglo?» sino **«¿el campo nuevo
     * cierra alguno de los huecos?»**. Si sí, quítalo de `GAPS`; si no, añade su nombre aquí.
     */
    public function test_the_order_contract_has_not_grown_without_revisiting_the_gaps(): void
    {
        $schema = $this->orderSchemaProperties();

        $this->assertSame(
            [
                'code', 'status', 'currency', 'total_cents', 'online_amount_cents',
                'pending_at_gate_cents', 'refund', 'can_be_retried',
                'created_at', 'created_label', 'paid_at', 'expires_at', 'items', 'guest_form_pending',
            ],
            $schema,
            "El esquema `Order` de `openapi/v1.yaml` ha cambiado.\n\n".
            "▶ Antes de tocar esta lista, cruza el campo nuevo con `GAPS`: si cierra uno de los huecos\n".
            "—el desglose de puerta, el resto de señal por producto, el total final o el pendiente de\n".
            'devolución—, quita esa entrada. Ese es el trabajo que este fichero existe para provocar.'
        );
    }

    /** Cada entrada, de las dos listas, va con su porqué. Una lista de nombres sueltos no informa. */
    public function test_every_entry_carries_its_reason(): void
    {
        $this->assertNotSame([], self::GAPS, 'Si ya no quedan huecos, BORRA este fichero: su trabajo terminó.');

        foreach ([self::GAPS, self::BY_DESIGN, self::WITHOUT_VALUE_PROBE] as $list) {
            foreach ($list as $source => $why) {
                $this->assertGreaterThan(80, mb_strlen($why), "«{$source}» no explica qué se perdería");
            }
        }
    }

    /**
     * Los campos que `openapi/v1.yaml` declara para un pedido, en su orden.
     *
     * Se leen del CONTRATO y no del `Resource`: el contrato es quien manda (`CLAUDE.md`), y un
     * `Resource` que publicara algo sin declararlo ya lo rechaza `ApiContractTest`.
     *
     * @return list<string>
     */
    private function orderSchemaProperties(): array
    {
        // `Symfony\Component\Yaml`, como `ApiContractTest`: la extensión `yaml` de PHP no está
        // instalada en este contenedor y depender de ella haría el test frágil por el entorno.
        $spec = Yaml::parseFile(base_path('openapi/v1.yaml'));

        $this->assertIsArray($spec, 'no se ha podido leer el contrato');

        return array_keys($spec['components']['schemas']['Order']['properties'] ?? []);
    }

    /** @return iterable<string, array{string, string}> */
    public static function gapProvider(): iterable
    {
        foreach (self::GAPS as $source => $why) {
            yield $source => [$source, $why];
        }
    }

    /**
     * Los valores con los que se sondea cada hueco en la respuesta.
     *
     * ⚠️ **Importes en CÉNTIMOS y textos tal cual**: la API publica céntimos enteros, así que un
     * importe que ella publicara aparecería con ese número exacto. Los valores del fixture están
     * elegidos para no coincidir entre sí (ver {@see orderWithEverything}).
     *
     * @return list<string|int>
     */
    private function probesFor(string $source, Order $order): array
    {
        $summary = $order->financialSummary();

        return match ($source) {
            'Order::depositRemainderPendingByProduct()' => array_map(
                fn (array $line): string => $line['name'].'|'.$line['amount'],
                $order->depositRemainderPendingByProduct(),
            ) === [] ? [] : array_column($order->depositRemainderPendingByProduct(), 'amount'),

            'Order::pendingAtGateLines()' => array_column($order->pendingAtGateLines(), 'amount'),

            'OrderFinancialSummary::totalFinalNeto()' => [$summary->totalFinalNeto()],

            default => [],
        };
    }

    /**
     * Un pedido que ejercita **los cuatro huecos a la vez**, con importes que no se repiten.
     *
     * ⚠️ **Los números están elegidos para ser únicos**, y no es manía: la sonda busca el valor en el
     * cuerpo de la respuesta, así que dos importes iguales harían que un hueco pareciera publicado
     * porque otro campo lleva el mismo número.
     *
     * @return array{0: User, 1: Order}
     */
    private function orderWithEverything(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'accent' => 'kids',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => '2026-06-20',
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);
        $pack = TicketType::create([
            'name' => ['es' => 'Cumple Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 6000,
            'guest_fields' => [['key' => 'child_name', 'label' => 'Nombre', 'required' => true]],
        ]);

        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-CAPTURE',
            'status' => Order::STATUS_PAID,
            'subtotal' => 13300, 'tax' => 0, 'total' => 13300,
            'currency' => 'EUR', 'paid_at' => Carbon::now(),
        ]);

        $line = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 9100, 'seats' => 1,
        ]);

        // ⚠️ **Una línea CANCELADA, y no es adorno**: sin ella `totalFinalNeto()` coincide con
        // `total_cents` —que la API sí publica— y su sonda daría un falso «ya está publicado». Es
        // además el caso donde el hueco importa: `total` es inmutable, y lo que el cliente acaba
        // pagando tras una cancelación solo lo dice ese helper.
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada suelta'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $order->items()->create([
            'ticket_type_id' => $entry->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 2300, 'seats' => 1,
            'cancelled_at' => Carbon::now(),
        ]);

        // ⚠️ **Y una SEGUNDA línea viva, porque con una sola la sonda no distingue.** `totalFinalNeto()`
        // es la suma de las líneas que quedan; con una sola coincide con su `charged_subtotal_cents`
        // —que la API sí publica— y el test daba un falso «ya está publicado». Con dos, el total no
        // coincide con ningún importe individual y la sonda vuelve a medir lo que dice medir.
        $order->items()->create([
            'ticket_type_id' => $entry->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 1900, 'seats' => 1,
        ]);

        // El resto de la señal (hueco 1) y un extra añadido en gestión (hueco 2). Importes distintos
        // entre sí y distintos del total, para que las sondas no se confundan.
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $line->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
            'amount_cents' => 3100, 'currency' => 'EUR', 'applied_by' => $user->id,
        ]);
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $line->id,
            'type' => OrderAdjustment::TYPE_EXTRA_DUE,
            'amount_cents' => 1700, 'currency' => 'EUR', 'applied_by' => $user->id,
            'reason' => 'Extra de gestión',
        ]);

        return [$user, $order->fresh()->load('items.ticketType', 'items.children', 'items.slot', 'adjustments')];
    }
}
