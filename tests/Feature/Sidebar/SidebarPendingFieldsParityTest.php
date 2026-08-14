<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.5·1 — **el cajón pide exactamente lo que el servidor va a exigir**.
 *
 * La cesta persistida vuelve **sin `event_data`** (`DECISIONES #38(d)`: en la instalación sembrada son
 * el nombre de un menor, su edad y sus alergias, y dejarlos en el navegador los pone fuera del alcance
 * de `User::anonymize()`). Así que una línea de pack restaurada está incompleta **por construcción** —
 * y aquí está el problema que este paso cierra: **`POST /orders/quote` la tarifica igual**, con su
 * total correcto, de modo que el cliente ve una cesta perfecta y el rechazo aparece al final del
 * embudo, en `POST /orders`, con un 422 que no puede arreglar desde ninguna pantalla.
 *
 * Lo que compara este test es la FRONTERA entre las dos mitades:
 *  - el cliente **enumera** qué campos faltan (presentación: qué controles pinta);
 *  - el servidor **decide** si una respuesta vale (`#38(f)`, y no es ceremonia: `sanitizeEventData()`
 *    aplica `preg_replace('/\D+/','')` a los `number`, así que una edad contestada «cinco» el servidor
 *    la ve VACÍA y cualquier validación ingenua del cliente la ve contestada).
 *
 * Si el servidor cambiara qué campos exige —otra fase, otro criterio de obligatoriedad—, el cajón
 * pediría un juego distinto y el cliente volvería a chocarse con el 422. Eso es lo que se fija aquí.
 */
class SidebarPendingFieldsParityTest extends TestCase
{
    use RefreshDatabase;

