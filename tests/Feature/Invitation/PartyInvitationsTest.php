<?php

namespace Tests\Feature\Invitation;

use App\Domain\Booking\Contracts\InvitationReplyOutcome;
use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **Las reglas del dominio de la invitación digital** (T4·2 de
 * `docs/specs/celebracion-e-invitacion.md` §4.5; `DECISIONES #574`).
 *
 * Lo que vigila, en orden de importancia:
 *
 *  1. **Que la hoja siga en blanco.** Un nombre repetido se acepta **en silencio** y con el mismo
 *     desenlace que la primera vez: cualquier otra cosa le confirmaría a quien tenga el enlace —que
 *     se reparte a un grupo de clase entero— quién va a esa fiesta (V6, §7.2·R1).
 *  2. **Que un padre no pueda meter a nadie cuando la lista está completa** (D2), re-comprobado BAJO
 *     EL LOCK y no solo al pintar (`SEC-04`).
 *  3. **Que el prerrelleno se lea por la REGLA y no por una clave quemada**: la edad por su TIPO y el
 *     nombre por la primera columna de texto — medido, porque la spec decía otra cosa.
 *  4. **Que el rastro no lleve PII** (`RGPD-02`).
 */
class PartyInvitationsTest extends TestCase
{
    use RefreshDatabase;

    private const VISIT = '2026-11-14';

    /** Una hora cualquiera muy anterior al plazo de la visita, para no rozar el corte sin querer. */
    private const WELL_BEFORE = '2026-10-01 12:00:00';

    // ─── Nace sola, y con lo que el anfitrión ya escribió ──────────────────────

    public function test_the_invitation_is_born_with_the_form_and_only_once(): void
    {
        $reservation = $this->reservation();

        $first = $this->service()->forReservation($reservation);
        $second = $this->service()->forReservation($reservation);

        $this->assertNotNull($first);
        $this->assertTrue($first->is($second), 'una reserva tiene UNA invitación, y abrir dos veces no crea dos');
        $this->assertSame(1, PartyInvitation::query()->count());
        $this->assertSame(PartyInvitation::TOKEN_LENGTH, mb_strlen((string) $first->token));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', (string) $first->token, 'el token es base62 opaco');
    }

    public function test_a_product_that_does_not_offer_it_has_no_invitation(): void
    {
        $reservation = $this->reservation(offersInvitation: false);

        $this->assertNull($this->service()->forReservation($reservation));
        $this->assertSame(0, PartyInvitation::query()->count());
    }

