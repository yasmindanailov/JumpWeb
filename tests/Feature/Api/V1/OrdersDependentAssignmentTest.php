<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Contracts\CheckoutLines;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 6 · menores a cargo, tanda 4 — `POST /api/v1/orders` con `dependent_ids` por línea y
 * `GET /orders/{code}/event-data` con `dependents[]` (`docs/specs/menores-a-cargo.md` §9.9.3 D1, D3,
 * D7; `DECISIONES #202`), contra el contrato.
 *
 * Lo que vale el fichero es la SECUENCIA: la asignación se comprueba ANTES del dinero —un id que no
 * puede asignarse responde 422 por campo sin crear el pedido ni consumir la ficha de admisión— y se
 * escribe DESPUÉS del `allow`, de modo que la 201 ya la lleva. Y la doctrina de `event-data`: el
 * nombre del menor viaja por ahí y NUNCA por los endpoints del pedido.
 */
class OrdersDependentAssignmentTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/orders';

    private User $user;

    private TicketType $entry;

    private TicketType $pack;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-27 12:00:00', 'Europe/Madrid'));
        $this->user = User::factory()->create();

        $rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (['10:00:00', '11:00:00'] as $start) {
            Slot::create([
                'zone_id' => $zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 10, 'online_capacity' => 10,
            ]);
        }

        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 990]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 60, 'min_qty' => 2, 'max_qty' => 10, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $this->pack->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1500]);

        $this->mode('externo');
    }

    private function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
        Setting::flushMemo();
    }

    private function add(User $holder, string $name = 'Lucas', string $bornOn = '2017-03-12'): Dependent
    {
        return app(DependentRegistry::class)->add($holder, $name, $bornOn);
    }

    private function signFor(User $holder, Dependent $dependent): void
    {
        $version = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();

        app(WaiverSigner::class)->sign($holder, $version, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB, ip: '10.0.0.7', userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT, subjectId: (int) $dependent->getKey(),
        ));
    }

    /** @return array<string, mixed> */
    private function cart(array $lines): array
    {
        return ['items' => array_map(fn (array $line): array => [
            'product_id' => ($line['product'] ?? $this->entry)->id,
            'date' => $this->date,
            'time' => $line['time'] ?? '10:00:00',
            'quantity' => $line['quantity'] ?? 2,
            ...(isset($line['dependent_ids']) ? ['dependent_ids' => $line['dependent_ids']] : []),
        ], $lines)];
    }

    // ── El camino feliz: 201 con la asignación ya escrita ─────────────────────────────────────

    public function test_it_assigns_the_tickets_to_the_dependents_and_the_contract_accepts_the_field(): void
    {
        $lucas = $this->add($this->user);
        $vera = $this->add($this->user, 'Vera', '2019-11-02');

        $response = $this->actingAs($this->user)->postJson(self::PATH, $this->cart([
            ['quantity' => 2, 'dependent_ids' => [$lucas->id, $vera->id]],
            ['quantity' => 1, 'time' => '11:00:00'],
        ]));

        $response->assertCreated()->assertValidRequest()->assertValidResponse(201);

        $order = Order::firstOrFail();
        [$first, $second] = $order->items()->whereNull('parent_item_id')->orderBy('id')->get();
        $this->assertEqualsCanonicalizing([$lucas->id, $vera->id], DependentAssignment::where('order_item_id', $first->id)->pluck('dependent_id')->all());
        $this->assertSame(0, DependentAssignment::where('order_item_id', $second->id)->count());

        // Y lo asignado se lee por `event-data`, emparejado por la línea (D7).
        $this->actingAs($this->user)->getJson(self::ROOT.'/orders/'.$order->code.'/event-data')
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('reservations.0.reservation_id', $first->id)
            ->assertJsonPath('reservations.0.dependents.0.name', 'Lucas')
            ->assertJsonPath('reservations.0.dependents.1.name', 'Vera')
            ->assertJsonPath('reservations.1.dependents', []);
    }

    /**
     * **La guarda que hace falsable D7**: el nombre del menor NUNCA viaja por los endpoints del
     * pedido, ni en la 201 —que se compone justo después de asignar— ni en las listas.
     */
    public function test_the_dependents_names_never_travel_in_the_order_endpoints(): void
    {
        $lucas = $this->add($this->user, 'Lucasnombreunico');

        $created = $this->actingAs($this->user)->postJson(self::PATH, $this->cart([['quantity' => 1, 'dependent_ids' => [$lucas->id]]]))
            ->assertCreated();
        $this->assertStringNotContainsString('Lucasnombreunico', $created->getContent() ?: '', 'la 201 no lleva el nombre');

        $code = Order::firstOrFail()->code;
        foreach ([self::ROOT.'/me/orders', self::ROOT.'/orders/'.$code, self::ROOT.'/me/reservations/upcoming'] as $url) {
            $response = $this->actingAs($this->user)->getJson($url)->assertOk();
            $this->assertStringNotContainsString('Lucasnombreunico', $response->getContent() ?: '', "«{$url}» devuelve el nombre de un menor: vive en `event-data`");
        }
    }

    // ── Los rechazos van ANTES del dinero ─────────────────────────────────────────────────────

    public function test_a_foreign_dependent_is_rejected_per_field_before_the_order_exists(): void
    {
        $other = User::factory()->create();
        $ofOther = $this->add($other, 'Ajeno');
        $own = $this->add($this->user);

        $response = $this->actingAs($this->user)->postJson(self::PATH, $this->cart([['quantity' => 2, 'dependent_ids' => [$own->id, $ofOther->id]]]));

        $response->assertStatus(422)->assertValidResponse(422)
            ->assertJsonPath('error.code', 'validation_failed');
        // Las claves llevan puntos (`items.0.dependent_ids.1`), así que se leen LITERALES, no por ruta.
        $this->assertSame(
            ['items.0.dependent_ids.1' => [__('api.dependents.not_yours')]],
            $response->json('error.fields'),
            'el rechazo va en el campo de ESE id, y solo en él'
        );
        $this->assertSame(0, Order::count(), 'la asignación se comprueba ANTES de crear el pedido');
        $this->assertSame(0, RateLimiter::attempts('reservation-confirm:'.$this->user->id), 'y antes de consumir la ficha de admisión');
        $this->assertSame(0, DependentAssignment::count());
    }

    public function test_the_waiver_is_a_condition_in_internal_mode(): void
    {
        $this->mode('interno');
        $lucas = $this->add($this->user);
        $cart = $this->cart([['quantity' => 1, 'dependent_ids' => [$lucas->id]]]);

        $response = $this->actingAs($this->user)->postJson(self::PATH, $cart)->assertStatus(422);
        $this->assertSame(['items.0.dependent_ids.0' => [__('api.dependents.waiver_unsigned')]], $response->json('error.fields'));
        $this->assertSame(0, Order::count());

        $this->signFor($this->user, $lucas);

        $this->actingAs($this->user)->postJson(self::PATH, $cart)->assertCreated();
        $this->assertSame(1, DependentAssignment::count());
    }

    public function test_more_dependents_than_units_and_pack_lines_are_rejected(): void
    {
        $lucas = $this->add($this->user);
        $vera = $this->add($this->user, 'Vera', '2019-11-02');

        $tooMany = $this->actingAs($this->user)->postJson(self::PATH, $this->cart([['quantity' => 1, 'dependent_ids' => [$lucas->id, $vera->id]]]))
            ->assertStatus(422);
        $this->assertSame(['items.0.dependent_ids' => [__('api.dependents.too_many')]], $tooMany->json('error.fields'));

        $pack = $this->actingAs($this->user)->postJson(self::PATH, $this->cart([['product' => $this->pack, 'quantity' => 4, 'dependent_ids' => [$lucas->id]]]))
            ->assertStatus(422);
        $this->assertSame(['items.0.dependent_ids' => [__('api.dependents.entries_only')]], $pack->json('error.fields'));

        $this->assertSame(0, Order::count());
    }

    /**
     * La FORMA la valida la capa de entrega: un id repetido o que no es entero es un 422 de validación.
     *
     * ⚠️⚠️ **Este caso CAMBIÓ DE PREMISA en `#567` y se reescribió**: aseveraba el campo
     * `items.0.dependent_ids.0`, que es el que emitía el `distinct` de Laravel —la regla que comparaba
     * contra la cesta ENTERA y rechazaba compras legítimas—. «Sin repetidos» es de la LÍNEA, así que
     * el aviso cae sobre la línea y con su motivo en nuestro idioma, no con «has a duplicate value».
     */
    public function test_the_shape_of_the_field_is_validated_before_anything_else(): void
    {
        $lucas = $this->add($this->user);

        $repeated = $this->actingAs($this->user)->postJson(self::PATH, $this->cart([['quantity' => 2, 'dependent_ids' => [$lucas->id, $lucas->id]]]))
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->assertSame(['items.0.dependent_ids' => [__('api.dependents.repeated')]], $repeated->json('error.fields'));
        // Y el motivo existe en los TRES idiomas: si faltara en uno, ese cliente leería la CLAVE.
        foreach (['es', 'en', 'fr'] as $locale) {
            $this->assertTrue(app('translator')->has('api.dependents.repeated', $locale, false), "Falta «api.dependents.repeated» en {$locale}.");
        }

        $this->actingAs($this->user)->postJson(self::PATH, $this->cart([['quantity' => 2, 'dependent_ids' => ['x']]]))
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame(0, Order::count());
    }

    /**
     * ❗❗❗ **EL MISMO MENOR EN DOS ENTRADAS SE COMPRA** (`#567`, `[DECIDIDO owner, 2026-09-12]`).
     *
     * Es el caso que faltaba, y por su ausencia el defecto llegó al navegador del owner con la suite en
     * verde: ningún test mandaba dos líneas con el mismo niño, y el `distinct` de dos comodines compara
     * contra la cesta entera. Lo sostienen `DependentAssigner`, que valida línea a línea, y el índice
     * único `(order_item_id, dependent_id)`, que también es por LÍNEA.
     */
    public function test_the_same_dependent_may_go_on_two_different_lines(): void
    {
        $lucas = $this->add($this->user);

        $response = $this->actingAs($this->user)->postJson(self::PATH, $this->cart([
            ['quantity' => 1, 'dependent_ids' => [$lucas->id]],
            ['quantity' => 1, 'time' => '11:00:00', 'dependent_ids' => [$lucas->id]],
        ]));

        $response->assertCreated()->assertValidRequest()->assertValidResponse(201);

        $items = Order::firstOrFail()->items()->whereNull('parent_item_id')->orderBy('id')->get();
        $this->assertCount(2, $items, 'dos líneas a horas distintas son dos reservas, no una fundida');

        foreach ($items as $item) {
            $this->assertSame([$lucas->id], DependentAssignment::where('order_item_id', $item->id)->pluck('dependent_id')->all());
        }
    }

    // ── La escritura no puede tirar el pedido ─────────────────────────────────────────────────

    /** §4.10 — si Identity no puede escribir, el pedido y su cobro siguen en pie y la 201 sale. */
    public function test_a_failed_assignment_leaves_the_order_and_the_payment_standing(): void
    {
        $lucas = $this->add($this->user);
        $this->app->instance(CheckoutLines::class, new class implements CheckoutLines
        {
            public function forOrder(int $orderId, int $userId): array
            {
                throw new \RuntimeException('Booking no responde');
            }
        });

        $this->actingAs($this->user)->postJson(self::PATH, $this->cart([['quantity' => 1, 'dependent_ids' => [$lucas->id]]]))
            ->assertCreated()->assertValidResponse(201);

        $this->assertSame(1, Order::count());
        $this->assertSame(0, DependentAssignment::count(), 'la línea se queda sin asignar; el pedido no se toca');
    }

    /** Sin `dependent_ids` nada cambia: la petición de siempre sigue siendo la petición de siempre. */
    public function test_a_cart_without_dependents_behaves_exactly_as_before(): void
    {
        $this->actingAs($this->user)->postJson(self::PATH, $this->cart([['quantity' => 2]]))
            ->assertCreated()->assertValidRequest()->assertValidResponse(201);

        $this->assertSame(1, Order::count());
        $this->assertSame(0, DependentAssignment::count());
        $this->assertSame(1, RateLimiter::attempts('reservation-confirm:'.$this->user->id));
    }
}
