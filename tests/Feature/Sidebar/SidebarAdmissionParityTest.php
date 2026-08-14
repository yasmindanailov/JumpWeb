<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ReservationAdmissionPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Http\Middleware\SetLocale;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.4a·1 — **el paso del carrito al pago lleva al mismo sitio en los dos motores**.
 *
 * El diff de árbol no puede ver nada de esto: lo que se compara aquí no es marcado, es **a qué
 * pantalla se va y qué se dice** al pulsar «Ir a pagar». Son cinco situaciones y cada una tiene su
 * destino —identificarse, pagar, o quedarse en el carrito con un aviso—, y el cliente las decide con
 * un veredicto de la API mientras el servidor las decide con el guard y la política de admisión.
 *
 * ⚠️ **El caso que este test existe para fijar es el de la PAUSA**, y salió de medirlo, no de leer el
 * Blade: `reportAdmissionDenial()` escribe `errors.reservations_paused` en el bag y **ese mensaje no
 * se pinta jamás** — al volver al paso 4 se cumple `showPausedNotice()` y el cartel de mantenimiento
 * sustituye el flujo entero. Un motor que enseñara ese error donde la web enseña el cartel mostraría
 * un texto que no existe en ninguna instalación, y el gate de árbol lo daría por bueno.
 *
 * ⚠️ Y el destino se compara **aunque los pasos 5 y 8 no estén transcritos todavía** (son 4.4b y 4.5).
 * Eso es a propósito: la decisión ya está tomada y probada, así que enchufar esas pantallas será
 * cablear y no volver a decidir — que es justo el fallo que 4.3·1 encontró con la banda de progreso.
 */
