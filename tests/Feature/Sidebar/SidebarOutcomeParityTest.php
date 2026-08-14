<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.6·1 — **el resumen de la reserva creada dice lo mismo salga de donde salga**.
 *
 * ⚠️ **Este test existe porque el diff de árbol NO puede verificar nada de esto**, y ya mordió una vez
 * en 4.2·3: el componente recibe las filas YA compuestas, así que el gate compara el mismo árbol
 * tanto si los números son los del pedido como si son de otro. El fallo que este paso podía repetir es
 * más feo que aquél —el marcado usa `product_name`/`charged_subtotal_cents` y el view-model de
 * Livewire dice `name`/`subtotal`—: **diff verde y cajón real con filas vacías**.
 *
 * Y hay un fallo peor todavía, que solo se ve aquí: las respuestas del pack viajan en un endpoint
 * APARTE —son datos de un menor y del art. 9, y `GET orders/{code}` no las lleva— y se emparejan por
 * `reservation_id`. Emparejarlas por posición pinta el nombre de un niño bajo la reserva de otro con
 * un árbol idéntico.
 *
 * **Dos divergencias DECLARADAS**, cada una con su caso para que no puedan cambiar sin que nadie lo
 * decida: la fase de las respuestas (§4.4.6) y el estado efectivo del pedido.
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
     * **Campo a campo, sobre el pedido REAL de una compra REAL.**
     *
     * El pedido lo crea el flujo de compra de la web —con su señal, sus respuestas del pack y su
     * complemento con unidades incluidas— y después se leen las dos fuentes: el view-model del
     * componente y lo que `outcome.js` compone con las respuestas de la API.
     */
    public function test_the_confirmed_summary_says_the_same_from_both_sources(): void
    {
        [$component, $user, $code] = $this->purchase();
        Order::where('code', $code)->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        // ⚠️ El `$refresh` no es ceremonia: el view-model se compone al RENDERIZAR, así que sin él se
        // compararía la foto de antes del cobro contra la de después —y la nota de señal es una de las
        // tres condiciones que dependen justo de eso—.
        $server = $this->summaryFromLivewire($component->call('$refresh'));
        $client = $this->summaryFromApi($user, $code);

        $this->assertSame(
            $server, $client,
            "El resumen de la reserva creada NO dice lo mismo en los dos motores.\n".
            '⚠️ El diff de árbol da esto por bueno: recibe las filas ya compuestas, así que unas filas '.
            "con los números de otro pedido pintan exactamente el mismo árbol.\n".
            'Server: '.json_encode($server, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n".
            'Client: '.json_encode($client, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /**
     * ⚠️ **Las respuestas del pack van bajo SU reserva, y esto no se puede probar con una sola línea.**
     *
     * `GET orders/{code}/event-data` no repite ni el nombre del producto ni la fecha —a propósito: no
     * duplica lo que el otro endpoint ya publica—, así que la única llave es `reservation_id`. Con dos
     * packs en el mismo pedido, emparejar por posición es un cruce silencioso: el árbol es idéntico y
     * lo que cambia es el nombre del niño que se enseña.
     */
    public function test_each_pack_answer_lands_under_its_own_reservation(): void
    {
        [$component, $user, $code] = $this->purchase(second: true);

        $server = $this->summaryFromLivewire($component);
        $client = $this->summaryFromApi($user, $code);

        $names = array_map(fn (array $line): array => array_column($line['event'], 'value'), $client['lines']);

        $this->assertSame([['Mara'], ['Nil']], $names, 'las dos reservas tienen que traer SU respuesta');
        $this->assertSame(
            array_column($server['lines'], 'event'),
            array_column($client['lines'], 'event'),
            "Las respuestas del pack NO caen bajo la misma reserva en los dos motores.\n".
            '⚠️ La llave es `reservation_id`, no la posición.'
        );
    }

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
        [, $user, $code] = $this->purchase(second: true);

        $straight = $this->summaryFromApi($user, $code);
        $reversed = $this->summaryFromApi($user, $code, reverseAnswers: true);

        $this->assertSame(
            $straight, $reversed,
            "El resumen CAMBIA si las respuestas llegan en otro orden.\n".
            '⚠️ Eso es emparejar por posición: el nombre de un niño acabaría bajo la reserva de otro, '.
            'con el árbol intacto y el gate en verde.'
        );
    }

    /**
     * ⚠️ **El enlace de «registro del parque»: su `href` es INVISIBLE para el diff de árbol** —igual
     * que pasó con el WhatsApp del aviso de pausa—, así que un motor que mandara a otro sitio pasaría
     * el gate en verde. Y no es un enlace cualquiera: lo edita un operador y viaja SANEADO por el
     * servidor (`SEC-07`), porque un cliente JSON no tiene escape de plantilla que remate la defensa.
     */
    public function test_the_registration_link_is_the_same_in_both_engines(): void
    {
        Setting::updateOrCreate(['key' => 'registration.url'], ['value' => 'https://registro.example.test/alta', 'group' => 'business']);
        Setting::flushMemo();

        [$component, $user] = $this->purchase();

        $server = $component->viewData('registration');
        $client = $this->actingAs($user)
            ->getJson('/api/v1/config', ['Origin' => config('app.url')])
            ->assertOk()
            ->json('registration');

        $this->assertNotNull($server, 'el caso necesita el enlace configurado');
        $this->assertSame(
            $server, $client,
            "El enlace de registro NO es el mismo en los dos motores.\n".
            '⚠️ `href` no es atributo de contrato: el diff de árbol da por bueno un botón que lleve a '.
            'otro sitio.'
        );
    }

    /** Y sin URL configurada, los dos motores no ofrecen nada — que es lo normal en una instalación. */
    public function test_neither_engine_invents_a_registration_link(): void
    {
        [$component, $user] = $this->purchase();

        $this->assertNull($component->viewData('registration'));
        $this->assertNull(
            $this->actingAs($user)->getJson('/api/v1/config', ['Origin' => config('app.url')])->json('registration')
        );
    }

    // ── Las dos divergencias DECLARADAS ───────────────────────────────────────────────────────

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
        [$component, $user, $code] = $this->purchase(withGuestStage: true);

        // Lo que un operador rellenaría después, desde el panel: misma columna, otra fase.
        $item = Order::where('code', $code)->firstOrFail()->items()->whereNull('parent_item_id')->firstOrFail();
        $item->update(['event_data' => array_merge((array) $item->event_data, ['guest_name' => 'Ada'])]);

        $server = $this->summaryFromLivewire($component->call('$refresh'));
        $client = $this->summaryFromApi($user, $code);

        $this->assertSame(
            ['Mara', 'Ada'], array_column($server['lines'][0]['event'], 'value'),
            'el Blade pinta las respuestas de las DOS fases'
        );
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
        [$component, $user, $code] = $this->purchase();
        Order::where('code', $code)->update(['expires_at' => now()->subMinute()]);

        $server = $this->summaryFromLivewire($component->call('$refresh'));
        $client = $this->summaryFromApi($user, $code);

        $this->assertSame(Order::STATUS_PENDING, $server['status'], 'el Blade lee la columna');
        $this->assertSame(
            Order::STATUS_EXPIRED, $client['status'],
            "La API ha dejado de publicar el estado EFECTIVO del pedido.\n".
            '⚠️ Es lo que hace que el cajón no prometa «pendiente de pago» sobre una reserva cuya plaza '.
            'ya volvió al inventario.'
        );
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /**
     * Una compra REAL por el flujo de la web, dejada en la pantalla de reserva creada.
     *
     * @return array{0: Testable, 1: User, 2: string}
     */
    private function purchase(bool $second = false, bool $withGuestStage = false): array
    {
        $pack = $this->pack('Cumpleaños', $withGuestStage);
        $cake = $this->addon($pack, 'Tarta', 1000);

        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->call('selectDate', $this->date)
            ->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->set('eventData', ['celebrant' => 'Mara'])
            ->call('incAddon', $cake->id)
            ->call('addToCart');

        if ($second) {
            // Un SEGUNDO pack en el mismo pedido: es lo único que puede destapar un emparejado por
            // posición, y con una línea sola el test pasaría con el fallo dentro.
            $other = $this->pack('Aniversario');
            $component
                ->call('selectType', $other->id)
                ->call('selectDate', $this->date)
                ->call('goToTime')
                ->call('selectTime', '10:00:00')
                ->set('eventData', ['celebrant' => 'Nil'])
                ->call('addToCart');
        }

        $component->call('checkout')->call('confirmReservation');

        $code = (string) $component->get('orderCode');
        $this->assertNotSame('', $code, 'el caso tiene que haber creado el pedido');

        return [$component->set('step', 6), $user, $code];
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
     * El view-model del componente, traducido a la forma que compone `outcome.js`.
     *
     * ⚠️ **La traducción es el test**: si los nombres de los campos no se cruzaran aquí, no habría nada
     * que comparar — y es justo el cruce que el cajón hace de verdad.
     *
     * @return array<string, mixed>
     */
    private function summaryFromLivewire(Testable $component): array
    {
        $confirmation = $component->viewData('confirmation');

        $this->assertNotNull($confirmation, 'el componente tiene que traer su resumen');

        return [
            'code' => $confirmation['code'],
            'status' => $confirmation['status'],
            'total_cents' => $confirmation['total'],
            'online_cents' => $confirmation['online'],
            'park_cents' => $confirmation['pending_at_park'],
            'has_guest_form' => (bool) $confirmation['has_guest_form'],
            'lines' => array_map(fn (array $line): array => [
                'product_name' => $line['name'],
                'is_pack' => $line['is_pack'],
                'quantity' => $line['qty'],
                'date' => $line['date'],
                'time' => $line['time'],
                'subtotal_cents' => $line['subtotal'],
                'has_deposit' => (bool) $line['has_deposit'],
                'deposit_cents' => $line['deposit'],
                'gate_remainder_cents' => $line['gate_remainder'],
                'addons' => array_map(fn (array $addon): array => [
                    'product_name' => $addon['name'],
                    'quantity' => $addon['qty'],
                    'free_quantity' => $addon['free_qty'],
                    'subtotal_cents' => $addon['subtotal'],
                ], $line['addons']),
                'event' => $line['event'],
            ], $confirmation['lines']),
        ];
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
