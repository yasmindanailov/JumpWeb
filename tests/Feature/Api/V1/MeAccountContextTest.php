<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerAccountContext;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

/**
 * `GET /api/v1/me/account-context` — el contexto de cuenta en un viaje
 * (`docs/specs/account-context-vue.md` §4.4).
 *
 * El endpoint no filtra nada por su cuenta: delega en `Identity\Services\CustomerAccountContext`,
 * que es el mismo servicio que alimenta el nav y el bloque de cuenta de la web desde `#221` y que a
 * su vez pide las reservas al contrato de Booking. Estos casos comprueban que la API **hereda** esas
 * reglas en vez de reimplementarlas.
 *
 * ⚠️ **Dos de ellos no son sobre conducta sino sobre CONVERGENCIA**, y son los que de verdad
 * protegen: que `next_reservation` sea byte a byte lo que publica `/me/reservations`, y que la URL
 * del post-form **nunca** sea la firmada. Ninguno de los dos fallaría por un cambio de lógica: los
 * dos fallan por un cambio de forma, que es como divergen dos superficies.
 */
class MeAccountContextTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/me/account-context';

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    public function test_it_publishes_the_context_of_the_authenticated_user(): void
    {
        $user = $this->verifiedUser('Ada Lovelace');
        $date = now()->addDays(3)->toDateString();
        $this->reservationFor($user, $this->entry('Salto 1 hora'), $date);

        $this->actingAs($user)->getJson(self::PATH)
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonPath('first_name', 'Ada')
            ->assertJsonPath('upcoming_count', 1)
            ->assertJsonPath('next_reservation.product_name', 'Salto 1 hora')
            ->assertJsonPath('next_reservation.date', $date)
            ->assertJsonPath('pending_forms', [])
            ->assertJsonPath('pending_forms_count', 0);
    }

    /**
     * ⚠️⚠️ **LA GUARDA CONTRA LA DIVERGENCIA: `next_reservation` es EXACTAMENTE un elemento de
     * `/me/reservations`.**
     *
     * Los dos salen del mismo `UpcomingReservationResource` a propósito (`AccountContextResource`
     * delega en vez de recomponer sus cuatro campos). Si alguien escribiera aquí la forma «a mano»
     * —es una tentación de dos líneas— habría **dos definiciones de reserva próxima en la misma
     * API**, incluida la etiqueta de día, y divergirían el día que alguien arregle una.
     *
     * Se comparan las dos respuestas REALES, no una idea de ellas.
     */
    public function test_the_next_reservation_is_byte_for_byte_what_me_reservations_publishes(): void
    {
        $user = $this->verifiedUser();
        $this->reservationFor($user, $this->entry('La próxima'), now()->addDays(2)->toDateString());
        $this->reservationFor($user, $this->entry('La lejana'), now()->addDays(9)->toDateString());

        $agregado = $this->actingAs($user)->getJson(self::PATH)->assertOk()->json('next_reservation');
        $lista = $this->actingAs($user)->getJson(self::ROOT.'/me/reservations')->assertOk()->json('data.0');

        $this->assertSame(
            $lista, $agregado,
            "`next_reservation` ha dejado de ser lo que publica `/me/reservations`.\n".
            "⚠️ Los dos tienen que salir del MISMO recurso. Dos formas de «reserva próxima» en la\n".
            'misma API divergen el día que alguien arregla una — empezando por la etiqueta de día.'
        );
    }

    /** Sin reservas, la forma no cambia: `null` explícito y contadores a cero. */
    public function test_without_reservations_the_shape_survives(): void
    {
        $this->actingAs($this->verifiedUser('Grace Hopper'))->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertExactJson([
                'first_name' => 'Grace',
                'upcoming_count' => 0,
                'next_reservation' => null,
                'pending_forms' => [],
                'pending_forms_count' => 0,
                'waiver' => ['mode' => 'externo', 'required' => false, 'outdated' => false, 'document_id' => null],
            ]);
    }

    /**
     * Fase 6 · waiver (`specs/waiver-probatorio.md` §4.8): «la re-firma se pide en el siguiente
     * momento natural —compra o login—». El contexto es lo que el cajón repinta al conseguir sesión,
     * así que es donde viaja si hay que firmar y qué texto.
     */
    public function test_it_says_whether_the_waiver_needs_signing_and_which_text(): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
        $document = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
        $user = $this->verifiedUser('Grace Hopper');

        $this->actingAs($user)->getJson(self::PATH)->assertOk()->assertValidResponse(200)
            ->assertJsonPath('waiver', ['mode' => 'interno', 'required' => true, 'outdated' => false, 'document_id' => $document->id]);

        app(WaiverSigner::class)->sign($user, $document, WaiverSignatureRequest::web('10.0.0.1', 'test'));
        // El servicio es un singleton memoizado POR PETICIÓN; en el test las tres peticiones
        // comparten contenedor, así que se olvida la instancia entre una y otra.
        app()->forgetInstance(CustomerAccountContext::class);
        $this->actingAs($user)->getJson(self::PATH)->assertOk()
            ->assertJsonPath('waiver.required', false)
            ->assertJsonPath('waiver.outdated', false);

        $v2 = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Texto nuevo.']]],
        ])->first();
        app()->forgetInstance(CustomerAccountContext::class);
        $this->actingAs($user)->getJson(self::PATH)->assertOk()
            ->assertJsonPath('waiver', ['mode' => 'interno', 'required' => false, 'outdated' => true, 'document_id' => $v2->id]);
    }

    /** Un pack sin rellenar publica su formulario pendiente, con producto y destino. */
    public function test_it_publishes_the_pending_guest_forms(): void
    {
        $user = $this->verifiedUser();
        $item = $this->reservationFor($user, $this->pack('Cumpleaños Jump'), now()->addDays(4)->toDateString());

        $this->actingAs($user)->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('pending_forms_count', 1)
            ->assertJsonPath('pending_forms.0.product_name', 'Cumpleaños Jump')
            ->assertJsonPath('pending_forms.0.url', route('reservation.guests', $item->id));
    }

    /**
     * ⚠️⚠️ **LA URL DEL POST-FORM VA SIN FIRMAR, Y ESTE CASO EXISTE PARA QUE SIGA ASÍ.**
     *
     * Publicar aquí `OrderItem::guestFormSignedUrl()` es un cambio de una línea que **no rompería
     * nada visible**: el enlace seguiría funcionando, mejor incluso. Lo que haría es acuñar una
     * credencial PORTADORA —sin sesión, válida durante días— que abre y **reescribe** nombres y
     * alergias de menores (art. 9, `RGPD-03`)… en un agregado que además se siembra en el HTML de
     * cada página con sesión.
     *
     * Lo que protege la ruta plana es la titularidad: `Http\Concerns\AuthorizesGuestForm` acepta
     * firma **o** dueño autenticado, con su escalada 403 → 410 → 404.
     */
    public function test_the_guest_form_url_is_never_a_signed_credential(): void
    {
        $user = $this->verifiedUser();
        $item = $this->reservationFor($user, $this->pack('Cumpleaños Jump'), now()->addDays(4)->toDateString());

        $url = (string) $this->actingAs($user)->getJson(self::PATH)->json('pending_forms.0.url');

        $this->assertSame(route('reservation.guests', $item->id), $url);
        $this->assertStringNotContainsString(
            'signature=', $url,
            "Se está publicando una URL FIRMADA del post-form.\n".
            "⚠️ Eso es una credencial portadora que abre y REESCRIBE datos de salud de un menor sin\n".
            'sesión y durante días — y esta misma respuesta se siembra en el HTML de cada página.'
        );
        $this->assertStringNotContainsString('expires=', $url);
    }

    /** Regla heredada del dominio: un pack ya celebrado deja de avisar. La API no la reimplementa. */
    public function test_a_finished_pack_no_longer_pends(): void
    {
        $user = $this->verifiedUser();
        $item = $this->reservationFor($user, $this->pack('El de ayer'), now()->subDays(2)->toDateString());
        $item->slot->update(['end_time' => '11:00:00']);

        $this->actingAs($user)->getJson(self::PATH)
            ->assertOk()
            ->assertJsonPath('pending_forms_count', 0)
            ->assertJsonPath('upcoming_count', 0);
    }

    /**
     * ⚠️⚠️ **Titularidad: el titular sale del guard, nunca de la petición — y esto lo INTENTA.**
     *
     * La primera versión de este caso se limitaba a comprobar que Ada veía lo de Ada, y **medido por
     * mutación no discriminaba**: un controlador que aceptara `?user=` para elegir titular la pasaba
     * entera, porque el caso nunca mandaba ese parámetro. Una guarda de seguridad que no intenta el
     * ataque solo comprueba que el camino feliz funciona.
     *
     * ▶ Por eso ahora se pide el contexto **suplantando a Grace por todas las vías plausibles** —query
     * y cuerpo— y se exige que la respuesta siga siendo la de Ada.
     */
    public function test_it_never_leaks_another_users_context(): void
    {
        $ada = $this->verifiedUser('Ada Lovelace');
        $grace = $this->verifiedUser('Grace Hopper');
        $this->reservationFor($grace, $this->pack('El pack de Grace'), now()->addDays(3)->toDateString());

        $intentos = [
            'sin suplantar' => self::PATH,
            'con ?user=' => self::PATH.'?user='.$grace->id,
            'con ?user_id=' => self::PATH.'?user_id='.$grace->id,
            'con ?id=' => self::PATH.'?id='.$grace->id,
        ];

        foreach ($intentos as $via => $url) {
            $response = $this->actingAs($ada)->getJson($url)->assertOk();

            $response->assertJsonPath('first_name', 'Ada')
                ->assertJsonPath('upcoming_count', 0)
                ->assertJsonPath('next_reservation', null)
                ->assertJsonPath('pending_forms_count', 0);

            $this->assertStringNotContainsString(
                'El pack de Grace', (string) $response->getContent(),
                "Se ha filtrado el contexto de otro titular «{$via}». La única fuente de identidad ".
                'de este endpoint es el guard: no acepta identificadores por la petición.'
            );
        }
    }

    public function test_it_rejects_an_anonymous_request(): void
    {
        $this->getJson(self::PATH)
            ->assertUnauthorized()
            ->assertValidResponse(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    /**
     * ⚠️ **No exige el correo verificado**, y es deliberado: el alta *pay-first* del embudo abre
     * sesión sin verificar, y ese cliente también tiene bloque de cuenta que pintar. Mismo criterio
     * que `/me` y `/me/reservations`. Si algún día se le añadiera `verified`, este caso lo diría.
     */
    public function test_an_unverified_customer_still_gets_their_context(): void
    {
        $user = User::factory()->create(['name' => 'Sin Verificar', 'email_verified_at' => null]);

        $this->actingAs($user)->getJson(self::PATH)
            ->assertOk()
            ->assertJsonPath('first_name', 'Sin');
    }

    /** `RGPD-04`: el cuerpo lleva nombre de pila, producto de una reserva y las URLs de sus post-forms. */
    public function test_the_response_is_not_stored(): void
    {
        $response = $this->actingAs($this->verifiedUser())->getJson(self::PATH);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────────────────────

    private function verifiedUser(string $name = 'Ada Lovelace'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => now()]);
    }

    private function entry(string $name): TicketType
    {
        return $this->ticketType($name, TicketType::TYPE_ENTRY, null);
    }

    /** Un pack con `guest_fields` → sus reservas piden post-form (#217). */
    private function pack(string $name): TicketType
    {
        return $this->ticketType($name, TicketType::TYPE_PACK, [
            ['key' => 'nombre', 'label' => ['es' => 'Nombre'], 'type' => 'text', 'phase' => 'booking', 'required' => true],
        ]);
    }

    private function ticketType(string $name, string $type, ?array $guestFields): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => $type,
            'zone_id' => $this->zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true,
            'guest_fields' => $guestFields,
            'position' => (int) TicketType::max('position') + 1,
        ]);
    }

    private function reservationFor(User $user, TicketType $type, string $date): OrderItem
    {
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);

        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => '10:00:00', 'end_time' => '23:00:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);

        return $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'parent_item_id' => null,
            'quantity' => 2, 'seats' => 2, 'unit_price' => 1000,
        ]);
    }
}