    /**
     * Sin columna de nombre no hay con qué emparejar: el interruptor no se sostiene solo.
     *
     * ⚠️⚠️ **La fila se fuerza POR DEBAJO del modelo (`DB::table`), y no es un atajo.** Desde la T4·3
     * (`#575`) el guard de `TicketType::saving()` **impide guardar esta combinación desde Eloquent** —
     * este caso la creaba con `save()` y el guard lo puso en rojo, que es exactamente su trabajo—. Lo
     * que queda por probar es el camino que el guard NO alcanza: una importación o una migración de
     * datos. Y lo que se comprueba es que el servicio tampoco se fía de esa fila.
     */
    public function test_without_a_name_column_there_is_no_invitation(): void
    {
        $reservation = $this->reservation();

        DB::table('ticket_types')->where('id', $reservation->ticket_type_id)->update([
            'guest_fields' => json_encode([
                ['key' => 'age', 'type' => 'age', 'required' => false, 'label' => ['es' => 'Edad']],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->assertNull($this->service()->forReservation($reservation->fresh(['ticketType'])));
        $this->assertSame(0, PartyInvitation::query()->count());
    }

    /**
     * ⚠️⚠️ **Medido, y corrige a la spec**: decía que el prerrelleno sale «por las claves `celebrant` y
     * `age`». La EDAD sí tiene lector canónico por TIPO (`celebrant_age`), pero el nombre no: aquí se
     * lee por la primera columna `text`, la misma regla que §7.2·R2 fijó para el esquema por invitado.
     * El fixture nombra sus claves **al revés de la convención** justo para que una lectura por clave
     * quemada falle.
     */
    public function test_the_prefill_is_read_by_the_rule_and_not_by_a_hardcoded_key(): void
    {
        $reservation = $this->reservation(eventFields: [
            ['key' => 'quien_cumple', 'type' => 'text', 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Homenajeado']],
            ['key' => 'los_anios', 'type' => 'celebrant_age', 'required' => false, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Edad']],
        ], eventData: ['quien_cumple' => 'Lucía', 'los_anios' => '8']);

        $invitation = $this->service()->forReservation($reservation);

        $this->assertSame('Lucía', $invitation->honoree_name);
        $this->assertSame(8, $invitation->honoree_age);
        $this->assertSame('Marta Anfitriona', $invitation->host_line, 'la línea de «te invita» sale del nombre de la cuenta');
        $this->assertFalse($invitation->show_host_phone, 'el teléfono solo si el anfitrión lo marca (D13)');
    }

    /** `SEC-07`: lo que se publica bajo el dominio del parque no puede traer un enlace. */
    public function test_a_celebrant_name_with_a_link_is_not_published(): void
    {
        $reservation = $this->reservation(eventData: ['celebrant' => 'Paga el regalo en https://cobro.example']);

        $this->assertSame('', $this->service()->forReservation($reservation)->honoree_name);
    }

    // ─── Compartir ────────────────────────────────────────────────────────────

    public function test_it_is_not_shareable_until_the_host_names_the_honoree(): void
    {
        $reservation = $this->reservation(eventData: []);
        $invitation = $this->service()->forReservation($reservation);

        $this->assertFalse($this->service()->isShareable($reservation, $invitation));

        $invitation->forceFill(['honoree_name' => 'Lucía'])->save();
        $this->assertTrue($this->service()->isShareable($reservation, $invitation));
    }

    /**
     * ⚠️ **Pasado el plazo SE SIGUE COMPARTIENDO** (§7.2·R8), y es lo contrario de lo que la primera
     * versión de la spec decía: la información de la fiesta hace falta **el día de la fiesta**.
     */
    public function test_past_the_deadline_it_is_still_shareable_even_though_replies_close(): void
    {
        $reservation = $this->reservation();
        $invitation = $this->service()->forReservation($reservation);

        // Dentro del día de la visita: el plazo de respuestas (24 h antes) ya venció.
        $this->travelTo(Carbon::parse(self::VISIT.' 09:00:00', 'Europe/Madrid'));

        $this->assertTrue($this->service()->isShareable($reservation->fresh(['ticketType', 'slot', 'order']), $invitation));
        $this->assertSame(
            InvitationReplyOutcome::REASON_CUTOFF,
            $this->service()->reply($invitation, 'Hugo Ruiz', true)->reason,
            'lo que el plazo cierra son las RESPUESTAS, no el enlace',
        );
    }

    // ─── Contestar ────────────────────────────────────────────────────────────

    public function test_a_name_that_normalises_to_nothing_is_refused(): void
    {
        $invitation = $this->invitation();

        $this->assertSame(InvitationReplyOutcome::REASON_NO_NAME, $this->service()->reply($invitation, '   ', true)->reason);
        $this->assertSame(0, InvitationReply::query()->count());
    }

    public function test_a_yes_takes_a_place_and_is_recorded(): void
    {
        $invitation = $this->invitation();

        $outcome = $this->service()->reply($invitation, 'Hugo Ruiz', true, InvitationReply::COMPANION_WITH_ADULT);

        $this->assertTrue($outcome->accepted);
        $this->assertFalse($outcome->joinedExistingPlace);
        $this->assertSame('Hugo Ruiz', $outcome->reply->child_name);
        $this->assertSame(InvitationReply::COMPANION_WITH_ADULT, $outcome->reply->companion);
        $this->assertTrue($outcome->reply->isPending(), 'nace por repasar: la adopta el anfitrión');
    }

    /**
     * ❗❗ **LA PRUEBA QUE CIERRA EL ORÁCULO DE PERTENENCIA** (`[DECIDIDO owner, 2026-09-18]`,
     * `DECISIONES #520`; sustituye a D2 de `#569`).
     *
     * Con la lista completa, un nombre que **ya está** en ella y uno **nuevo** tienen que recibir
     * exactamente el mismo desenlace. Hasta `#520` no era así: el que emparejaba se aceptaba y el
     * nuevo recibía `full`, así que cualquiera con el enlace —repartido a un grupo de clase entero—
     * podía **reconstruir la lista de invitados probando nombres**. Lo encontró la revisión
     * adversarial de `#579`, y es la misma fuga que el motivo «repetido» ya tenía cerrada.
     *
     * ⚠️ Se asevera sobre los DOS campos a la vez y con el mismo fixture: comparar solo `accepted`
     * dejaría pasar un `reason` distinto, que es exactamente la rendija por la que se filtraba.
     */
    public function test_with_a_full_list_a_known_name_and_a_new_one_answer_the_same(): void
    {
        $invitation = $this->invitation(quantity: 2, guests: [['name' => 'Ana Gil'], ['name' => 'Leo Sanz']]);

        // «Ana Gil» ESTÁ en la lista; «Hugo Ruiz» no. Los dos con la lista completa.
        $conocido = $this->service()->reply($invitation, 'Ana Gil', true);
        $nuevo = $this->service()->reply($invitation, 'Hugo Ruiz', true);

        $this->assertSame(
            [$conocido->accepted, $conocido->reason],
            [$nuevo->accepted, $nuevo->reason],
            'el desenlace distingue quién está en la lista: es un oráculo de pertenencia'
        );
        $this->assertTrue($nuevo->accepted, 'la lista completa ya no rechaza (#520)');
        $this->assertNull($nuevo->reason);

        // Las dos se guardan: el que no cabe lo resuelve el ANFITRIÓN, no el servidor.
        $this->assertSame(2, InvitationReply::query()->count());
    }

    /**
     * ⚠️ Y lo que NO cambia: el que no cabe **ocupa plaza nueva**, así que el suelo de `#444` sube por
     * encima de la cantidad y el anfitrión no puede bajar invitados hasta resolverlo. Es la mitad que
     * hace honesto el cambio — aceptar en silencio sin que nadie se entere sí sería un defecto.
     */
    public function test_a_yes_that_does_not_fit_still_takes_a_place_of_its_own(): void
    {
        $invitation = $this->invitation(quantity: 2, guests: [['name' => 'Ana Gil'], ['name' => 'Leo Sanz']]);

        $outcome = $this->service()->reply($invitation, 'Hugo Ruiz', true);

        $this->assertTrue($outcome->accepted);
        $this->assertFalse(
            $outcome->joinedExistingPlace,
            'se unió a una plaza ajena: entonces no saldría en el aviso de «no caben»'
        );
    }

    /** D3: un «no» NUNCA ocupa, así que se recoge aunque la lista esté completa. */
    public function test_a_no_is_always_recorded_even_with_a_full_list(): void
    {
        $invitation = $this->invitation(quantity: 2, guests: [['name' => 'Ana Gil'], ['name' => 'Leo Sanz']]);

        $outcome = $this->service()->reply($invitation, 'Hugo Ruiz', false);

        $this->assertTrue($outcome->accepted);
        $this->assertFalse((bool) $outcome->reply->attending);
    }

    /** D11: empareja con una ficha ya escrita → esa plaza ya tiene dueño y es el mismo niño. */
    public function test_a_yes_that_matches_a_written_guest_fits_even_when_full(): void
    {
        $invitation = $this->invitation(quantity: 2, guests: [['name' => 'Ana Gil'], ['name' => 'Leo Sanz']]);

        $outcome = $this->service()->reply($invitation, 'ANA  gil', true);

        $this->assertTrue($outcome->accepted, 'la clave normalizada es la misma persona');
        $this->assertTrue($outcome->joinedExistingPlace);
    }

    /**
     * Y por la PRIMERA PALABRA, que es el caso real: el anfitrión pegó «Mateo» con el pegado de la T2
     * y el padre escribe «Mateo Ruiz», con apellidos porque es lo que se le pide.
     */
    public function test_a_yes_matches_a_guest_written_with_only_the_first_name(): void
    {
        $invitation = $this->invitation(quantity: 2, guests: [['name' => 'Mateo'], ['name' => 'Leo Sanz']]);

        $outcome = $this->service()->reply($invitation, 'Mateo Ruiz', true);

        $this->assertTrue($outcome->accepted);
        $this->assertTrue($outcome->joinedExistingPlace);
    }

    /**
     * ⚠️⚠️ **La columna de nombre se lee por la REGLA, no por la clave `name`** (§7.2·R2).
     *
     * El resto del fichero usa el esquema por defecto, donde la primera columna `text` **se llama**
     * `name` — así que una lectura por clave quemada pasaría todos esos casos. Lo enseñó el arnés de
     * mutación al diseñarlo: no había ningún mutante que pudiera morder ahí. Aquí el esquema la llama
     * `como_se_llama`, que es lo que haría una instalación que renombra su columna desde el panel.
     */
    public function test_the_guest_name_column_is_read_by_the_rule_even_when_renamed(): void
    {
        $reservation = $this->reservation(quantity: 2, guests: [['como_se_llama' => 'Mateo'], ['como_se_llama' => 'Leo Sanz']], guestFields: [
            ['key' => 'como_se_llama', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
            ['key' => 'alergia', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Alergia']],
        ]);
        $invitation = $this->service()->forReservation($reservation);

        // Empareja con la ficha renombrada → cabe aunque la lista esté completa.
        $matched = $this->service()->reply($invitation, 'Mateo Ruiz', true);
        $this->assertTrue($matched->accepted);
        $this->assertTrue($matched->joinedExistingPlace, 'la ficha se leyó: si no, esto habría ocupado plaza nueva');

        // Y el CONTRASTE, que es lo que hace útil la aserción de arriba: un nombre que NO está en
        // ninguna ficha **ocupa plaza propia**. Si la columna renombrada no se leyera, los dos
        // caminos darían lo mismo y el caso pasaría sin probar nada.
        //
        // ⚠️ El control era `full` hasta `#520`; ese motivo ya no existe —era un oráculo de
        // pertenencia— y lo que queda para distinguirlos es la plaza, que el padre no ve.
        $this->assertFalse(
            $this->service()->reply($invitation, 'Ana Gil', true)->joinedExistingPlace,
            'un nombre ajeno a las fichas se unió a una plaza existente: la columna no se está leyendo'
        );
    }

    /**
     * ⚠️⚠️ **V6 · §7.2·R1 — el caso que sostiene la privacidad de la feature.** Rechazar el repetido, o
     * decir «ya nos habéis contestado por Hugo», le confirmaría a cualquiera con el enlace que Hugo va
     * a esa fiesta: bastaba con probar nombres. Se acepta, **con el mismo desenlace que la primera
     * vez**, y no ocupa plaza nueva.
     */
    public function test_a_repeated_name_is_accepted_in_silence_with_the_very_same_outcome(): void
    {
        $invitation = $this->invitation(quantity: 2);

        $first = $this->service()->reply($invitation, 'Hugo Ruiz', true);
        $second = $this->service()->reply($invitation, 'hugo  RUIZ', true);

        $this->assertTrue($first->accepted);
        $this->assertTrue($second->accepted, 'el segundo ve exactamente lo mismo que el primero');
        $this->assertNull($second->reason);
        $this->assertTrue($second->joinedExistingPlace, 'se une a la propuesta del primero: no ocupa plaza nueva');
        $this->assertSame(2, InvitationReply::query()->count(), 'las dos quedan, y las resuelve el anfitrión');

        // Y el repetido NO consumió plaza: con la que queda libre entra otro niño y el suelo sigue
        // contando DOS, no tres.
        //
        // ⚠️ Se mide sobre el suelo —los ids distintos por niño— y ya no con `full`, que desde `#520`
        // no existe: era la otra mitad del mismo oráculo que este caso existe para cerrar.
        $this->assertTrue($this->service()->reply($invitation, 'Leo Sanz', true)->accepted);
        $this->assertCount(
            2,
            app(PartyGuests::class)->committedReplyIdsIn((int) $invitation->order_item_id),
            'el repetido ocupó una plaza propia: son dos niños, no tres'
        );
    }

    /**
     * El tope anti-spam de §4.5·12: `3 × invitados`, que frena el «no» repetido —que no ocupa plaza y
     * por tanto no lo para la lista completa— sin castigar a una familia que se equivoca y rectifica.
     *
     * ⚠️⚠️ **El número va LITERAL, y la primera versión de este caso no lo hacía.** Calculaba el tope
     * leyendo `REPLY_CAP_PER_GUEST`, o sea **la constante que tenía que vigilar**: subirla a 300 hacía
     * que el bucle diera 300 vueltas y el caso siguiera en verde. Lo cazó el arnés de mutación. *Un
     * test que se calcula su expectativa desde el código bajo prueba no prueba ese código.*
     */
    public function test_an_invitation_stops_taking_replies_past_its_cap(): void
    {
        $this->assertSame(3, PartyInvitations::REPLY_CAP_PER_GUEST, 'el tope decidido son 3 por invitado');

        $invitation = $this->invitation(quantity: 1);

        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($this->service()->reply($invitation, 'Niño '.$i, false)->accepted);
        }

        $this->assertSame(InvitationReplyOutcome::REASON_TOO_MANY, $this->service()->reply($invitation, 'Uno más', false)->reason);
    }

    public function test_a_cancelled_reservation_takes_no_replies(): void
    {
        $invitation = $this->invitation();
        $invitation->reservation->forceFill(['cancelled_at' => now()])->save();

        $this->assertSame(InvitationReplyOutcome::REASON_CLOSED, $this->service()->reply($invitation, 'Hugo Ruiz', true)->reason);
    }

    /** `RGPD-02`: el rastro dice qué pasó, nunca de quién. */
    public function test_the_audit_trail_never_carries_the_child_name(): void
    {
        $invitation = $this->invitation();

        $this->service()->reply($invitation, 'Hugo Ruiz', true);

        $log = AuditLog::query()->where('action', 'orders.invitation_reply_received')->sole();
        $payload = json_encode($log->payload, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('Hugo', (string) $payload);
        $this->assertStringNotContainsString('Ruiz', (string) $payload);
        $this->assertTrue($log->payload['attending']);
    }

    // ─── Fixtures ─────────────────────────────────────────────────────────────

    private function service(): PartyInvitations
    {
        return app(PartyInvitations::class);
    }

    /** Una invitación viva sobre una reserva con `$quantity` invitados y sus fichas ya escritas. */
    private function invitation(int $quantity = 4, array $guests = []): PartyInvitation
    {
        $reservation = $this->reservation(quantity: $quantity, guests: $guests);

        return $this->service()->forReservation($reservation);
    }

    private function reservation(
        bool $offersInvitation = true,
        int $quantity = 4,
        array $guests = [],
        ?array $eventFields = null,
        ?array $eventData = null,
        ?array $guestFields = null,
    ): OrderItem {
        $this->travelTo(Carbon::parse(self::WELL_BEFORE, 'UTC'));

        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::create([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Cumpleaños'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 1, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_invitation' => $offersInvitation,
            'guest_fields' => $guestFields ?? TicketType::DEFAULT_GUEST_FIELDS,
            'event_fields' => $eventFields ?? [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Homenajeado']],
                ['key' => 'celebrant_age', 'type' => 'celebrant_age', 'required' => false, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Edad']],
            ],
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => self::VISIT,
            'start_time' => '17:00:00', 'end_time' => '18:00:00', 'capacity' => 200, 'online_capacity' => 200,
        ]);
        $order = Order::create([
            'user_id' => User::factory()->create(['name' => 'Marta Anfitriona'])->id,
            'code' => 'R-'.mb_strtoupper(mb_substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);

        return $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => $quantity, 'unit_price' => 500, 'seats' => $quantity,
            'event_data' => $eventData ?? ['celebrant' => 'Lucía', 'celebrant_age' => '8'],
            'guest_data' => $guests,
        ]);
    }
}
