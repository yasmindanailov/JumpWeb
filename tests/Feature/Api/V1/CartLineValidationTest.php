<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 4 · paso 4.0b·6 — `POST /api/v1/cart/validate-line`.
 *
 * El endpoint existe porque la cesta de la SPA vive en `localStorage` y al añadir una línea **no
 * queda ninguna ida y vuelta al servidor** (`sidebar-spa.md` §4.4.2, `DECISIONES #38(f)`). Lo que
 * estas guardas protegen es justamente lo que se le escaparía a un cliente que validara por su
 * cuenta:
 *
 *  1. **el saneo de un campo `number`** — la edad contestada «cinco» el servidor la ve VACÍA, y ese
 *     caso no lo encuentra ninguna revisión de código, solo un cliente enfadado;
 *  2. **la fusión de líneas** — que cambia cantidad, señal y ocupación;
 *  3. **el re-tope de cantidad**, que el servidor aplica en silencio desde siempre.
 *
 * Y una que la web no necesitaba: la franja se comprueba contra la OFERTA, no contra el aforo a
 * secas. En la web la hora siempre venía de `availableTimes()`; un cliente de API puede enviar
 * cualquiera, y validar solo por aforo daría por buena una línea que el checkout va a rechazar.
 */
class CartLineValidationTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/cart/validate-line';

    private Zone $zone;

    private int $normalRateId;

    private string $date;

    private TicketType $entry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalRateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true,
            'max_guests_per_slot' => 60, 'max_per_slot' => 0, 'prep_blocks_cupo' => false,
        ]);

        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (['10:00:00', '11:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 10, 'online_capacity' => 10,
            ]);
        }

        $this->entry = $this->makeEntry();
    }

    private function makeEntry(): TicketType
    {
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ]);
        $entry->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 990]);

        return $entry;
    }

    /** Un pack con un campo de texto obligatorio y una EDAD (`number`), que es el caso interesante. */
    private function makePack(): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'min_qty' => 6, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => (int) TicketType::max('position') + 1,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Homenajeado']],
                ['key' => 'age', 'type' => 'number', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Edad']],
                ['key' => 'notes', 'type' => 'textarea', 'required' => false, 'stage' => 'postform', 'label' => ['es' => 'Notas']],
            ],
        ]);
        $pack->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 5000]);

        return $pack;
    }

    /** @param array<string, mixed> $overrides */
    private function line(TicketType $product, int $quantity = 2, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'date' => $this->date,
            'time' => '10:00:00',
            'quantity' => $quantity,
        ], $overrides);
    }

    /** @param array<string, mixed> $body */
    private function check(array $body): TestResponse
    {
        return $this->postJson(self::PATH, $body);
    }

    // ── El camino feliz ───────────────────────────────────────────────────────────────────────

    public function test_a_valid_line_is_accepted_with_the_quantity_it_would_enter_with(): void
    {
        $this->check(['line' => $this->line($this->entry, 3)])
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('problems', [])
            ->assertJsonPath('quantity', 3)
            ->assertJsonPath('quantity_capped', false)
            ->assertJsonPath('max_quantity', 10)
            ->assertJsonPath('merges_with_index', null);
    }

    /**
     * La cesta enviada RETIENE cupo, así que el techo que se devuelve ya la descuenta — es la misma
     * regla que hace que la disponibilidad lleve la cesta (`AFORO-02`). Sin esto, un cliente vería
     * las plazas que su propia cesta ya ocupa.
     */
    public function test_the_cart_sent_discounts_the_capacity_it_already_holds(): void
    {
        // 4 plazas de las 10 ya retenidas por la propia cesta a esa hora → quedan 6.
        $this->check([
            'line' => $this->line($this->entry, 1),
            'items' => [$this->line($this->entry, 4)],
        ])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('max_quantity', 6);

        // Y la hora de al lado no la toca: una entrada de 60 min ocupa su tramo, no el siguiente.
        $this->check([
            'line' => $this->line($this->entry, 1, ['time' => '11:00:00']),
            'items' => [$this->line($this->entry, 4)],
        ])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('max_quantity', 10);
    }

    // ── El caso que justifica el endpoint entero ──────────────────────────────────────────────

    /**
     * **La razón de ser de este paso** (`DECISIONES #38(f)`). Un campo `number` pasa por
     * `preg_replace('/\D+/', '')`, así que «cinco» queda en cadena vacía: el servidor lo ve SIN
     * responder y cualquier validación ingenua en el cliente lo ve contestado. Es el caso que no
     * encuentra ninguna revisión de código.
     */
    public function test_an_age_answered_in_words_is_seen_as_unanswered_by_the_server(): void
    {
        $pack = $this->makePack();

        $this->check(['line' => $this->line($pack, 8, [
            'event_data' => ['celebrant' => 'Mara', 'age' => 'cinco'],
        ])])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', false)
            ->assertJsonCount(1, 'problems')
            ->assertJsonPath('problems.0.reason', 'event_field_required')
            ->assertJsonPath('problems.0.field', 'age');
    }

    /** Y con dígitos dentro sí cuenta: «5 años» se sanea a «5», que es una respuesta. */
    public function test_an_age_with_digits_inside_text_does_count_as_answered(): void
    {
        $pack = $this->makePack();

        $this->check(['line' => $this->line($pack, 8, [
            'event_data' => ['celebrant' => 'Mara', 'age' => '5 años'],
        ])])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', true);
    }

    /** Faltan los dos: se nombran los DOS, para poder resaltar los dos inputs de una vez. */
    public function test_every_missing_required_field_is_named(): void
    {
        $pack = $this->makePack();

        $response = $this->check(['line' => $this->line($pack, 8, ['event_data' => []])])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', false)
            ->assertJsonCount(2, 'problems');

        $this->assertSame(['celebrant', 'age'], array_column($response->json('problems'), 'field'));
    }

    /** Los campos de la fase `postform` no se piden al reservar: se rellenan semanas después. */
    public function test_a_postform_field_is_not_required_to_add_the_line(): void
    {
        $pack = $this->makePack();

        $this->check(['line' => $this->line($pack, 8, [
            'event_data' => ['celebrant' => 'Mara', 'age' => '5'],
        ])])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', true);
    }

    // ── La fusión de líneas ───────────────────────────────────────────────────────────────────

    /**
     * Dos entradas del mismo producto, día y hora se FUNDEN sumando cantidades. Un cliente que no lo
     * supiera crearía una línea duplicada donde el servidor tiene una sola.
     */
    public function test_it_says_which_line_would_absorb_the_candidate(): void
    {
        $this->check([
            'line' => $this->line($this->entry, 2),
            'items' => [
                $this->line($this->entry, 1, ['time' => '11:00:00']),
                $this->line($this->entry, 3),
            ],
        ])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('merges_with_index', 1);
    }

    /** Un pack NUNCA se funde: sumar invitados de otra línea mezclaría dos fiestas. */
    public function test_a_pack_never_merges(): void
    {
        $pack = $this->makePack();
        $answers = ['event_data' => ['celebrant' => 'Mara', 'age' => '5']];

        $this->check([
            'line' => $this->line($pack, 6, $answers),
            'items' => [$this->line($pack, 6, $answers)],
        ])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('merges_with_index', null);
    }

    /** Y una línea CON complementos tampoco: la fusión no sabría qué hacer con ellos. */
    public function test_a_line_with_addons_never_merges(): void
    {
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON, 'zone_id' => $this->zone->id,
            'duration_min' => 0, 'seats_per_unit' => 0, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ]);

        $this->check([
            'line' => $this->line($this->entry, 2, ['addons' => [['product_id' => $addon->id, 'quantity' => 2]]]),
            'items' => [$this->line($this->entry, 3)],
        ])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('merges_with_index', null);
    }

    // ── Cantidad y cupo ───────────────────────────────────────────────────────────────────────

    /** El re-tope que el servidor hace en silencio, dicho en voz alta. */
    public function test_a_quantity_over_the_capacity_is_capped_and_says_so(): void
    {
        $this->check(['line' => $this->line($this->entry, 25)])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('quantity', 10)
            ->assertJsonPath('quantity_capped', true);
    }

    public function test_a_quantity_below_the_pack_minimum_is_rejected(): void
    {
        $pack = $this->makePack();

        $this->check(['line' => $this->line($pack, 3, [
            'event_data' => ['celebrant' => 'Mara', 'age' => '5'],
        ])])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('problems.0.reason', 'quantity_below_minimum')
            ->assertJsonPath('problems.0.field', null)
            // Rechazada: no hay cantidad efectiva que ofrecer.
            ->assertJsonPath('quantity', 0);
    }

    /** Una franja llena por la propia cesta: la hora se ofrece, pero ya no cabe nadie. */
    public function test_a_full_slot_is_sold_out(): void
    {
        $this->check([
            'line' => $this->line($this->entry, 1),
            'items' => [$this->line($this->entry, 10)],
        ])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('problems.0.reason', 'sold_out');
    }

    // ── Lo que la web no necesitaba: la franja contra la OFERTA ───────────────────────────────

    /**
     * Una hora que no existe se rechaza como `time_unavailable`, no como `sold_out`: la diferencia
     * le dice al cliente si reintentar más tarde tiene sentido.
     */
    public function test_a_time_that_is_not_offered_is_rejected(): void
    {
        $this->check(['line' => $this->line($this->entry, 1, ['time' => '19:00:00'])])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('problems.0.reason', 'time_unavailable');
    }

    /**
     * **La guarda que la web no podía necesitar.** Una franja CERRADA a la venta online no se
     * ofrece, y validar solo por aforo la habría dado por buena — el aforo sigue ahí; lo que no está
     * es la oferta. El checkout la rechazaría después, así que decir «válida» sería mentir.
     */
    public function test_a_slot_closed_to_online_sales_is_not_offered(): void
    {
        Slot::query()->where('date', $this->date)->where('start_time', '10:00:00')
            ->update(['online_sales_open' => false]);

        $this->check(['line' => $this->line($this->entry, 1)])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('problems.0.reason', 'time_unavailable');
    }

    /** Un día ya pasado tampoco se ofrece, aunque su franja siga en la base de datos con sitio. */
    public function test_a_past_date_is_not_offered(): void
    {
        $past = Carbon::today()->subDay()->toDateString();
        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $past, 'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);

        $this->check(['line' => $this->line($this->entry, 1, ['date' => $past])])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('problems.0.reason', 'time_unavailable');
    }

    // ── Producto y cesta ──────────────────────────────────────────────────────────────────────

    /** No existe, no se vende o su zona no opera: la misma respuesta, para no ser un oráculo. */
    public function test_a_product_that_is_not_on_sale_is_rejected(): void
    {
        $this->entry->update(['is_sellable' => false]);

        $this->check(['line' => $this->line($this->entry, 1)])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('problems.0.reason', 'product_unavailable');
    }

    public function test_an_unknown_product_is_rejected_the_same_way(): void
    {
        $this->check(['line' => $this->line($this->entry, 1, ['product_id' => 999999])])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('problems.0.reason', 'product_unavailable');
    }

    /**
     * `PAY-12`: el tope de líneas impide inflar la cesta para hacer contención sobre los locks de
     * franjas del checkout.
     */
    public function test_a_full_cart_rejects_a_new_line(): void
    {
        // Aforo holgado a propósito: el tope de LÍNEAS es lo que se prueba, y con la capacidad por
        // defecto la franja se llenaría antes —`sold_out` mordería primero, que es el orden correcto
        // del validador y no lo que este caso quiere fijar—.
        Slot::query()->where('date', $this->date)->update(['capacity' => 200, 'online_capacity' => 200]);

        $items = [];
        for ($i = 0; $i < OrderCreator::MAX_LINES_PER_CART; $i++) {
            $items[] = $this->line($this->makeEntry(), 1);
        }

        $this->check(['line' => $this->line($this->entry, 1), 'items' => $items])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('problems.0.reason', 'cart_full');
    }

    // ── La forma sí es 422 ────────────────────────────────────────────────────────────────────

    /**
     * El veredicto de NEGOCIO va en el cuerpo con un 200; la FORMA sigue siendo un 422 que nombra el
     * campo. Confundirlos dejaría a un cliente sin saber si corregir el dato o la petición.
     */
    public function test_a_malformed_line_is_a_422_that_names_the_field(): void
    {
        $response = $this->check(['line' => $this->line($this->entry, 1, ['date' => '13-08-2026'])])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertArrayHasKey('line.date', $response->json('error.fields'));
    }

    public function test_the_line_is_required(): void
    {
        $this->check(['items' => []])->assertStatus(422)->assertValidResponse(422);
    }

    /** Es público: la web deja llegar hasta el pago sin cuenta, y preguntar si algo cabe también. */
    public function test_it_is_public(): void
    {
        $this->check(['line' => $this->line($this->entry, 1)])->assertOk();
    }

    /**
     * Las respuestas del evento se ACEPTAN y **no se devuelven**: son datos personales de un menor y
     * este endpoint es público. Misma regla que `orders/quote`, y por eso lo que vuelve de un campo
     * que falta es su CLAVE, no lo que se escribió.
     */
    public function test_event_answers_are_never_echoed_back(): void
    {
        $pack = $this->makePack();

        $response = $this->check(['line' => $this->line($pack, 8, [
            'event_data' => ['celebrant' => 'Mara', 'age' => 'cinco'],
        ])]);

        $response->assertOk()->assertValidResponse(200);

        $this->assertStringNotContainsString('Mara', $response->getContent() ?: '');
        $this->assertStringNotContainsString('cinco', $response->getContent() ?: '');
    }
}