    private TicketType $pack;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump', 'color' => '#FF5B22',
            'position' => 1, 'is_active' => true,
        ]);

        $this->date = now()->addDay()->toDateString();

        Slot::create([
            'zone_id' => $zone->id, 'date' => $this->date,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 30, 'online_capacity' => 30,
        ]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 60, 'min_qty' => 6, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            // El esquema sembrado de verdad: un obligatorio de `booking`, dos opcionales, y uno de
            // POST-FORM que el cajón **no** debe pedir nunca.
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Nombre del homenajeado/a']],
                ['key' => 'age', 'type' => 'number', 'required' => false, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Edad que cumple']],
                ['key' => 'notes', 'type' => 'textarea', 'required' => false, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Notas (alergias…)']],
                ['key' => 'adults_approx', 'type' => 'number', 'required' => true, 'stage' => TicketType::EVENT_STAGE_POSTFORM, 'label' => ['es' => 'Nº de adultos']],
            ],
        ]);
        $this->pack->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 5000]);
    }

    /** @return array<string, mixed> */
    private function items(): array
    {
        return ['items' => [[
            'product_id' => $this->pack->id, 'date' => $this->date, 'time' => '10:00:00', 'quantity' => 6,
        ]]];
    }

    // ── El problema que el paso cierra ────────────────────────────────────────────────────────

    /**
     * ⚠️ **El presupuesto NO avisa, y por eso hacía falta la guarda del cliente.**
     *
     * Una línea sin respuestas se tarifica con normalidad —200, con su total— y solo `POST /orders` la
     * rechaza. Si esto dejara de ser cierto (por ejemplo, si el presupuesto empezara a validarlas), la
     * guarda del cajón sobraría y habría que revisarla: por eso el hecho se fija aquí.
     */
    public function test_the_quote_does_not_warn_but_the_order_rejects(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/orders/quote', $this->items(), ['Origin' => config('app.url')])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/api/v1/orders', $this->items(), ['Origin' => config('app.url')])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'line_event_required');
    }

    // ── La frontera: enumerar (cliente) vs decidir (servidor) ─────────────────────────────────

    /**
     * ⚠️ **Los campos que el cajón pide son EXACTAMENTE los que el servidor echa en falta.**
     *
     * Se comparan las dos listas para una línea sin ninguna respuesta: la del dominio
     * (`missingRequiredEventFields`, fase `booking`) y la que compone el módulo del cliente con el
     * esquema que publica `GET catalog/products/{id}`. Si divergen, el cliente pide un juego de datos
     * y el servidor exige otro — que es volver al 422 por otro camino.
     */
    public function test_the_client_asks_for_exactly_what_the_server_demands(): void
    {
        $server = array_values($this->pack->missingRequiredEventFields([], TicketType::EVENT_STAGE_BOOKING));

        $client = array_column($this->pendingInNode([]), 'key');

        $this->assertNotSame([], $server, 'el pack tiene que exigir algo, o el caso no compara nada');
        $this->assertSame(
            $server, $client,
            "El cajón NO pide los mismos campos que el servidor exige.\n".
            '⚠️ Pedir de menos deja que el cliente llegue al pago y se choque con un 422; pedir de más '.
            'le exige datos que nadie va a mirar.'
        );
    }

    /**
     * ⚠️ **Los campos de POST-FORM no se piden en la compra, y el caso lo prueba con uno OBLIGATORIO.**
     *
     * `adults_approx` es `required` pero de fase `postform`: se rellena semanas después, con la firma
     * del correo. Un cajón que mirara `required` sin mirar la fase bloquearía la compra pidiendo un
     * dato que el checkout no exige — y el esquema que publica el catálogo ya viene filtrado, así que
     * lo que este caso vigila es que siga viniendo así.
     */
    public function test_post_form_fields_are_never_asked_at_checkout(): void
    {
        $keys = array_column($this->pendingInNode([]), 'key');

        $this->assertNotContains(
            'adults_approx', $keys,
            'el cajón está pidiendo un campo de POST-FORM en la compra: el checkout no lo exige'
        );
        $this->assertNotContains(
            'adults_approx',
            $this->pack->missingRequiredEventFields([], TicketType::EVENT_STAGE_BOOKING),
            'y el servidor tampoco lo exige al crear el pedido: son la misma frontera'
        );
    }

    /**
     * Con el campo contestado, ni uno ni otro piden nada — y el pedido se crea. Es la mitad que hace
     * significativa a la otra: sin ella, un cliente que pidiera SIEMPRE todo pasaría los dos casos
     * anteriores.
     */
    public function test_once_answered_neither_side_asks_and_the_order_goes_through(): void
    {
        $this->assertSame([], $this->pendingInNode(['celebrant' => 'Mara']));
        $this->assertSame([], $this->pack->missingRequiredEventFields(['celebrant' => 'Mara'], TicketType::EVENT_STAGE_BOOKING));

        $items = $this->items();
        $items['items'][0]['event_data'] = ['celebrant' => 'Mara'];

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/orders', $items, ['Origin' => config('app.url')])
            ->assertCreated();
    }

    /**
     * ⚠️ **La frontera medida: «cinco» en un campo numérico.**
     *
     * El cliente lo ve contestado —hay algo escrito— y el SERVIDOR lo ve vacío, porque
     * `sanitizeEventData()` deja solo los dígitos. Es el ejemplo con el que `#38(f)` justificó que la
     * validación fuera un endpoint y no una copia en el cliente, y aquí queda como hecho ejecutable:
     * el cajón no puede saberlo, así que el «no» tiene que venir del servidor.
     */
    public function test_the_client_cannot_know_what_the_server_will_discard(): void
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Solo edad'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->pack->zone_id,
            'duration_min' => 60, 'min_qty' => 6, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
            'event_fields' => [
                ['key' => 'age', 'type' => 'number', 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Edad']],
            ],
        ]);

        $schema = [['key' => 'age', 'label' => 'Edad', 'required' => true, 'type' => 'number']];

        $this->assertSame(
            [], $this->pendingInNode(['age' => 'cinco'], $schema),
            'el cliente ve el campo contestado: hay algo escrito'
        );
        $this->assertSame(
            ['age'], array_values($pack->missingRequiredEventFields(['age' => 'cinco'], TicketType::EVENT_STAGE_BOOKING)),
            'y el servidor lo ve VACÍO, porque el saneo se queda solo con los dígitos'
        );
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /**
     * Lo que el cajón pediría: el esquema REAL que publica el catálogo, pasado por el módulo REAL.
     *
     * @param  array<string, mixed>  $answers
     * @param  array<int, array<string, mixed>>|null  $schema  para forzar un esquema en un caso concreto
     * @return array<int, array<string, mixed>>
     */
    private function pendingInNode(array $answers, ?array $schema = null): array
    {
        $fields = $schema ?? $this->schemaFromApi();

        $script = <<<'JS'
            import { pendingEventFields } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { fields, answers } = JSON.parse(raw);
                process.stdout.write(JSON.stringify(pendingEventFields(fields, answers)));
            });
            JS;

        $path = base_path('storage/framework/testing/pending-fields.mjs');

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/cart.js'), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode(['fields' => $fields, 'answers' => $answers], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo de cesta falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * El esquema tal y como lo recibe el cajón: de `GET catalog/products/{id}`, no escrito a mano.
     *
     * @return array<int, array<string, mixed>>
     */
    private function schemaFromApi(): array
    {
        return $this->getJson('/api/v1/catalog/products/'.$this->pack->id)
            ->assertOk()
            ->json('event_fields');
    }
}
