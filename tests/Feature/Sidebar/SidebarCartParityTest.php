<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Http\Api\CartPayload;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.3·2 — **el pie y el carrito dicen lo mismo en los dos motores**.
 *
 * ⚠️ **Esto es lo que `SidebarDomContractTest` no puede ver, y aquí conviene decirlo entero**: su
 * normalizador descarta los nodos de texto a propósito («el contrato es la estructura, no la copia»),
 * así que un importe mal formateado, un plural sin resolver o un rótulo cambiado pasan **verdes**. Y
 * además alimenta a Vue con el view-model del SERVIDOR, de modo que tampoco vería que el cliente
 * compone otro.
 *
 * Aquí se cierra por el otro lado: el view-model que compone el CLIENTE —ejecutando sus módulos
 * planos en Node, con las respuestas REALES de la API— contra el que compone `Purchase`, campo a
 * campo. Si las dos fuentes se separan, salta con el campo exacto.
 *
 * Los tres fallos que este test existe para impedir, los tres ya medidos:
 *  - **el importe sin separador de millares** (`1000,00 €` frente a `1.000,00 €`), que en una cesta se
 *    cruza a diario;
 *  - **el rótulo del desglose**, que NO es el mismo en el paso 3 («Pagas ahora (señal)») y en la cesta
 *    («Pagas ahora», neutro porque en una cesta mixta no todo es señal, #225);
 *  - **el recuento de la barra-carrito**, que es pluralización de Laravel y no `n === 1`.
 */
class SidebarCartParityTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/api/v1';

    private Zone $zone;

    private int $rateId;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        // ⚠️ `max_guests_per_slot` no es decoración: sin él un PACK no encuentra cupo de invitados y
        // `selectTime()` deja la cantidad en 0, así que la línea nunca entra en la cesta y el caso
        // pasaría a comparar una cesta con una sola entrada barata — sin cruzar el millar.
        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true,
            'max_guests_per_slot' => 60, 'max_per_slot' => 0, 'prep_blocks_cupo' => false,
        ]);

        $this->date = now()->addDay()->toDateString();

        for ($i = 1; $i <= 5; $i++) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => now()->addDays($i)->toDateString(),
                // ⚠️ Dos horas de franja: el pack dura 120 minutos y en una franja de 60 no cabe —
                // `SlotOffer` no la ofrecería y el caso se quedaría sin pack que meter en la cesta.
                'start_time' => '10:00:00', 'end_time' => '12:00:00',
                'capacity' => 60, 'online_capacity' => 60,
            ]);
        }
    }

    /**
     * ⚠️ **El precio se elige para CRUZAR el millar**, que es donde el formateador del cliente fallaba:
     * 20 invitados × 60 € son 1.200 €. Con un pack barato el test pasaría con el fallo dentro.
     */
    private function pack(): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 20, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'deposit_type' => 'fixed', 'deposit_value' => 3000,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Homenajeado']],
            ],
        ]);
        $pack->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => 6000]);

        $cake = TicketType::create([
            'name' => ['es' => 'Tarta'], 'type' => TicketType::TYPE_ADDON, 'seats_per_unit' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $cake->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => 1000]);
        $pack->configurableAddons()->attach($cake->id, [
            'quantity_mode' => 'fixed', 'position' => 1,
            'is_included' => true, 'included_quantity' => 1, 'allow_extra' => true,
        ]);

        return $pack;
    }

    private function entry(int $priceCents = 990): TicketType
    {
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'min_qty' => 1, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 3,
        ]);
        $entry->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => $priceCents]);

        return $entry;
    }

    // ── El PIE ────────────────────────────────────────────────────────────────────────────────

    /**
     * El pie del paso 3, que es el único que se compone con el dinero de UNA línea.
     *
     * El estado se toma del componente Livewire y el dinero de la respuesta REAL del endpoint de
     * complementos, que es de donde lo saca el cajón: así lo que se compara es la composición, no de
     * dónde salen los números.
     */
    public function test_the_client_builds_the_same_step_footer_as_the_server(): void
    {
        $pack = $this->pack();

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->call('selectDate', $this->date)
            ->call('goToTime');

        // Sin hora: importe «—» y CTA inactivo.
        $this->assertFooterMatches($component, $this->stepState($component, null));

        $component->call('selectTime', '10:00:00');

        $line = $this->lineFromApi($pack, (int) $component->get('qty'));

        $this->assertGreaterThan(
            100000, $line['total_cents'],
            'el caso tiene que cruzar los 1.000 € o no prueba el separador de millares'
        );

        $this->assertFooterMatches($component, $this->stepState($component, $line));
    }

    /**
     * El pie del catálogo y el de la cesta, que se componen con los AGREGADOS del presupuesto.
     *
     * ⚠️ La cesta se llena con una entrada **y** un pack con señal a propósito: es la cesta MIXTA, y
     * es donde el rótulo neutro «Pagas ahora» tiene sentido y el del paso 3 no.
     */
    public function test_the_client_builds_the_same_cart_footer_as_the_server(): void
    {
        $component = $this->componentWithMixedCart();
        $quote = $this->quoteFromApi($component);

        $this->assertGreaterThan(
            100000, $quote['total_cents'],
            'la cesta tiene que cruzar los 1.000 € o no prueba el separador de millares'
        );
        $this->assertLessThan(
            $quote['total_cents'], $quote['online_amount_cents'],
            'sin señal no habría desglose que comparar'
        );

        foreach ([1, 4] as $step) {
            $component->set('step', $step);

            $this->assertFooterMatches($component, [
                'step' => $step,
                'messages' => __('tickets'),
                'locale' => app()->getLocale(),
                'cartCount' => count($quote['lines']),
                'cartTotalCents' => $quote['total_cents'],
                'cartOnlineCents' => $quote['online_amount_cents'],
            ]);
        }
    }

    /**
     * **La guarda del pie**: donde el servidor no emite barra, el cliente tampoco la compone.
     *
     * Con la cesta vacía `footer()` es `null` en el catálogo y en el carrito. Un cliente que
     * devolviera una barra enseñaría «0,00 €» y un «Ir a pagar» donde la web no enseña nada.
     */
    public function test_neither_engine_builds_a_footer_with_an_empty_cart(): void
    {
        foreach ([1, 4] as $step) {
            $component = Livewire::test(Purchase::class)->set('step', $step);

            $this->assertNull($component->viewData('footer'));
            $this->assertNull($this->buildFooterInNode([
                'step' => $step, 'messages' => __('tickets'), 'locale' => app()->getLocale(),
                'cartCount' => 0, 'cartTotalCents' => 0, 'cartOnlineCents' => 0,
            ]));
        }
    }

    // ── Las FILAS del carrito ─────────────────────────────────────────────────────────────────

    /**
     * Las filas que pinta el carrito, comparadas campo a campo con las del servidor.
     *
     * ⚠️ **El emparejado va por `index`, no por posición**, y el caso lo fuerza: se retira de la venta
     * un producto que está EN MEDIO de la cesta, de modo que el presupuesto devuelve una secuencia con
     * hueco. Un cliente que recorriera las dos listas en paralelo pintaría los precios de una línea
     * sobre otra, y el botón de quitar borraría la reserva equivocada.
     */
    public function test_the_client_builds_the_same_cart_rows_as_the_server(): void
    {
        $component = $this->componentWithMixedCart();

        // Un tercer producto EN MEDIO que deja de venderse: el presupuesto lo salta y su hueco en la
        // secuencia de `index` es la única señal de que existió.
        $retired = $this->entry(500);
        $cart = $component->get('cart');
        array_splice($cart, 1, 0, [[
            'ticket_type_id' => $retired->id, 'date' => $this->date, 'time' => '10:00:00',
            'qty' => 1, 'event_data' => [], 'addons' => [],
        ]]);
        // Se retira ANTES de sembrar la cesta: `viewData()` devuelve lo del ÚLTIMO render, así que
        // retirarlo después dejaría el presupuesto calculado con el producto todavía a la venta.
        $retired->update(['is_sellable' => false]);
        $component->set('cart', $cart)->set('step', 4);

        $server = $component->viewData('cartLines');

        $this->assertCount(2, $server, 'la línea retirada no se tarifica');
        $this->assertSame([0, 2], array_column($server, 'index'), 'la secuencia de índices tiene que tener un HUECO');

        $client = $this->cartRowsInNode($this->quoteFromApi($component)['lines'], $component);

        $this->assertSame(
            $this->comparableRows($server),
            $this->comparableRows($client),
            "Las filas del carrito NO coinciden entre los dos motores.\n".
            'El diff de árbol no lo ve: descarta el texto y alimenta a Vue con el view-model del servidor.'
        );
    }

    /**
     * ⚠️ **Las respuestas del pack las empareja el CLIENTE**, porque el presupuesto no las devuelve
     * (son datos de un menor y el endpoint es público) y las etiquetas viven en
     * `GET catalog/products/{id}`. El emparejado tiene que dar lo mismo que `TicketType::eventAnswers()`:
     * orden del ESQUEMA y fuera las vacías.
     */
    public function test_the_client_pairs_the_pack_answers_like_the_server(): void
    {
        $pack = $this->pack();
        $pack->update(['event_fields' => [
            ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Homenajeado']],
            ['key' => 'age', 'type' => 'number', 'required' => false, 'stage' => 'booking', 'label' => ['es' => 'Edad']],
            ['key' => 'notes', 'type' => 'textarea', 'required' => false, 'stage' => 'booking', 'label' => ['es' => 'Notas']],
        ]]);

        // La del medio SIN responder: no puede pintarse, y las otras dos conservan el orden del esquema.
        $answers = ['notes' => 'Sin frutos secos', 'celebrant' => 'Mara'];

        $server = $pack->eventAnswers($answers);
        $client = $this->eventAnswersInNode(
            array_map(fn (array $f): array => ['key' => $f['key'], 'label' => $pack->eventFieldLabel($f)], $pack->eventFields()),
            $answers
        );

        $this->assertSame(['celebrant', 'notes'], array_column($server, 'key'), 'el orden lo pone el ESQUEMA, no las respuestas');
        $this->assertSame($server, $client);
    }

    // ── El SANEADOR de la cesta persistida (Fase 4 · paso 4.3·4) ──────────────────────────────

    /**
     * El corpus de líneas con el que se comparan los dos lados.
     *
     * No son casos «raros»: son las formas que una cesta guardada en el navegador acaba teniendo —una
     * versión anterior del cajón, un `localStorage` editado a mano desde las DevTools, una migración a
     * medias—. Cada una lleva escrito qué pasaría si el cliente y el servidor no coincidieran.
     *
     * @return array<string, array<string, mixed>>
     */
    private function corpus(): array
    {
        $base = ['product_id' => 1, 'date' => '2026-09-05', 'time' => '10:00:00', 'quantity' => 2];

        return [
            'línea buena' => $base,
            'hora sin segundos' => [...$base, 'time' => '10:00'],
            'cantidad como cadena' => [...$base, 'quantity' => '3'],
            'producto como cadena' => [...$base, 'product_id' => '1'],
            // ⚠️ El caso que una expresión regular da por bueno: `date_format` reconstruye la fecha.
            'fecha que no existe' => [...$base, 'date' => '2026-02-30'],
            'fecha bisiesta válida' => [...$base, 'date' => '2028-02-29'],
            'fecha sin acolchar' => [...$base, 'date' => '2026-9-5'],
            'fecha vacía' => [...$base, 'date' => ''],
            'fecha con hora' => [...$base, 'date' => '2026-09-05T10:00'],
            'hora sin dos puntos' => [...$base, 'time' => '1000'],
            'hora imposible' => [...$base, 'time' => '25:00'],
            'hora con milésimas' => [...$base, 'time' => '10:00:00.000'],
            'cantidad cero' => [...$base, 'quantity' => 0],
            'cantidad negativa' => [...$base, 'quantity' => -1],
            'cantidad decimal' => [...$base, 'quantity' => 2.5],
            'cantidad decimal como cadena' => [...$base, 'quantity' => '3.5'],
            'cantidad con texto' => [...$base, 'quantity' => 'abc'],
            'producto cero' => [...$base, 'product_id' => 0],
            'producto nulo' => [...$base, 'product_id' => null],
            'complemento correcto' => [...$base, 'addons' => [['product_id' => 4, 'quantity' => 1]]],
            'complementos vacíos' => [...$base, 'addons' => []],
            'respuestas vacías' => [...$base, 'event_data' => []],
        ];
    }

    /**
     * ⚠️ **El ORÁCULO DIFERENCIAL: el saneador del cliente acepta lo que el servidor acepta.**
     *
     * Es la única forma de comparar dos implementaciones sin copiar la lista de reglas a mano —copiarla
     * es exactamente lo que se separa con el tiempo—. El corpus pasa por el `Validator` REAL con
     * `CartPayload::lineRules()` y por `sanitizeLine()` en Node, y se comparan los veredictos.
     *
     * Importa porque los dos errores posibles duelen de formas distintas: si el cliente es más
     * ESTRICTO, borra líneas que el servidor habría comprado; si es más LAXO, una sola línea mala hace
     * que `orders/quote` devuelva 422 sobre el cuerpo entero y el cajón se queda **sin precios, sin
     * horas y sin poder preguntar si cabe otra línea** — inservible y sin botón para quitar la culpable,
     * en un almacén que no caduca.
     */
    public function test_the_client_sanitiser_accepts_exactly_what_the_server_accepts(): void
    {
        $corpus = $this->corpus();
        $client = $this->sanitiseInNode(array_values($corpus));

        $expected = [];
        $actual = [];

        foreach (array_keys($corpus) as $i => $label) {
            $server = ! validator(['line' => $corpus[$label]], CartPayload::lineRules('line'))->fails();

            $expected[$label] = $server ? 'acepta' : 'descarta';
            $actual[$label] = $client[$i] === null ? 'descarta' : 'acepta';
        }

        $this->assertSame(
            $expected, $actual,
            'El saneador del cajón y el validador de la API NO coinciden.
'.
            'Más estricto borra líneas comprables; más laxo deja que UNA línea mala tumbe el cuerpo '.
            'entero del presupuesto con un 422 y deje el cajón inservible.'
        );
    }

    /**
     * **La vuelta del oráculo**: lo que el saneador DEVUELVE tiene que pasar el validador del servidor.
     *
     * No basta con acertar el veredicto: la línea saneada es la que se manda de verdad, y si el
     * saneador normalizara mal —una hora a la que le faltan los segundos, una cantidad que se queda en
     * cadena— el 422 llegaría igual.
     */
    public function test_what_the_client_keeps_is_accepted_by_the_server(): void
    {
        $client = $this->sanitiseInNode(array_values($this->corpus()));
        $kept = array_values(array_filter($client));

        $this->assertNotEmpty($kept, 'si el saneador lo descartara todo, este caso no probaría nada');

        foreach ($kept as $line) {
            $this->assertFalse(
                validator(['line' => $line], CartPayload::lineRules('line'))->fails(),
                'El servidor rechaza una línea que el cliente conserva: '.json_encode($line, JSON_UNESCAPED_UNICODE)
            );
        }
    }

    /**
     * **Y el inventario de campos**, para que un campo nuevo en el contrato no pase inadvertido.
     *
     * `CartPayload::lineRules()` publica sus claves; si el servidor empieza a validar un séptimo campo
     * y el saneador del cliente no lo conoce, lo tirará al restaurar sin que nadie se entere.
     */
    public function test_the_client_knows_every_field_the_server_validates(): void
    {
        $server = array_map(
            fn (string $key): string => str_replace('line.', '', $key),
            array_keys(CartPayload::lineRules('line'))
        );

        // Las reglas de los complementos van con comodín (`addons.*.…`): el campo de la línea es `addons`.
        $server = array_values(array_unique(array_map(
            fn (string $key): string => explode('.', $key)[0],
            $server
        )));

        sort($server);

        $client = $this->sanitisedFieldsInNode();
        sort($client);

        $this->assertSame(
            $server, $client,
            'El servidor valida campos de línea que el saneador del cliente no conoce.
'.
            'Los que no conoce los TIRA al restaurar, y la cesta vuelve incompleta sin decir nada.'
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>|null>
     */
    private function sanitiseInNode(array $lines): array
    {
        return $this->runInNode(<<<'JS'
            import { sanitizeLine } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify({ lines: JSON.parse(raw).map(sanitizeLine) }));
            });
            JS, $lines, 'sanitise-lines.mjs', 'cart.js')['lines'];
    }

    /** @return array<int, string> */
    private function sanitisedFieldsInNode(): array
    {
        return $this->runInNode(<<<'JS'
            import { SANITISED_FIELDS } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify({ fields: [...SANITISED_FIELDS] }));
            });
            JS, [], 'sanitised-fields.mjs', 'cart.js')['fields'];
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /** Una cesta MIXTA: una entrada que se paga entera y un pack con señal. */
    private function componentWithMixedCart(): Testable
    {
        $pack = $this->pack();
        $entry = $this->entry();

        return Livewire::test(Purchase::class)
            ->call('selectType', $entry->id)
            ->call('selectDate', $this->date)
            ->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('selectType', $pack->id)
            ->call('selectDate', $this->date)
            ->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->set('eventData', ['celebrant' => 'Mara'])
            ->call('addToCart');
    }

    /**
     * El estado del paso 3 tal y como lo tiene el cajón.
     *
     * @param  array<string, mixed>|null  $line
     * @return array<string, mixed>
     */
    private function stepState(Testable $component, ?array $line): array
    {
        return [
            'step' => 3,
            'messages' => __('tickets'),
            'locale' => app()->getLocale(),
            'hasTime' => $component->get('time') !== null,
            'lineTotalCents' => $line['total_cents'] ?? null,
            'lineHasDeposit' => $line['has_deposit'] ?? false,
            'lineDepositCents' => $line['deposit_cents'] ?? 0,
            'lineGateRemainderCents' => $line['gate_remainder_cents'] ?? 0,
        ];
    }

    /** @param array<string, mixed> $state */
    private function assertFooterMatches(Testable $component, array $state): void
    {
        $server = $component->viewData('footer');
        $client = $this->buildFooterInNode($state);

        $this->assertSame(
            $server, $client,
            "El pie del paso {$state['step']} NO coincide con el del servidor.\n".
            '⚠️ Ni el diff de árbol ni ninguna otra prueba pueden cazar esto: los importes y los '.
            'rótulos son TEXTO, y el normalizador del gate descarta los nodos de texto.'
        );
    }

    /** El pie de la línea, tal y como lo publica el endpoint que el cajón consulta. */
    private function lineFromApi(TicketType $pack, int $quantity): array
    {
        return $this->postJson(self::ROOT.'/catalog/products/'.$pack->id.'/addons', [
            'quantity' => $quantity, 'date' => $this->date, 'time' => '10:00:00',
        ])->assertOk()->json('line');
    }

    /** El presupuesto de la cesta del componente, pedido a la API como haría el cajón. */
    private function quoteFromApi(Testable $component): array
    {
        $items = array_map(fn (array $line): array => array_filter([
            'product_id' => $line['ticket_type_id'],
            'date' => $line['date'],
            'time' => $line['time'],
            'quantity' => $line['qty'],
            'addons' => array_map(fn (array $addon): array => [
                'product_id' => $addon['ticket_type_id'], 'quantity' => $addon['qty'],
            ], $line['addons'] ?? []),
        ], fn ($value) => $value !== []), $component->get('cart'));

        return $this->postJson(self::ROOT.'/orders/quote', ['items' => array_values($items)])->assertOk()->json();
    }

    /**
     * Deja las filas comparables: solo lo que se PINTA. `product_id` se excluye porque el view-model
     * de Livewire no lo lleva —su vista no lo necesita— y compararlo mediría el andamiaje.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function comparableRows(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'index' => $row['index'],
            'name' => $row['name'] ?? $row['product_name'],
            'is_pack' => $row['is_pack'],
            'date' => $row['date'],
            'time' => $row['time'],
            'quantity' => $row['qty'] ?? $row['quantity'],
            'subtotal_cents' => $row['subtotal'] ?? $row['subtotal_cents'],
            'has_deposit' => $row['has_deposit'],
            'deposit_cents' => $row['deposit'] ?? $row['deposit_cents'],
            'gate_remainder_cents' => $row['gate_remainder'] ?? $row['gate_remainder_cents'],
            'event' => $row['event'],
            'addons' => array_map(fn (array $addon): array => [
                'name' => $addon['name'] ?? $addon['product_name'],
                'quantity' => $addon['qty'] ?? $addon['quantity'],
                'free_quantity' => $addon['free_qty'] ?? $addon['free_quantity'],
                'subtotal_cents' => $addon['subtotal'] ?? $addon['subtotal_cents'],
            ], $row['addons']),
        ], $rows);
    }

    /** @param array<string, mixed> $state */
    private function buildFooterInNode(array $state): ?array
    {
        return $this->runInNode(<<<'JS'
            import { buildFooter } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify({ footer: buildFooter(JSON.parse(raw)) }));
            });
            JS, $state, 'build-footer.mjs', 'foot.js')['footer'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $quoteLines
     * @return array<int, array<string, mixed>>
     */
    private function cartRowsInNode(array $quoteLines, Testable $component): array
    {
        $cart = array_map(fn (array $line): array => ['event_data' => $line['event_data'] ?? []], $component->get('cart'));

        $fields = [];
        foreach ($component->get('cart') as $line) {
            $type = TicketType::find($line['ticket_type_id']);
            $fields[(string) $line['ticket_type_id']] = array_map(
                fn (array $field): array => ['key' => $field['key'], 'label' => $type->eventFieldLabel($field)],
                $type?->eventFields(TicketType::EVENT_STAGE_BOOKING) ?? []
            );
        }

        return $this->runInNode(<<<'JS'
            import { cartRows } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { lines, cart, fields } = JSON.parse(raw);
                process.stdout.write(JSON.stringify({ rows: cartRows(lines, cart, fields) }));
            });
            JS, ['lines' => $quoteLines, 'cart' => $cart, 'fields' => $fields], 'cart-rows.mjs', 'cart.js')['rows'];
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $fields
     * @param  array<string, mixed>  $answers
     * @return array<int, array<string, string>>
     */
    private function eventAnswersInNode(array $fields, array $answers): array
    {
        return $this->runInNode(<<<'JS'
            import { eventAnswers } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { fields, answers } = JSON.parse(raw);
                process.stdout.write(JSON.stringify({ rows: eventAnswers(fields, answers) }));
            });
            JS, ['fields' => $fields, 'answers' => $answers], 'event-answers.mjs', 'cart.js')['rows'];
    }

    /**
     * Mismo patrón que `SidebarCalendarParityTest`: script efímero que importa el módulo REAL por ruta
     * ABSOLUTA —el `import` se resuelve respecto al fichero— y habla por stdin/stdout con JSON.
     *
     * @param  array<mixed>  $input
     * @return array<mixed>
     */
    private function runInNode(string $script, array $input, string $filename, string $module): array
    {
        $path = base_path('storage/framework/testing/'.$filename);

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/'.$module), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo «{$module}» falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
