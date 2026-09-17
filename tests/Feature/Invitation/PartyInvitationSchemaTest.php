<?php

namespace Tests\Feature\Invitation;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * **Los cimientos de la INVITACIÓN DIGITAL** (T4·1 de `docs/specs/celebracion-e-invitacion.md` §4.4;
 * `DECISIONES #573`).
 *
 * Vigila lo que el esquema PROMETE y que se rompería sin que nada fallara. Cada caso de aquí salió de
 * la segunda revisión adversarial de la spec (§7.2), que encontró estas políticas **ausentes**:
 *
 *  · las cascadas, sin las que `PurgeCustomerData` —que borra los pedidos POR TABLA— se estrellaría;
 *  · el `SET NULL` del vínculo con el justificante, sin el que la poda de una respuesta se llevaría
 *    por delante una PRUEBA LEGAL que se conserva años;
 *  · el índice NO único de `child_key`, que es una decisión de PRIVACIDAD y no una laxitud;
 *  · la lista blanca de escritura, porque estas filas las escribe una superficie pública.
 */
class PartyInvitationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reservation_can_only_have_one_invitation(): void
    {
        $reservation = $this->reservation();
        $this->invitationFor($reservation);

        $this->expectException(QueryException::class);
        $this->invitationFor($reservation);
    }

    /**
     * ⚠️⚠️ **Sin esta cascada la purga de go-live se estrella**: `PurgeCustomerData` borra los pedidos
     * con `Order::query()->delete()`, así que cualquier fila que cuelgue de una reserva y no caiga con
     * ella deja la limpieza a medias — y es PII de menores que dio un tercero.
     */
    public function test_deleting_the_reservation_takes_the_invitation_and_its_replies(): void
    {
        $reservation = $this->reservation();
        $invitation = $this->invitationFor($reservation);
        $this->replyTo($invitation, 'Hugo Ruiz');

        $reservation->delete();

        $this->assertSame(0, PartyInvitation::query()->count());
        $this->assertSame(0, InvitationReply::query()->count(), 'las respuestas tienen que caer con su reserva');
    }

    /**
     * Y la OTRA cascada, la de `invitation_replies.party_invitation_id`, que hay que ejercer por
     * separado.
     *
     * ⚠️ **Lo enseñó el arnés de mutación**: cambiándola a `RESTRICT` no moría ningún caso, porque al
     * borrar la RESERVA las respuestas caían igual por su segunda clave foránea. Una guarda que solo
     * ejerce un camino da por probada una promesa que nadie comprueba.
     */
    public function test_deleting_the_invitation_takes_its_replies(): void
    {
        $invitation = $this->invitationFor($this->reservation());
        $this->replyTo($invitation, 'Hugo Ruiz');

        $invitation->delete();

        $this->assertSame(0, InvitationReply::query()->count(), 'las respuestas tienen que caer con su invitación');
    }

    /**
     * **V6 · §7.2·R1 — es una decisión de PRIVACIDAD.** Un único sobre `(order_item_id, child_key)`
     * obligaría a rechazar el segundo «sí» por el mismo nombre, y ese rechazo le confirmaría a
     * cualquiera con el enlace que ese niño va a la fiesta: bastaba con probar nombres.
     */
    public function test_two_replies_for_the_same_child_are_accepted_in_silence(): void
    {
        $invitation = $this->invitationFor($this->reservation());

        $this->replyTo($invitation, 'Hugo Ruiz');
        $this->replyTo($invitation, 'hugo  RUIZ');

        $replies = InvitationReply::query()->get();
        $this->assertCount(2, $replies);
        $this->assertSame(
            [PersonNameKey::for('Hugo Ruiz'), PersonNameKey::for('Hugo Ruiz')],
            $replies->pluck('child_key')->all(),
            'las dos normalizan a la misma clave: es lo que las hace la misma persona sin decirlo',
        );
    }

    /**
     * ⚠️⚠️ **El caso que ordena la FK** (§7.2·R6). Las respuestas se podan a los 14 días de la visita
     * y **el justificante se conserva años**: con `RESTRICT` la poda fallaría, y con `CASCADE` se
     * llevaría una prueba legal por delante. `SET NULL` deja la prueba intacta y sin vínculo.
     */
    public function test_pruning_a_reply_never_takes_the_signed_authorisation_with_it(): void
    {
        $reservation = $this->reservation();
        $invitation = $this->invitationFor($reservation);
        $reply = $this->replyTo($invitation, 'Hugo Ruiz');

        $authorisation = GuardianAuthorization::query()->create([
            'order_item_id' => $reservation->getKey(),
            'minor_name' => 'Hugo', 'minor_surname' => 'Ruiz',
            'minor_key' => GuardianAuthorization::keyFor('Hugo', 'Ruiz'),
            'minor_born_on' => '2017-05-04',
            'guardian_name' => 'Ana', 'guardian_surname' => 'Gil',
            'guardian_relationship' => 'mother',
        ]);
        $authorisation->forceFill(['invitation_reply_id' => $reply->getKey()])->save();

        $reply->delete();

        $authorisation->refresh();
        $this->assertTrue($authorisation->exists, 'la prueba legal sobrevive a la poda de la respuesta');
        $this->assertNull($authorisation->invitation_reply_id, 'y se queda sin vínculo, no colgando de una fila borrada');
    }

    /**
     * La poda por PLAZO (V3), que es lo que hace cierta la promesa de conservación.
     *
     * ⚠️ Corre el comando de verdad en vez de contar `prunable()`: un `Prunable` que no esté en la
     * lista explícita de `model:prune` **no se poda nunca**, y contar la consulta no lo vería.
     */
    public function test_replies_are_pruned_fourteen_days_after_the_visit_and_not_before(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00', 'UTC'));

        $old = $this->invitationFor($this->reservation('2026-09-16'));      // la visita fue hace 15 días
        $recent = $this->invitationFor($this->reservation('2026-09-18'));   // hace 13
        $this->replyTo($old, 'Hugo Ruiz');
        $this->replyTo($recent, 'Lucía Gil');

        Artisan::call('model:prune', ['--model' => [InvitationReply::class]]);

        $this->assertSame(
            ['Lucía Gil'],
            InvitationReply::query()->pluck('child_name')->all(),
            'se poda lo que pasó el plazo y solo eso',
        );
    }

    /** Y el modelo está en la lista EXPLÍCITA del scheduler, o la poda no corre nunca en producción. */
    public function test_the_model_is_registered_in_the_prune_list(): void
    {
        $this->assertStringContainsString(
            'InvitationReply::class',
            (string) file_get_contents(base_path('routes/console.php')),
            'un Prunable fuera de la lista de `model:prune` es una promesa de conservación que nadie cumple',
        );
    }

    /**
     * `SEC-10` aplicado a una superficie PÚBLICA que escribe: la lista blanca tiene que dejar fuera lo
     * que decide el ANFITRIÓN. Un padre que manda `adopted_at` en su payload no puede dar por repasada
     * su propia respuesta.
     *
     * ⚠️ **Fuera de producción esto LANZA** (`preventSilentlyDiscardingAttributes`, `SEC-10`), y en
     * producción se descarta en silencio. La protección es la misma —el valor no llega a la fila—; lo
     * que cambia es que aquí, además, nadie puede añadir una clave a mano sin enterarse.
     */
    public function test_a_manipulated_payload_cannot_resolve_its_own_reply(): void
    {
        // ⚠️ **Las DOS claves, una a una.** El arnés de mutación enseñó que aseverar solo el
        // comportamiento no bastaba: añadiendo `adopted_at` a la lista blanca el caso seguía en verde,
        // porque el payload mandaba también `dismissed_at` y la excepción saltaba igual. Una guarda que
        // se satisface con que «algo» falle no dice cuál de las dos protege.
        $fillable = (new InvitationReply)->getFillable();
        $this->assertNotContains('adopted_at', $fillable, 'la adopción la decide el anfitrión, no el payload');
        $this->assertNotContains('dismissed_at', $fillable, 'el descarte también');

        $invitation = $this->invitationFor($this->reservation());

        $this->expectException(MassAssignmentException::class);

        InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(),
            'order_item_id' => $invitation->order_item_id,
            'attending' => true,
            'child_name' => 'Hugo Ruiz',
            'child_key' => PersonNameKey::for('Hugo Ruiz'),
            'adopted_at' => now(),
            'dismissed_at' => now(),
        ]);
    }

    /** Los dos interruptores nacen apagados: esta migración no cambia ninguna instalación. */
    public function test_both_switches_are_off_by_default(): void
    {
        // ⚠️ `fresh()`: el defecto lo pone la BASE DE DATOS, así que la instancia recién creada lo
        // tiene en `null` hasta releerla. Aseverar sobre ella diría que el defecto no existe.
        $product = $this->pack()->fresh();

        $this->assertFalse($product->guest_invitation, 'ningún producto ofrece invitación sin que alguien lo encienda');
        $this->assertDatabaseHas('ticket_types', ['id' => $product->id, 'guest_invitation' => false]);
    }

    // ─── Fixtures ─────────────────────────────────────────────────────────────

    private function pack(): TicketType
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);

        return TicketType::create([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Cumpleaños'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 2, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
        ]);
    }

    private function reservation(string $date = '2026-11-14'): OrderItem
    {
        $product = $this->pack();
        $slot = Slot::create([
            'zone_id' => $product->zone_id, 'date' => $date,
            'start_time' => '17:00:00', 'end_time' => '18:00:00', 'capacity' => 200, 'online_capacity' => 200,
        ]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'R-'.mb_strtoupper(mb_substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);

        return $order->items()->create([
            'ticket_type_id' => $product->id, 'slot_id' => $slot->id,
            'quantity' => 10, 'unit_price' => 500, 'seats' => 10,
        ]);
    }

    private function invitationFor(OrderItem $reservation): PartyInvitation
    {
        return PartyInvitation::query()->create([
            'order_item_id' => $reservation->getKey(),
            'token' => PartyInvitation::freshToken(),
            'theme' => PartyInvitation::THEME_DEFAULT,
            'honoree_name' => 'Lucía',
            'honoree_age' => 8,
            'host_line' => 'Te invita Marta',
            'show_host_phone' => false,
        ]);
    }

    private function replyTo(PartyInvitation $invitation, string $childName): InvitationReply
    {
        return InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(),
            'order_item_id' => $invitation->order_item_id,
            'attending' => true,
            'child_name' => $childName,
            'child_key' => PersonNameKey::for($childName),
        ]);
    }
}
