<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Http\Api\CartPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.3·2, re-apuntado en 4.7·2b·2 — **el pie y el carrito dicen lo que dicen el
 * DICCIONARIO y `number_format`** (`DECISIONES #80`).
 *
 * ⚠️ **Esto es lo que `SidebarDomContractTest` no puede ver, y aquí conviene decirlo entero**: su
 * normalizador descarta los nodos de texto a propósito («el contrato es la estructura, no la copia»),
 * así que un importe mal formateado, un plural sin resolver o un rótulo cambiado pasan **verdes**.
 *
 * ### Qué comparaba antes y por qué la referencia cambia
 *
 * Comparaba el pie y las filas contra el view-model de `Purchase`. Pero ese view-model **no era una
 * fuente**: lo ensamblaba con `__()` y `number_format`, o sea que era un **intermediario** de las dos
 * cosas que sobreviven. Se compara contra ellas directamente, que es la misma re-apuntada de `#75(b)`.
 *
 * ⚠️ **Y está MEDIDO que hace falta**: renombrar `footer_pay_now` en `lang/es/tickets.php` deja los
 * 2715 casos verdes **salvo uno**, y es el del pie de la cesta. `foot.test.js` no puede verlo —su
 * diccionario es fabricado— y `SidebarTextParityTest` no lleva esas claves.
 *
 * Los tres fallos que este test existe para impedir, los tres ya medidos:
 *  - **el importe sin separador de millares** (`1000,00 €` frente a `1.000,00 €`), que en una cesta se
 *    cruza a diario;
 *  - **el rótulo del desglose**, que NO es el mismo en el paso 3 («Pagas ahora (señal)») y en la cesta
 *    («Pagas ahora», neutro porque en una cesta mixta no todo es señal, #225);
 *  - **el recuento de la barra-carrito**, que es pluralización de Laravel y no `n === 1`.
 *
 * ⚠️ **Lo que este test NO es**: no fija la ESTRUCTURA del pie —eso es `foot.test.js`, y el árbol lo
 * ejecuta desde `#71`— sino sus textos e importes, que son lo único que el gate no puede mirar.
 */
class SidebarCartParityTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/api/v1';

    /** El marcador de «todavía no hay importe» del pie (`foot.js`), que NO es «0,00 €». */
    private const AMOUNT_PLACEHOLDER = '—';

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
     * El dinero sale de la respuesta REAL del endpoint de complementos, que es de donde lo saca el
     * cajón: así lo que se compara son los textos, no de dónde salen los números.
     */
    public function test_the_step_footer_is_the_dictionary_and_number_format(): void
    {
        $pack = $this->pack();

        // Sin hora: importe «—» y CTA inactivo. Es el único estado en que el pie no lleva importe,
        // así que el marcador tiene que ser el del módulo y no un importe de cero.
        $this->assertFooterTexts($this->stepState(hasTime: false, line: null), [
            'cta' => __('tickets.add_to_cart'),
            'label' => __('tickets.total'),
            'amount' => self::AMOUNT_PLACEHOLDER,
            'note' => __('tickets.iva_note'),
        ]);

        $line = $this->lineFromApi($pack, $pack->min_qty);

        $this->assertGreaterThan(
            100000, $line['total_cents'],
            'el caso tiene que cruzar los 1.000 € o no prueba el separador de millares'
        );
        $this->assertTrue($line['has_deposit'], 'sin señal no habría desglose que comparar en el paso 3');

        $this->assertFooterTexts($this->stepState(hasTime: true, line: $line), [
            'cta' => __('tickets.add_to_cart'),
            'label' => __('tickets.total'),
            'amount' => $this->money($line['total_cents']),
            // ⚠️ El rótulo CON «(señal)»: aquí se sabe que todo lo que se cobra ahora lo es. En la
            // cesta no, y por eso el de abajo es otro.
            'nowLabel' => __('tickets.footer_pay_now_deposit'),
            'now' => $this->money($line['deposit_cents']),
            'park' => $this->money($line['gate_remainder_cents']),
            'note' => __('tickets.iva_note'),
        ]);
    }

    /**
     * El pie del catálogo y el de la cesta, que se componen con los AGREGADOS del presupuesto.
     *
     * ⚠️ La cesta se llena con una entrada **y** un pack con señal a propósito: es la cesta MIXTA, y
     * es donde el rótulo neutro «Pagas ahora» tiene sentido y el del paso 3 no.
     */
    public function test_the_cart_footer_is_the_dictionary_and_number_format(): void
    {
        $cart = $this->mixedCart();
        $quote = $this->quoteFromApi($cart);
        $count = count($quote['lines']);

        $this->assertGreaterThan(
            100000, $quote['total_cents'],
            'la cesta tiene que cruzar los 1.000 € o no prueba el separador de millares'
        );
        $this->assertLessThan(
            $quote['total_cents'], $quote['online_amount_cents'],
            'sin señal no habría desglose que comparar'
        );

        foreach ([1, 4] as $step) {
            $state = [
                'step' => $step,
                'messages' => __('tickets'),
                'locale' => app()->getLocale(),
                'cartCount' => $count,
                'cartTotalCents' => $quote['total_cents'],
                'cartOnlineCents' => $quote['online_amount_cents'],
            ];

            // La barra del catálogo ancla en el RECUENTO —pluralizado por Laravel— y la del carrito
            // en el rótulo «Total». Son dos composiciones distintas del mismo dinero.
            $expected = $step === 1
                ? [
                    'cta' => __('tickets.go_to_cart'),
                    'label' => trans_choice('tickets.cart_items', $count, ['count' => $count]),
                    'amount' => $this->money($quote['total_cents']),
                ]
                : [
                    'cta' => __('tickets.go_to_pay'),
                    'label' => __('tickets.total'),
                    'amount' => $this->money($quote['total_cents']),
                    // ⚠️ Rótulo NEUTRO, sin «(señal)»: en una cesta mixta lo que se cobra ahora no es
                    // solo señal (#225). Es el otro rótulo del paso 3, y confundirlos es silencioso.
                    'nowLabel' => __('tickets.footer_pay_now'),
                    'now' => $this->money($quote['online_amount_cents']),
                    'park' => $this->money($quote['total_cents'] - $quote['online_amount_cents']),
                    'note' => __('tickets.iva_note'),
                ];

            $this->assertFooterTexts($state, $expected);
        }
    }

    /**
     * **La guarda del pie**: con la cesta vacía no hay barra en el catálogo ni en el carrito.
     *
     * Un cliente que devolviera una barra enseñaría «0,00 €» y un «Ir a pagar» sobre una cesta que no
     * existe. La mitad que lo comprobaba sobre `Purchase::footer()` se va con el componente; esta
     * afirma la conducta directamente, que es lo que hay que conservar.
     */
    public function test_no_footer_is_built_with_an_empty_cart(): void
    {
        foreach ([1, 4] as $step) {
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
    /**
     * ⚠️ **La línea que el presupuesto SALTA, y el hueco que deja.**
     *
     * Un producto que deja de venderse con la cesta ya guardada no se tarifica, así que el
     * presupuesto devuelve menos líneas de las que hay — y el `index` de cada fila es lo ÚNICO que
     * dice a qué línea de la cesta corresponde. Emparejar por POSICIÓN pintaría las respuestas del
     * pack en la fila equivocada.
     *
     * La referencia ya no es `viewData('cartLines')` sino el presupuesto REAL, que es de donde
     * `Purchase` sacaba lo mismo. El caso conserva lo que el diff de árbol no puede ver: sus fixtures
     * tienen **una** línea, y con una línea emparejar por posición sale verde (`#70`).
     */
    public function test_the_client_pairs_each_row_with_its_own_cart_line(): void
    {
        $cart = $this->mixedCart();

        // Un tercer producto EN MEDIO que deja de venderse: el presupuesto lo salta y su hueco en la
        // secuencia de `index` es la única señal de que existió.
        $retired = $this->entry(500);
        array_splice($cart, 1, 0, [[
            'ticket_type_id' => $retired->id, 'date' => $this->date, 'time' => '10:00:00',
            'qty' => 1, 'event_data' => [], 'addons' => [],
        ]]);
        $retired->update(['is_sellable' => false]);

        $lines = $this->quoteFromApi($cart)['lines'];

        $this->assertCount(2, $lines, 'la línea retirada no se tarifica');

        $client = $this->cartRowsInNode($lines, $cart);

        $this->assertSame([0, 2], array_column($client, 'index'),
            'la secuencia de índices tiene que tener un HUECO: es lo que ata cada fila a su línea');

        // Y lo que se pinta de cada fila es lo que publica el presupuesto, sin recomponer importes.
        $this->assertSame(
            $this->paintedFields($lines),
            $this->paintedFields($client),
            "Las filas del carrito NO dicen lo que dice el presupuesto.\n".
            '`cartRows()` recoloca esos campos, no los calcula: una diferencia aquí es un importe '.
            'compuesto en el cliente, que es la segunda aritmética que `PAY-12` prohíbe.'
        );

        // Y el hueco no es decorativo: la fila del pack tiene que llevar SUS respuestas, no las de la
        // línea que ocupa esa posición.
        $pack = collect($client)->firstWhere('is_pack', true);
        $this->assertSame(2, $pack['index'], 'el pack es la TERCERA línea de la cesta');
        $this->assertSame(['Mara'], array_column($pack['event'], 'value'),
            'las respuestas tienen que ser las de la línea 2, no las de la que va en su posición');
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
            // Fase 6 · menores a cargo, tanda 4: los ids de los menores, con las reglas de FORMA del
            // servidor (`integer|min:1|distinct`). Que no haya más que unidades NO es forma: lo decide
            // `DependentAssigner`, así que el saneador tampoco lo mira.
            'menores asignados' => [...$base, 'dependent_ids' => [12, 7]],
            'menores como cadena numérica' => [...$base, 'dependent_ids' => ['12']],
            'menores vacíos' => [...$base, 'dependent_ids' => []],
            'menor con texto' => [...$base, 'dependent_ids' => ['x']],
            'menor cero' => [...$base, 'dependent_ids' => [0]],
            'menor repetido' => [...$base, 'dependent_ids' => [7, 7]],
            'menores que no son lista' => [...$base, 'dependent_ids' => 7],
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

    /**
     * Una cesta MIXTA: una entrada que se paga entera y un pack con señal.
     *
     * Se compone directamente en la forma que el cajón persiste, sin conducir ningún motor: lo que
     * este fichero prueba es qué se PINTA con una cesta dada, no cómo se llena.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mixedCart(): array
    {
        $entry = $this->entry();
        $pack = $this->pack();

        return [
            [
                'ticket_type_id' => $entry->id, 'date' => $this->date, 'time' => '10:00:00',
                'qty' => 1, 'event_data' => [], 'addons' => [],
            ],
            [
                'ticket_type_id' => $pack->id, 'date' => $this->date, 'time' => '10:00:00',
                'qty' => $pack->min_qty, 'event_data' => ['celebrant' => 'Mara'], 'addons' => [],
            ],
        ];
    }

    /**
     * El estado del paso 3 tal y como lo tiene el cajón.
     *
     * @param  array<string, mixed>|null  $line
     * @return array<string, mixed>
     */
    private function stepState(bool $hasTime, ?array $line): array
    {
        return [
            'step' => 3,
            'messages' => __('tickets'),
            'locale' => app()->getLocale(),
            'hasTime' => $hasTime,
            'lineTotalCents' => $line['total_cents'] ?? null,
            'lineHasDeposit' => $line['has_deposit'] ?? false,
            'lineDepositCents' => $line['deposit_cents'] ?? 0,
            'lineGateRemainderCents' => $line['gate_remainder_cents'] ?? 0,
        ];
    }

    /**
     * Un importe como lo escribe el servidor. **Es la referencia que sobrevive**: `number_format` con
     * coma decimal, punto de millares y el euro detrás, que es lo que pinta el sitio entero.
     */
    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }

    /**
     * Comprueba los TEXTOS del pie que compone el cliente contra el diccionario y `number_format`.
     *
     * Solo los textos y los importes: la estructura la fija `foot.test.js` y el árbol la ejecuta
     * desde `#71`. Lo que ninguno de los dos puede mirar es lo que aquí se compara, porque el
     * normalizador del gate descarta los nodos de texto.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, string>  $expected
     */
    private function assertFooterTexts(array $state, array $expected): void
    {
        $footer = $this->buildFooterInNode($state);

        $this->assertIsArray($footer, "el paso {$state['step']} tiene que componer pie con esta cesta");

        $flat = $footer + ($footer['split'] ?? []);

        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $flat, "el pie del paso {$state['step']} no lleva «{$key}»");
            $this->assertSame(
                $value, $flat[$key],
                "El «{$key}» del pie del paso {$state['step']} NO es el del diccionario/`number_format`.\n".
                '⚠️ Ni el diff de árbol ni ninguna otra prueba pueden cazar esto: los importes y los '.
                'rótulos son TEXTO, y el normalizador del gate descarta los nodos de texto.'
            );
        }
    }

    /** El pie de la línea, tal y como lo publica el endpoint que el cajón consulta. */
    private function lineFromApi(TicketType $pack, int $quantity): array
    {
        return $this->postJson(self::ROOT.'/catalog/products/'.$pack->id.'/addons', [
            'quantity' => $quantity, 'date' => $this->date, 'time' => '10:00:00',
        ])->assertOk()->json('line');
    }

    /** El presupuesto de la cesta del componente, pedido a la API como haría el cajón. */
    private function quoteFromApi(array $cart): array
    {
        $items = array_map(fn (array $line): array => array_filter([
            'product_id' => $line['ticket_type_id'],
            'date' => $line['date'],
            'time' => $line['time'],
            'quantity' => $line['qty'],
            'addons' => array_map(fn (array $addon): array => [
                'product_id' => $addon['ticket_type_id'], 'quantity' => $addon['qty'],
            ], $line['addons'] ?? []),
        ], fn ($value) => $value !== []), $cart);

        return $this->postJson(self::ROOT.'/orders/quote', ['items' => array_values($items)])->assertOk()->json();
    }

    /**
     * Lo que se PINTA de cada fila, en la forma en que lo publica el PRESUPUESTO.
     *
     * Sirve para los dos lados —las filas del cliente y las líneas del presupuesto— porque el cliente
     * no reescribe estos campos: los recoloca. Ahí está la comprobación: si `cartRows()` empezara a
     * componer un importe por su cuenta, dejaría de coincidir con la fuente de la que sale.
     *
     * `product_id` se excluye a propósito: no se pinta, y compararlo mediría el andamiaje.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function paintedFields(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'subtotal_cents' => $row['subtotal_cents'],
            'has_deposit' => $row['has_deposit'],
            'deposit_cents' => $row['deposit_cents'],
            'gate_remainder_cents' => $row['gate_remainder_cents'],
            'addons' => array_map(fn (array $addon): array => [
                'product_name' => $addon['product_name'],
                'quantity' => $addon['quantity'],
                'free_quantity' => $addon['free_quantity'],
                'subtotal_cents' => $addon['subtotal_cents'],
            ], $row['addons']),
        ], array_values($rows));
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
    private function cartRowsInNode(array $quoteLines, array $cartLines): array
    {
        $cart = array_map(fn (array $line): array => ['event_data' => $line['event_data'] ?? []], $cartLines);

        $fields = [];
        foreach ($cartLines as $line) {
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
