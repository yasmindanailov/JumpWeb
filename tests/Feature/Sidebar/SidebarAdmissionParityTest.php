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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.4a·1, re-apuntado en 4.7·2b·2 — **el paso del carrito al pago, con las respuestas
 * REALES del servidor y el diccionario REAL** (`DECISIONES #78`).
 *
 * ### Qué comparaba antes y por qué eso ya no es la pregunta
 *
 * Nació comparando el destino y el aviso contra el componente Livewire. Medido campo a campo, esa
 * mitad **no dejaba nada que el módulo no probara ya por su cuenta**: las cinco situaciones, el
 * destino de cada una, la clave de cada aviso, el `max` que sale del sobre y la regla de releer el
 * estado están todas en `admission.test.js`, y las cinco mutaciones que se probaron dejan rojos los
 * dos a la vez. Comparar contra un motor que se va no añadía red.
 *
 * ### Lo que sí queda, y es lo único que este fichero puede decir
 *
 * `admission.test.js` prueba el módulo con un diccionario y unas respuestas **fabricadas por quien
 * escribió el módulo**. Este test es el único sitio donde la cadena entera es real:
 *
 *  · **las respuestas las da el servidor de verdad** —`GET /me` y `GET /me/reservation-eligibility`
 *    sobre una instalación en pausa, un titular con el tope lleno o el limitador agotado—, así que un
 *    cambio de contrato se ve aquí aunque el módulo siga verde contra sus fixtures;
 *  · **el diccionario es el de `lang/`**, no uno inventado, y la referencia es `__()`, que es la
 *    fuente que el error bag de Livewire solo intermediaba (misma re-apuntada que `#75(b)`).
 *
 * ⚠️ **Está MEDIDO que esa segunda mitad no la cubre nadie más**: renombrar `errors.too_many_pending`
 * en `lang/es/tickets.php` deja los 2715 casos verdes salvo uno, y es el de aquí. `admission.test.js`
 * no puede verlo —su diccionario es de mentira— y `SidebarTextParityTest` no lleva esas claves en su
 * lista.
 *
 * ⚠️ **Lo que este test NO es**: no fija el reparto de `decideCheckout()` —eso es `admission.test.js`,
 * con muchos más casos frontera— ni los códigos que publica el endpoint —eso es
 * `ReservationEligibilityTest`, que los afirma uno a uno—. Aquí el sujeto es el **cableado**: que esas
 * dos piezas, tal y como existen, encajen.
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

    // ── El destino, con las respuestas reales de cada situación ───────────────────────────────

    /**
     * ⚠️ **Las cinco situaciones, cada una con su destino, alimentadas por el servidor de verdad.**
     *
     * El destino se nombra por la constante de `machine.js` y no por su número: el paso al que se va
     * es la decisión, el número con el que se llame es del embudo.
     *
     * Lo que esto añade a `admission.test.js` no es el reparto —allí está, con más fronteras— sino que
     * las respuestas que lo alimentan **no las escribe nadie**: si `allowed` o `reason` cambiaran de
     * nombre, sus fixtures seguirían verdes y este caso no.
     */
    public function test_the_real_responses_take_the_client_where_each_situation_leads(): void
    {
        $steps = $this->steps();

        $clean = User::factory()->create();
        $capped = User::factory()->create();
        $throttled = User::factory()->create();
        $paused = User::factory()->create();

        $this->fillPendingOrders($capped);
        $this->exhaustRateLimit($throttled);

        $expected = [
            // Sin sesión, `GET /me` responde 401 y ese es el único «no hay nadie» que vale.
            'invitado' => [null, 1, 'IDENTIFY'],
            'identificado y admitido' => [$clean, 1, 'PAY'],
            // La única guarda que no pregunta nada al servidor, y por eso va la primera.
            'cesta vacía' => [$clean, 0, 'CART'],
            'tope de pendientes' => [$capped, 1, 'CART'],
            'límite de frecuencia' => [$throttled, 1, 'CART'],
        ];

        foreach ($expected as $label => [$user, $cartCount, $step]) {
            $this->assertSame(
                $steps[$step],
                $this->clientVerdict($user, cartCount: $cartCount)['step'],
                "Con «{$label}» el cajón no va a «{$step}».\n".
                '⚠️ El diff de árbol no puede ver esto: lo que se decide no es marcado, es a qué '.
                'pantalla lleva el CTA que convierte la cesta en un pedido.'
            );
        }

        // La pausa va aparte porque cambia el estado de la instalación para todos los demás.
        $this->pause();
        $this->assertSame(
            $steps['CART'],
            $this->clientVerdict($paused, cartCount: 1)['step'],
            'con las reservas en pausa el cajón se queda en el carrito'
        );
    }

    // ── El texto del aviso, contra el diccionario ─────────────────────────────────────────────

    /**
     * ⚠️ **Los avisos, palabra por palabra y en los TRES idiomas, contra `__()`.**
     *
     * El error bag de Livewire era un **intermediario** de estas mismas claves, así que la referencia
     * correcta siempre fue el diccionario (`#75(b)`). Y el del tope no es una comparación de textos:
     * el cliente interpola `max_pending_orders` **del sobre de la API** y la referencia interpola la
     * constante de la POLÍTICA, así que un desajuste entre esos dos números cae aquí.
     *
     * Va por idioma porque el francés y el inglés no comparten la posición del marcador.
     */
    public function test_the_denial_messages_are_the_dictionary_with_the_servers_own_numbers(): void
    {
        $capped = User::factory()->create();
        $throttled = User::factory()->create();

        $this->fillPendingOrders($capped);
        $this->exhaustRateLimit($throttled);

        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            $cases = [
                'tope de pendientes' => [
                    $capped,
                    __('tickets.errors.too_many_pending', ['max' => ReservationAdmissionPolicy::MAX_PENDING_PER_USER]),
                ],
                'límite de frecuencia' => [$throttled, __('tickets.errors.try_later')],
            ];

            foreach ($cases as $label => [$user, $expected]) {
                $this->assertStringNotContainsString(
                    'tickets.errors', $expected,
                    "la clave del aviso de «{$label}» no existe en el diccionario «{$locale}»"
                );

                $this->assertSame(
                    $expected,
                    $this->clientVerdict($user, cartCount: 1)['error'],
                    "El aviso de «{$label}» en «{$locale}» NO es el del diccionario.\n".
                    '⚠️ El normalizador del diff de árbol descarta los nodos de texto: este aviso solo '.
                    'lo compara este test.'
                );
            }
        }
    }

    /** Y el de la cesta vacía, la única guarda que no pregunta nada al servidor. */
    public function test_the_empty_cart_message_is_the_dictionary(): void
    {
        $user = User::factory()->create();

        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            $expected = __('tickets.errors.cart_empty');

            $this->assertStringNotContainsString('tickets.errors', $expected,
                "la clave del aviso de cesta vacía no existe en el diccionario «{$locale}»");

            $this->assertSame(
                $expected,
                $this->clientVerdict($user, cartCount: 0)['error'],
                "El aviso de cesta vacía en «{$locale}» NO es el del diccionario."
            );
        }
    }

    // ── La pausa: el veredicto que no compone mensaje ─────────────────────────────────────────

    /**
     * ⚠️ **La pausa no se enseña como error de carrito, y eso salió de MEDIR el motor viejo**:
     * `reportAdmissionDenial()` escribía `errors.reservations_paused` en su bag y ese texto **no se
     * pintaba jamás**, porque el cartel de mantenimiento sustituía el flujo entero. Pintarlo en el
     * cajón habría sido enseñar un texto que la web no enseña en ninguna instalación.
     *
     * La demostración sobre el Blade se va con el Blade —era su única prueba posible—; lo que se
     * queda, y es lo que importa, es la conducta: **sin mensaje, y releyendo el estado**, que es lo
     * que hace aparecer el cartel y lo que cierra el residual de 4.3·3 (un cajón ya ABIERTO cuando se
     * acciona el interruptor). Aquí se ejerce con la instalación REALMENTE pausada.
     */
    public function test_the_pause_is_a_notice_and_not_a_cart_error(): void
    {
        $user = User::factory()->create();
        $this->pause();

        $verdict = $this->clientVerdict($user, cartCount: 1);

        $this->assertSame('', $verdict['error'], 'el cajón no puede enseñar un texto donde la web enseña el cartel');
        $this->assertTrue($verdict['rereadStatus'], 'y tiene que releer el estado para poder pintarlo');
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
     * caería en el aviso genérico y el mensaje dejaría de coincidir con el del diccionario.
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
     * Los pasos del embudo tal y como los nombra `machine.js`.
     *
     * Se leen del módulo en vez de escribir números en PHP: lo que este test afirma es a qué PASO se
     * va, y un número suelto en el test no dice cuál es ni se entera si el embudo se renumera.
     *
     * @return array<string, int>
     */
    private function steps(): array
    {
        return $this->runInNode(null, <<<'JS'
            import { STEPS } from 'file://__MODULE__';
            process.stdout.write(JSON.stringify(STEPS));
            JS, 'machine.js');
    }

    /**
     * Ejecuta un módulo del cajón en Node, que es lo que corre en el navegador.
     *
     * Mismo patrón que las demás paridades del cajón: script efímero que importa el módulo REAL por
     * ruta absoluta y habla por stdin/stdout con JSON.
     *
     * @param  array<string, mixed>|null  $state
     * @return array<string, mixed>
     */
    private function runInNode(?array $state, ?string $script = null, string $module = 'admission.js'): array
    {
        $script ??= <<<'JS'
            import { decideCheckout } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify(decideCheckout(JSON.parse(raw))));
            });
            JS;

        $path = base_path('storage/framework/testing/sidebar-'.pathinfo($module, PATHINFO_FILENAME).'.mjs');

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/'.$module), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput($state === null ? '' : json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo «{$module}» falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
