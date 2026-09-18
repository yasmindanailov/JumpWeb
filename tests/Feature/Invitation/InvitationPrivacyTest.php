<?php

namespace Tests\Feature\Invitation;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\AccountPrivacy;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverChain;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL RGPD DE LA INVITACIÓN DIGITAL** (T4·5 de `docs/specs/celebracion-e-invitacion.md` §4.4;
 * `DECISIONES #577`). `RGPD-01` (supresión), `RGPD-02` (el rastro) y el export del art. 20.
 *
 * ❗❗ **El agujero que esto cierra es el peor de su clase, porque era SILENCIOSO y estaba al revés**:
 * `anonymize()` lleva desde la Fase 1 vaciando `guest_data`/`event_data` —los nombres y las alergias de
 * los invitados—, y la invitación digital volvía a guardar esa misma clase de dato en dos tablas que la
 * supresión no miraba. El titular borraba su cuenta, el nombre del homenajeado seguía en pie y **nada
 * fallaba**. El art. 17 no se incumple con un error ruidoso: se incumple con una tabla nueva que nadie
 * recordó.
 *
 * **Los cuatro regímenes que este fichero separa**, y por qué no son el mismo:
 *  1. **Las respuestas y la invitación se BORRAN** con la supresión: son PII de menores —de otras
 *     familias— cuya única justificación era organizar una fiesta que ya no tiene dueño.
 *  2. ⚠️⚠️ **El JUSTIFICANTE se CONSERVA** (art. 17.3.e, `RGPD-01`): prueba que un adulto autorizó la
 *     entrada de un menor a una visita que ocurrió, y no es del responsable para que él la borre. Solo
 *     pierde el puntero a la respuesta — y **la cadena tiene que seguir verificando**. Éste es el caso
 *     que ordena la tanda: si el vínculo entrara en el hash, la supresión de un titular rompería la
 *     prueba de otra familia.
 *  3. **La purga de go-live se las lleva por cascada**, y eso se comprueba en vez de suponerse: una FK
 *     mal puesta no rompe nada hasta el día en que la purga corre en producción.
 *  4. **El export del art. 20 NO las lleva**: son datos de terceros. Es la misma doctrina que ya deja
 *     `guest_data` fuera del export mientras `event_data` va entero (medido en
 *     `CustomerOrderHistoryReader::line()`).
 */
class InvitationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    // ─── 1 · La supresión borra lo que los padres contestaron ─────────────────

    public function test_deleting_the_account_takes_the_invitation_and_every_reply(): void
    {
        [$host, $item, $invitation] = $this->partyWithReplies();

        $this->assertSame(2, InvitationReply::query()->count(), 'el fixture no montó las respuestas');

        $host->anonymize();

        $this->assertSame(0, InvitationReply::query()->where('order_item_id', $item->id)->count());
        $this->assertSame(0, PartyInvitation::query()->where('order_item_id', $item->id)->count());

        // Y el pedido SIGUE: el deber fiscal cubre la factura, no la lista de invitados.
        $this->assertNotNull($item->fresh(), 'la reserva se conserva; lo que se va es la PII');
    }

    /**
     * CONTROL de la supresión. Sin él, un `delete()` sin `where` pasaría los dos casos de arriba: se
     * habría llevado también las fiestas de todos los demás y el test lo llamaría un éxito.
     */
    public function test_deleting_one_account_never_touches_another_hosts_party(): void
    {
        [$host] = $this->partyWithReplies();
        [, $otherItem] = $this->partyWithReplies();

        $host->anonymize();

        $this->assertSame(2, InvitationReply::query()->where('order_item_id', $otherItem->id)->count());
        $this->assertSame(1, PartyInvitation::query()->where('order_item_id', $otherItem->id)->count());
    }

    /*
     * ⚠️⚠️ **LO QUE ESTE FICHERO NO PUEDE MEDIR, Y SE DICE EN VEZ DE FINGIRSE.**
     *
     * `anonymize()` borra las respuestas **y** la invitación, dos consultas, para no depender de la
     * cascada — la misma lección que `user_identities` tiene escrita en esa función: un
     * `cascadeOnDelete` que por esa vía **no se dispara nunca**.
     *
     * Con las FK activas la primera consulta es redundante (al caer la invitación, sus respuestas
     * caen), así que **su mutación no muerde** — y eso está declarado en `mutar-invitacion-t45.sh`,
     * no escondido. Aquí hubo un caso que pretendía ejercerla apagando las claves foráneas, y
     * **medido, no las apagaba**: `Schema::withoutForeignKeyConstraints()` es un no-op dentro de una
     * transacción y `RefreshDatabase` abre una. Habría sido una guarda verde para siempre sin medir
     * nada; se retiró en cuanto comprobó su propio instrumento.
     *
     * ▶ La propiedad que haría falta esa consulta —que la cascada exista— **ya está vigilada** por
     * `PartyInvitationSchemaTest::test_deleting_the_invitation_takes_its_replies` (T4·1). La consulta
     * se queda como cinturón; no necesita una guarda propia que mentiría.
     */

    // ─── 2 · Lo que NO se borra, y su prueba sigue en pie ─────────────────────

    /**
     * ⚠️⚠️ **El caso que ordena la tanda.** El justificante de un menor invitado **sobrevive** a la
     * supresión del responsable (`RGPD-01`: no es suyo para borrarlo, art. 17.3.e), y al caer la
     * respuesta a la que estaba atado solo pierde el puntero (`nullOnDelete`).
     *
     * Lo que se asevera es que **la cadena sigue verificando**: si `invitation_reply_id` entrara en el
     * hash de la firma, borrar la cuenta del anfitrión dejaría ROTA la prueba de la visita de un menor
     * de otra familia. Es exactamente la lección del `SET NULL` de `waiver-por-reserva.md` §10, y aquí
     * queda ejercida en vez de escrita.
     */
    public function test_the_guest_minor_waiver_survives_and_its_chain_still_verifies(): void
    {
        [$host, $item, $invitation] = $this->partyWithReplies();

        $reply = InvitationReply::query()->where('order_item_id', $item->id)->firstOrFail();
        $signed = app(GuardianAuthorizationSigner::class)->sign(
            $host,
            (int) $item->id,
            $this->version(),
            [
                'minor_name' => 'Hugo', 'minor_surname' => 'Ruiz Pla', 'minor_born_on' => '2018-05-04',
                'guardian_name' => 'Marta', 'guardian_surname' => 'Pla Gil', 'guardian_relationship' => 'mother',
                'guardian_email' => 'marta@example.com', 'guardian_phone' => '600111222',
            ],
            WaiverSignatureRequest::web('10.0.0.9', 'Mozilla/5.0 (padre)'),
            (int) $reply->getKey(),
        );

        $authorizationId = (int) $signed['authorization']->getKey();
        $this->assertSame(
            (int) $reply->getKey(),
            (int) $signed['authorization']->invitation_reply_id,
            'el fixture no ató la firma a la respuesta: el caso no probaría nada'
        );

        $host->anonymize();

        $survivor = GuardianAuthorization::query()->find($authorizationId);
        $this->assertNotNull($survivor, 'la prueba de una visita que ocurrió no es del responsable');
        $this->assertNull($survivor->invitation_reply_id, 'el vínculo cae con la respuesta (nullOnDelete)');
        $this->assertSame(1, WaiverSignature::query()->count(), 'la firma sigue ahí');

        $verdict = WaiverChain::verify($host);
        $this->assertTrue($verdict['ok'], 'la supresión del anfitrión dejó ROTA la cadena de otra familia');
    }

    // ─── 3 · La purga de go-live ──────────────────────────────────────────────

    /**
     * La purga borra los pedidos **por tabla** (§1.3·11) y confía en la cascada para lo que cuelga. La
     * spec lo da por hecho en una línea; aquí se comprueba, porque una FK en `RESTRICT` no rompe nada
     * hasta el día en que la purga corre en producción — y ese día ya no hay a quien preguntar.
     */
    public function test_the_go_live_purge_takes_the_invitations_with_the_orders(): void
    {
        [, $item] = $this->partyWithReplies();
        $admin = User::factory()->create(['email' => 'duena@jumpweb.test']);
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin']));

        $this->artisan('app:purge-customers', ['--keep' => ['duena@jumpweb.test'], '--force' => true])
            ->assertExitCode(0);

        $this->assertSame(0, PartyInvitation::query()->count(), 'la cascada no se las llevó');
        $this->assertSame(0, InvitationReply::query()->count());
        $this->assertSame(0, OrderItem::query()->count(), 'el control: la purga sí borró los pedidos');
    }

    // ─── 4 · El export del art. 20 ────────────────────────────────────────────

    /**
     * ⚠️ **Lo que un padre contestó NO es dato del anfitrión.** El art. 20 es portabilidad de LO SUYO:
     * `event_data` va entero (es de él y de su hijo) y `guest_data` **no va**, porque son nombres y
     * alergias de niños de otras familias. Las respuestas de la invitación son esa misma clase de dato
     * y siguen la misma regla.
     *
     * Se asevera sobre el JSON entero y por el nombre del niño, no por una clave: una clave nueva que
     * lo publicara con otro nombre pasaría un test que solo mirase `$export['orders'][0]['replies']`.
     */
    public function test_the_article_20_export_never_carries_what_other_parents_answered(): void
    {
        [$host] = $this->partyWithReplies();

        $export = app(AccountPrivacy::class)->exportFor($host->fresh());
        $json = json_encode($export, JSON_UNESCAPED_UNICODE) ?: '';

        $this->assertStringNotContainsString('Hugo Ruiz', $json, 'un niño invitado no es un dato portable del anfitrión');
        $this->assertStringNotContainsString('Lía Fernández', $json);
        $this->assertStringContainsString('Lucía', $json, 'el control: lo SUYO sí se exporta (event_data)');
    }

    // ─── Fixtures ─────────────────────────────────────────────────────────────

    private function version(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    /**
     * Una fiesta con invitación y DOS respuestas de padres distintos.
     *
     * @return array{0: User, 1: OrderItem, 2: PartyInvitation}
     */
    private function partyWithReplies(): array
    {
        $host = User::factory()->create(['email_verified_at' => now()]);

        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::firstOrCreate(
            ['zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK],
            [
                'name' => ['es' => 'Cumpleaños'], 'duration_min' => 120, 'seats_per_unit' => 1,
                'min_qty' => 1, 'max_qty' => 20, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
                'guest_invitation' => true,
                'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
                'event_fields' => [
                    ['key' => 'celebrant', 'type' => 'text', 'required' => true,
                        'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Homenajeado']],
                ],
            ]
        );
        $slot = Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => now()->addMonth()->toDateString(), 'start_time' => '17:00:00'],
            ['end_time' => '18:00:00', 'capacity' => 200, 'online_capacity' => 200],
        );

        $order = Order::create([
            'user_id' => $host->id,
            'code' => 'R-'.mb_strtoupper(mb_substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 6, 'unit_price' => 500, 'seats' => 6,
            // El control del export: el nombre del homenajeado es dato del titular y SÍ se exporta.
            'event_data' => ['celebrant' => 'Lucía'],
        ]);

        $invitation = app(PartyInvitations::class)->forReservation($item->fresh(['ticketType', 'slot', 'order']));

        foreach (['Hugo Ruiz', 'Lía Fernández'] as $child) {
            InvitationReply::query()->create([
                'party_invitation_id' => $invitation->getKey(),
                'order_item_id' => $item->getKey(),
                'attending' => true,
                'child_name' => $child,
                'child_key' => PersonNameKey::for($child),
            ]);
        }

        return [$host, $item, $invitation];
    }
}