class SidebarAdmissionParityTest extends TestCase
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
            'capacity' => 20, 'online_capacity' => 20,
        ]);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'min_qty' => 1, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 990]);
    }

    // ── El destino: las cinco situaciones ─────────────────────────────────────────────────────

    /**
     * ⚠️ **Las CINCO situaciones y sus destinos, comparadas una a una.**
     *
     * No es una muestra: son todas las salidas de `Purchase::checkout()`. Y el orden importa tanto
     * como el destino — la cesta vacía se comprueba **antes** de preguntar nada al servidor, y el
     * «no hay nadie» manda sobre cualquier veredicto de elegibilidad.
     */
    public function test_the_client_goes_where_the_server_goes(): void
    {
        foreach ($this->situations() as $label => [$user, $prepare]) {
            $prepare();

            $server = $this->serverStep($user, withCart: $label !== 'cesta vacía');
            $client = $this->clientVerdict($user, cartCount: $label === 'cesta vacía' ? 0 : 1);

            $this->assertSame(
                $server,
                $client['step'],
                "Con «{$label}» los dos motores NO van al mismo paso.\n".
                "  Livewire: {$server}\n".
                "  Cajón SPA: {$client['step']}\n".
                '⚠️ El diff de árbol no puede ver esto: lo que diverge no es el marcado, es a qué '.
                'pantalla lleva el CTA que convierte la cesta en un pedido.'
            );
        }
    }

    /**
     * Las cinco situaciones de `checkout()`, cada una con su titular y cómo se prepara.
     *
     * @return array<string, array{0: ?User, 1: callable}>
     */
    private function situations(): array
    {
        $clean = User::factory()->create();
        $capped = User::factory()->create();
        $throttled = User::factory()->create();
        $paused = User::factory()->create();

        return [
            'invitado' => [null, fn () => null],
            'identificado y admitido' => [$clean, fn () => null],
            // ⚠️ La cesta del caso anterior sobrevive: vive en la SESIÓN y `mount()` la restaura, así
            // que sin vaciarla este caso no probaría la cesta vacía sino la del vecino.
            'cesta vacía' => [$clean, fn () => session()->forget('purchase.cart')],
            'tope de pendientes' => [$capped, fn () => $this->fillPendingOrders($capped)],
            'límite de frecuencia' => [$throttled, fn () => $this->exhaustRateLimit($throttled)],
            'reservas en pausa' => [$paused, fn () => $this->pause()],
        ];
    }

    // ── El texto del aviso ────────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **Los avisos, palabra por palabra y en los TRES idiomas.**
     *
     * El del tope lleva un parámetro (`:max`) que sale de dos sitios distintos —del `context` del
     * veredicto en Livewire y de `max_pending_orders` del sobre en la API—, así que un desajuste
     * entre esos dos números se ve aquí y en ningún otro sitio. Y va por idioma porque el francés y
     * el inglés no comparten la posición del marcador.
     */
    public function test_the_denial_messages_match_the_server_in_every_locale(): void
    {
        $capped = User::factory()->create();
        $throttled = User::factory()->create();

        $this->fillPendingOrders($capped);
        $this->exhaustRateLimit($throttled);

        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            foreach (['tope de pendientes' => $capped, 'límite de frecuencia' => $throttled] as $label => $user) {
                $server = $this->serverError($user);
                $client = $this->clientVerdict($user, cartCount: 1)['error'];

                $this->assertNotSame('', $server, "el servidor tiene que avisar de «{$label}»");
                $this->assertSame(
                    $server,
                    $client,
                    "El aviso de «{$label}» en «{$locale}» NO dice lo mismo en los dos motores.\n".
                    '⚠️ El normalizador del diff de árbol descarta los nodos de texto: este aviso solo '.
                    'lo compara este test.'
                );
            }
        }
    }

    /** Y el de la cesta vacía, que es la única guarda que no pregunta nada al servidor. */
    public function test_the_empty_cart_message_matches_the_server(): void
    {
        $user = User::factory()->create();

        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            $component = Livewire::test(Purchase::class)->set('step', 4)->call('checkout');

            $this->assertSame(
                (string) $component->errors()->first('cart'),
                $this->clientVerdict($user, cartCount: 0)['error'],
                "El aviso de cesta vacía en «{$locale}» NO coincide."
            );
        }
    }

    // ── La pausa: el aviso que el servidor escribe y NUNCA enseña ─────────────────────────────

    /**
     * ⚠️ **Lo que este caso fija está MEDIDO y es contraintuitivo**: con las reservas en pausa,
     * `reportAdmissionDenial()` escribe su mensaje en el error bag y el HTML **no lo contiene**,
     * porque `showPausedNotice()` sustituye el flujo entero por el cartel de mantenimiento.
     *
     * Por eso el cliente NO compone error para la pausa: pintarlo sería enseñar un texto que la web
     * no enseña en ninguna instalación. Lo que hace en su lugar —releer el estado— es lo que le
     * permite pintar el cartel, y de paso cierra el residual que 4.3·3 dejó declarado: un cajón ya
     * ABIERTO cuando se acciona el interruptor no se enteraba hasta cerrarlo y volver a abrirlo.
     */
    public function test_the_pause_is_a_notice_and_not_a_cart_error(): void
    {
        $user = User::factory()->create();
        $this->pause();

        $component = $this->componentWithCart($user)->call('checkout');
        $message = (string) $component->errors()->first('cart');
        $html = $component->html();

        $this->assertNotSame('', $message, 'el servidor sí escribe el mensaje en su bag');
        $this->assertStringContainsString('purchase__maint', $html, 'y el cartel de mantenimiento lo tapa');
        $this->assertStringNotContainsString(
            $message, $html,
            'si el mensaje de pausa llegara a pintarse, el cliente tendría que pintarlo también'
        );

        $verdict = $this->clientVerdict($user, cartCount: 1);

        $this->assertSame('', $verdict['error'], 'el cajón no puede enseñar un texto que la web no enseña');
        $this->assertTrue($verdict['rereadStatus'], 'y tiene que releer el estado para poder pintar el cartel');
    }

    /**
     * El espejo del anterior: releer el estado es EXCLUSIVO de la pausa.
     *
     * Sin esto, «releer siempre» pasaría el caso de arriba añadiendo una petición a cada clic de un
     * cliente que no tiene ningún problema.
     */
    public function test_no_other_verdict_asks_to_reread_the_status(): void
    {
        $clean = User::factory()->create();
        $capped = User::factory()->create();
        $this->fillPendingOrders($capped);

        foreach (['admitido' => $clean, 'tope de pendientes' => $capped] as $label => $user) {
            $this->assertFalse(
                $this->clientVerdict($user, cartCount: 1)['rereadStatus'],
                "«{$label}» no tiene por qué releer el estado de las reservas"
            );
        }
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /** El paso al que llega el componente Livewire tras pulsar «Ir a pagar». */
    private function serverStep(?User $user, bool $withCart): int
    {
        $component = $withCart
            ? $this->componentWithCart($user)
            : $this->actingAsOrGuest($user)->livewire()->set('step', 4);

        return (int) $component->call('checkout')->get('step');
    }

    /** El aviso que el componente Livewire deja en el carrito tras pulsar «Ir a pagar». */
    private function serverError(User $user): string
    {
        return (string) $this->componentWithCart($user)->call('checkout')->errors()->first('cart');
    }

    /**
     * Un componente con una línea en la cesta, sembrada pasando por el flujo real.
     *
     * Se pasa por `addToCart()` en vez de escribir `$cart` a mano para que la línea la componga el
     * dominio: un test que fija una forma de cesta que el código nunca produce prueba su propia idea.
     */
    private function componentWithCart(?User $user): Testable
    {
        return $this->actingAsOrGuest($user)->livewire()
            ->call('selectType', $this->entry->id)
            ->call('selectDate', $this->date)
            ->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart');
    }

    private function livewire(): Testable
    {
        return Livewire::test(Purchase::class);
    }

    private function actingAsOrGuest(?User $user): self
    {
        if ($user !== null) {
            $this->actingAs($user);
        }

        return $this;
    }

    /**
     * El veredicto del cajón SPA: las respuestas REALES de la API pasadas por el módulo REAL en Node.
     *
     * ⚠️ **Las respuestas se piden de verdad y no se fabrican**, que es lo que hace este test capaz de
     * ver un cambio de contrato: si `reason` dejara de publicar `too_many_pending_orders`, el módulo
     * caería en el aviso genérico y el mensaje dejaría de coincidir con el del servidor.
     *
     * @return array{step: int, error: string, rereadStatus: bool}
     */
    private function clientVerdict(?User $user, int $cartCount): array
    {
        $this->actingAsOrGuest($user);

        // ⚠️ El diccionario se toma ANTES de las peticiones: `SetLocale` corre en cada una y deja la
        // app en el idioma que negocie la petición, así que leerlo después devolvería el del servidor
        // y no el del caso. El cajón real no tiene este problema —recibe el payload en el montaje—,
        // pero el test sí, y sin esto compararía español contra inglés.
        $messages = __('tickets');
        $locale = app()->getLocale();

        $verdict = $this->runInNode([
            'cartCount' => $cartCount,
            'me' => $this->apiResult($this->getJson('/api/v1/me')),
            'eligibility' => $this->apiResult($this->getJson('/api/v1/me/reservation-eligibility')),
            'messages' => $messages,
        ]);

        $this->app->setLocale($locale);

        return $verdict;
    }

    /**
     * Una respuesta HTTP con la forma FIJA que `api.js` entrega a quien llama.
     *
     * @return array{ok: bool, status: int, data: mixed}
     */
    private function apiResult(TestResponse $response): array
    {
        return [
            'ok' => $response->getStatusCode() < 400,
            'status' => $response->getStatusCode(),
            'data' => $response->json(),
        ];
    }

    private function pause(): void
    {
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '+34 968 12 34 56']);
        Setting::flushMemo();
    }

    private function fillPendingOrders(User $user): void
    {
        for ($i = 0; $i < ReservationAdmissionPolicy::MAX_PENDING_PER_USER; $i++) {
            Order::create([
                'user_id' => $user->id, 'code' => 'PEND-'.$user->id.'-'.$i, 'status' => Order::STATUS_PENDING,
                'subtotal' => 990, 'tax' => 0, 'total' => 990, 'currency' => 'EUR',
                'expires_at' => now()->addHour(),
            ]);
        }
    }

    /**
     * Agota el limitador de frecuencia por el contrato, no escribiendo su clave.
     *
     * `admitReservation()` es la variante que CONSUME; usarla es lo que garantiza que se está
     * llenando el mismo cubo que mira la consulta.
     */
    private function exhaustRateLimit(User $user): void
    {
        $admission = app(ReservationAdmission::class);

        for ($i = 0; $i < ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE; $i++) {
            $admission->admitReservation((int) $user->id);
        }
    }

    /**
     * Ejecuta `admission.js` en Node, que es lo que corre en el navegador.
     *
     * Mismo patrón que las demás paridades del cajón: script efímero que importa el módulo REAL por
     * ruta absoluta y habla por stdin/stdout con JSON.
     *
     * @param  array<string, mixed>  $state
     * @return array{step: int, error: string, rereadStatus: bool}
     */
    private function runInNode(array $state): array
    {
        $script = <<<'JS'
            import { decideCheckout } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify(decideCheckout(JSON.parse(raw))));
            });
            JS;

        $path = base_path('storage/framework/testing/decide-checkout.mjs');

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/admission.js'), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo de admisión falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
