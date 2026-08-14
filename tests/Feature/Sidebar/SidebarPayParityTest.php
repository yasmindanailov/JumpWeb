<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ReservationErrorMap;
use App\Http\Middleware\SetLocale;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.5·2 — **el formulario que sale hacia la pasarela, y los «no» del checkout**.
 *
 * ⚠️ **Este test existe porque el diff de árbol NO puede verificar el paso 9.** Su normalizador
 * conserva `role`, `type`, `disabled` y los `aria-*`; **`action`, `method` y los `name` de los campos
 * no son atributos de contrato**. Es decir: un motor que emitiera el formulario con los campos
 * vacíos, con otro nombre o apuntando a otro sitio **pasaría el gate en verde**, y el cobro moriría
 * con SIS0042 —firma inválida— con el pedido ya creado y el aforo retenido. Es el mismo agujero que
 * obligó a escribir la paridad de enlaces del aviso de pausa, con mucho más dinero delante.
 *
 * La otra mitad son los códigos: `POST /orders` publica **doce** motivos de rechazo del checkout más
 * los de admisión, y el cajón tiene que traducirlos a los MISMOS textos que pinta el componente
 * Livewire. Aquí se recorre el enum entero del servidor: un código que nadie mapee lo nombra el test
 * en vez de salir como un aviso vacío en la pantalla donde el cliente esperaba pagar.
 */
class SidebarPayParityTest extends TestCase
{
    use RefreshDatabase;

