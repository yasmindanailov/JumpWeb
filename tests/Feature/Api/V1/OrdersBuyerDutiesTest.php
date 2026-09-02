<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\ApiTestCase;

/**
 * **LO QUE EL COMPRADOR DEBE ANTES DE CONTRATAR** (`specs/auth-con-google.md` §21.4.2, `#349`),
 * contra el contrato.
 *
 * `[DECIDIDO owner, 2026-09-02]`: las condiciones se aceptan **en el momento del contrato** —no al
 * crear la cuenta— y el teléfono se pide **solo si falta**. Eso es lo que permite que el alta con
 * Google no pregunte ninguna de las dos cosas.
 *
 * ⚠️⚠️ **Y no es solo comodidad: cierra un hueco legal medido.** Antes de esta tanda el embudo **no
 * enseñaba las condiciones en ningún sitio** —cero enlaces en los ocho pasos del cajón—, así que quien
 * ya tenía cuenta compraba sin que se le mostraran nunca. La LCGC (art. 5) pide que el consumidor haya
 * podido conocerlas para que se incorporen al contrato.
 *
 * ⚠️ **La autoridad es el SERVIDOR y por eso estos casos existen.** El cajón pinta la casilla porque
 * el contexto de cuenta se lo dice, pero si la decisión viviera ahí se compraría sin aceptar nada
 * **quitando un `input` del DOM** — el defecto que `#400` documenta para el justificante.
 */
class OrdersBuyerDutiesTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/orders';

    private TicketType $entry;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-02 12:00:00', 'Europe/Madrid'));

        $rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        Slot::create([
            'zone_id' => $zone->id, 'date' => $this->date,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 990]);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Las condiciones
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **El caso que sostiene la tanda**: sin la casilla no hay pedido. Y se asevera además que
     * **no se creó nada** — el 422 llega antes del dinero, así que ni pedido, ni aforo retenido, ni
     * ficha de admisión consumida.
     */
    public function test_without_accepting_the_terms_there_is_no_order(): void
    {
        $this->publishTerms();
        $user = $this->buyer();

        $this->actingAs($user)->postJson(self::PATH, $this->cart())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['accept_terms']]]);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, $user->consents()->where('type', Consent::TYPE_TERMS)->count());
    }

    /** Y aceptándolas se compra, con la prueba escrita: versión, fecha e IP. */
    public function test_accepting_them_creates_the_order_and_records_the_proof(): void
    {
        $this->publishTerms();
        $user = $this->buyer();

        $this->actingAs($user)->postJson(self::PATH, $this->cart(['accept_terms' => true]))
            ->assertCreated()
            ->assertValidRequest()
            ->assertValidResponse(201);

        $consent = $user->consents()->where('type', Consent::TYPE_TERMS)->sole();

        $this->assertSame('v1·es', $consent->version);
        $this->assertNotNull($consent->accepted_at);
        $this->assertNotNull($user->fresh()->terms_accepted_at);
    }

    /**
     * ⚠️ **A quien ya las tiene aceptadas NO se le piden**, que es la mitad de la decisión del owner:
     * *«se pide una vez»*. Sin este caso, la tanda podría estar pidiendo la casilla en cada compra y
     * los demás pasarían igual.
     */
    public function test_someone_who_already_accepted_the_current_version_is_not_asked_again(): void
    {
        $this->publishTerms();
        $user = $this->buyer();
        $user->consents()->create([
            'type' => Consent::TYPE_TERMS, 'accepted_at' => now(), 'ip' => '10.0.0.1', 'version' => 'v1·es',
        ]);

        $this->actingAs($user)->postJson(self::PATH, $this->cart())->assertCreated();

        // Y no se ha escrito una segunda prueba del mismo consentimiento.
        $this->assertSame(1, $user->consents()->where('type', Consent::TYPE_TERMS)->count());
    }

    /**
     * ⚠️⚠️ **Sin ninguna versión publicada NO se pide nada y la venta sigue.** El hueco falla hacia
     * invisible, como las claves de Google: una instalación recién montada no puede quedarse sin poder
     * vender porque a nadie le haya dado tiempo a pulsar «Publicar».
     */
    public function test_an_installation_without_published_terms_sells_without_asking(): void
    {
        $user = $this->buyer();

        $this->actingAs($user)->postJson(self::PATH, $this->cart())->assertCreated();

        $this->assertSame(0, $user->consents()->where('type', Consent::TYPE_TERMS)->count());
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El teléfono
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Sin teléfono no hay pedido — y con él, se guarda en la cuenta para no volver a pedirlo. */
    public function test_an_account_without_a_phone_is_asked_for_one_and_it_is_kept(): void
    {
        $user = $this->buyer(phone: null);

        $this->actingAs($user)->postJson(self::PATH, $this->cart())
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['phone']]]);

        $this->assertSame(0, Order::query()->count());

        $this->actingAs($user)->postJson(self::PATH, $this->cart(['phone' => ' 600 111 222 ']))->assertCreated();

        $this->assertSame('600 111 222', $user->fresh()->phone, 'el teléfono se guarda recortado');
    }

    /**
     * ⚠️ **A quien ya lo tiene NO se le pide, y su teléfono NO se pisa.** Es la diferencia entre
     * «pedimos lo que falta» y «pedimos siempre»: mandar uno teniendo otro no puede cambiarlo por la
     * puerta de atrás — para eso está la pantalla de datos, con su reconfirmación.
     */
    public function test_an_account_with_a_phone_is_not_asked_and_cannot_be_overwritten_from_here(): void
    {
        $user = $this->buyer(phone: '600 000 000');

        $this->actingAs($user)->postJson(self::PATH, $this->cart(['phone' => '699 999 999']))->assertCreated();

        $this->assertSame('600 000 000', $user->fresh()->phone);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Las dos a la vez, que es el caso de quien entra con Google
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * El caso real que motiva la tanda: alguien que se dio de alta con Google **no tiene ninguna de
     * las dos**, y las dos se le piden en el mismo 422 — no una, luego la otra.
     */
    public function test_a_google_signup_is_asked_for_both_at_once(): void
    {
        $this->publishTerms();
        $user = $this->buyer(phone: null);

        $this->actingAs($user)->postJson(self::PATH, $this->cart())
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['accept_terms', 'phone']]]);

        $this->actingAs($user)->postJson(self::PATH, $this->cart(['accept_terms' => true, 'phone' => '600111222']))
            ->assertCreated();
    }

    /**
     * ⚠️ **Una casilla marcada a `false` no cuela**: `accepted` exige verdad, no presencia. Sin esto,
     * un cliente que mandara el campo con `false` pasaría la validación de forma y compraría sin haber
     * aceptado nada.
     */
    public function test_sending_the_box_as_false_is_not_accepting(): void
    {
        $this->publishTerms();

        $this->actingAs($this->buyer())->postJson(self::PATH, $this->cart(['accept_terms' => false]))
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['accept_terms']]]);

        $this->assertSame(0, Order::query()->count());
    }

    // ─────────────────────────────────────────────────────────────────────────────────

    private function buyer(?string $phone = '600 000 000'): User
    {
        return User::factory()->create(['phone' => $phone, 'email_verified_at' => now()]);
    }

    private function publishTerms(): void
    {
        app(LegalDocumentPublisher::class)->publish('condiciones', [
            'es' => ['title' => 'Condiciones', 'body' => [['h' => 'Reserva', 'p' => 'Texto de las condiciones.']]],
        ]);
    }

    /** @return array<string, mixed> */
    private function cart(array $extra = []): array
    {
        return [
            'items' => [[
                'product_id' => $this->entry->id,
                'date' => $this->date,
                'time' => '10:00:00',
                'quantity' => 2,
            ]],
            ...$extra,
        ];
    }
}