    private TicketType $entry;

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

        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'min_qty' => 1, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 990]);
    }

    /** @return array<string, mixed> */
    private function items(int $quantity = 2): array
    {
        return ['items' => [[
            'product_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'quantity' => $quantity,
        ]]];
    }

    // ── El formulario firmado ─────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **El cajón emite EXACTAMENTE los campos que le da el servidor, sin tocar uno solo.**
     *
     * La firma cubre esos valores exactos: renombrar, reordenar, filtrar o normalizar cualquiera de
     * ellos es un cobro rechazado. Se comprueba contra la respuesta REAL de `POST /orders` pasada por
     * el módulo REAL — y se comparan los valores, no solo las claves.
     */
    public function test_the_client_emits_the_signed_fields_untouched(): void
    {
        $payment = $this->createOrderByApi()->json('payment');

        $form = $this->gatewayFormInNode($payment);

        $this->assertNotNull($form, 'el formulario tiene que componerse: sin él no hay pago');
        $this->assertSame($payment['url'], $form['url'], 'el destino es el que firma el servidor, no otro');
        $this->assertSame($payment['method'], $form['method'], 'el verbo lo publica el contrato');

        $this->assertSame(
            $payment['fields'],
            array_column($form['fields'], 'value', 'name'),
            "El cajón NO emite los campos firmados tal y como llegan.\n".
            '⚠️ La firma cubre esos valores exactos: cualquier cambio es un SIS0042 con el pedido ya '.
            'creado y el aforo retenido. Y el diff de árbol no puede verlo: los `name` no son '.
            'atributos de contrato.'
        );
    }

    /**
     * **Y son los mismos que emite el Blade.** Los nombres salen del mismo `PaymentTicket` en los dos
     * motores, así que esto no puede fallar por casualidad — pero si alguien «normalizara» los campos
     * en una de las dos superficies, aquí se vería.
     */
    public function test_both_engines_post_the_same_field_names_to_the_same_gateway(): void
    {
        $payment = $this->createOrderByApi()->json('payment');

        $component = $this->componentAtPayment()->call('confirmReservation');
        $blade = (array) $component->get('redsysFormData');

        $this->assertSame(
            ['Ds_SignatureVersion', 'Ds_MerchantParameters', 'Ds_Signature'],
            array_keys($payment['fields']),
            'los campos que publica la API han cambiado: revisa el driver y este test a la vez'
        );

        $this->assertSame(
            $blade['gatewayUrl'], $payment['url'],
            'los dos motores tienen que mandar el pago al MISMO sitio'
        );
    }

    /**
     * ⚠️ **Un formulario a medias no se pinta**, y esto no es paranoia: el pedido ya existe y retiene
     * aforo cuando llega esta respuesta, así que un `<form>` sin destino o sin campos sería un POST a
     * ninguna parte con el cliente creyendo que está pagando. Se degrada al mismo aviso que el 502 del
     * puerto de pasarela, que es lo que ha ocurrido desde su punto de vista.
     */
    public function test_a_half_built_gateway_form_is_not_painted(): void
    {
        $this->assertNull($this->gatewayFormInNode(['url' => '', 'method' => 'POST', 'fields' => ['a' => 'b']]));
        $this->assertNull($this->gatewayFormInNode(['url' => 'https://x.test', 'method' => 'POST', 'fields' => []]));
        $this->assertNull($this->gatewayFormInNode(['url' => 'https://x.test', 'method' => 'POST']));
        $this->assertNull($this->gatewayFormInNode(null));
    }

    // ── Los «no» del checkout ─────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **El mapa del cliente cubre el enum ENTERO del servidor.**
     *
     * `ReservationErrorMap` traduce cada `ReservationException` del dominio a un código estable; el
     * cajón hace el camino inverso, del código a la clave del diccionario. Si el servidor añade un
     * motivo de rechazo y nadie lo mapea aquí, el cliente pintaría el aviso genérico en la pantalla
     * donde el cliente esperaba pagar — o, peor, uno vacío. Este caso lo nombra.
     */
    public function test_the_client_maps_every_checkout_error_the_server_can_send(): void
    {
        // `knownKeys()` devuelve las CLAVES del diccionario que el mapa cubre; el código público de
        // cada una lo da `CODES`, que es la constante que el propio test de exhaustividad del servidor
        // usa. Se lee por reflexión para no duplicar aquí una tabla que ya existe.
        $codes = array_map(
            fn (ApiErrorCode $code): string => $code->value,
            array_values((new \ReflectionClass(ReservationErrorMap::class))->getConstant('CODES')),
        );

        $this->assertNotSame([], $codes, 'el servidor tiene que publicar códigos, o este test no mira nada');

        $mapped = $this->errorKeysInNode();

        foreach ($codes as $code) {
            $this->assertArrayHasKey(
                $code, $mapped,
                "El cajón no sabe traducir «{$code}», que `POST /orders` puede devolver.\n".
                '⚠️ Sin mapa, el cliente enseña el aviso genérico en la pantalla de pagar y el motivo '.
                'real se pierde.'
            );
        }
    }

    /**
     * Y los textos coinciden con los del componente Livewire, **en los tres idiomas y con sus
     * parámetros interpolados**.
     *
     * Se provoca un rechazo REAL —una franja que se agota entre que se pinta y se confirma— para que
     * los `params` (`product`, `when`) los ponga el dominio y no el test.
     */
    public function test_the_rejection_text_matches_the_server_in_every_locale(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            // El aforo se agota tras pintar el carrito: es el rechazo más común de todos.
            $component = $this->componentAtPayment();
            Slot::query()->update(['online_capacity' => 0]);

            $component->call('confirmReservation');

            $server = (string) $component->errors()->first('cart');

            $texts = __('tickets');
            $response = $this->actingAs(User::factory()->create())
                ->postJson('/api/v1/orders', $this->items(), ['Origin' => config('app.url')]);
            $this->app->setLocale($locale);

            $client = $this->confirmErrorInNode([
                'ok' => false,
                'status' => $response->getStatusCode(),
                'error' => $response->json('error'),
            ], $texts);

            $this->assertNotSame('', $server, "el servidor tiene que rechazar en «{$locale}»");
            $this->assertSame(
                $server,
                $client['error'],
                "El aviso del rechazo en «{$locale}» NO dice lo mismo en los dos motores.\n".
                '⚠️ Los `params` (`:product`, `:when`) los pone el dominio; el cliente solo los '.
                'interpola en la misma clave.'
            );

            Slot::query()->update(['online_capacity' => 30]);
        }
    }

    /**
     * ⚠️ **La pausa no compone mensaje: pide releer el estado**, igual que en el paso al pago
     * (4.4a·1). Es además lo que el contrato pedía tras un 409 `reservations_paused` y que quedaba
     * como último residual del aviso de mantenimiento.
     */
    public function test_a_paused_installation_asks_to_reread_instead_of_writing_a_message(): void
    {
        $verdict = $this->confirmErrorInNode([
            'ok' => false, 'status' => 409, 'error' => ['code' => 'reservations_paused', 'message' => 'x'],
        ], __('tickets'));

        $this->assertSame('', $verdict['error'], 'el cartel de mantenimiento habla por él');
        $this->assertTrue($verdict['rereadStatus'], 'y hay que releer el estado para poder pintarlo');
    }

    /** Un código desconocido o un corte de red no dejan la pantalla de pagar muda. */
    public function test_an_unknown_failure_still_says_something(): void
    {
        foreach ([
            ['ok' => false, 'status' => 409, 'error' => ['code' => 'algo_nuevo']],
            ['ok' => false, 'status' => 500, 'error' => null],
            ['ok' => false, 'status' => 0, 'error' => null],
        ] as $response) {
            $this->assertSame(
                __('tickets.errors.try_later'),
                $this->confirmErrorInNode($response, __('tickets'))['error'],
            );
        }
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    private function createOrderByApi(): TestResponse
    {
        return $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/orders', $this->items(), ['Origin' => config('app.url')])
            ->assertCreated();
    }

    /** Un componente Livewire con la cesta puesta y en el paso de pago. */
    private function componentAtPayment(): Testable
    {
        $this->actingAs(User::factory()->create());

        return Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('selectDate', $this->date)
            ->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('checkout');
    }

    /**
     * @param  array<string, mixed>|null  $payment
     * @return array<string, mixed>|null
     */
    private function gatewayFormInNode(?array $payment): ?array
    {
        return $this->runInNode(<<<'JS'
            import { gatewayForm } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify({ out: gatewayForm(JSON.parse(raw).payment) }));
            });
            JS, ['payment' => $payment])['out'];
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $messages
     * @return array{error: string, rereadStatus: bool}
     */
    private function confirmErrorInNode(array $response, array $messages): array
    {
        return $this->runInNode(<<<'JS'
            import { confirmError } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { response, messages } = JSON.parse(raw);
                process.stdout.write(JSON.stringify({ out: confirmError(response, messages) }));
            });
            JS, ['response' => $response, 'messages' => $messages])['out'];
    }

    /** @return array<string, string> */
    private function errorKeysInNode(): array
    {
        return $this->runInNode(<<<'JS'
            import { ERROR_KEYS } from 'file://__MODULE__';
            process.stdin.on('data', () => {});
            process.stdin.on('end', () => process.stdout.write(JSON.stringify({ out: ERROR_KEYS })));
            JS, [])['out'];
    }

    /**
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     */
    private function runInNode(string $script, array $input): array
    {
        $path = base_path('storage/framework/testing/pay-parity.mjs');

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/pay.js'), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo de pago falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
