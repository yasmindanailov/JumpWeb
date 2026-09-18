<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

/**
 * **La INVITACIÓN DIGITAL por API** (T4·6 de `docs/specs/celebracion-e-invitacion.md` §4.10;
 * `DECISIONES #578`).
 *
 * Son DOS superficies con dos credenciales distintas, y este fichero existe sobre todo para vigilar
 * lo que las separa:
 *
 *  1. **PÚBLICA, por token** — la tarjeta que abre un padre y su respuesta. Su propiedad es que es
 *     una **HOJA EN BLANCO**: ni una respuesta, ni un contador, **ni si un nombre ya contestó**. De
 *     ahí sale todo lo demás, porque el enlace se reparte a un grupo de clase entero.
 *  2. **DEL ANFITRIÓN**, por la misma puerta que su formulario de invitados — personalizar, adoptar
 *     y quitar una respuesta.
 *
 * ⚠️ `assertValidResponse()` valida cada cuerpo contra `openapi/v1.yaml`, así que un campo de más en
 * la tarjeta pública **no hace falta cazarlo a mano**: el contrato lo prohíbe con
 * `additionalProperties: false`. Lo que sí se comprueba a mano aquí es lo que el contrato no puede
 * ver — que un nombre repetido dé el mismo desenlace, y que los cuatro «no» sean el mismo 404.
 */
class InvitationApiTest extends ApiTestCase
{
    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    // ── 1 · La HOJA EN BLANCO ─────────────────────────────────────────────────

    public function test_the_public_card_shows_the_party_and_not_a_single_reply(): void
    {
        [$reservation, $invitation] = $this->party();
        $this->replyOf($reservation, $invitation, 'Hugo Ruiz');

        $response = $this->getJson("/api/v1/invitations/{$invitation->token}");

        $response->assertOk()->assertValidResponse(200)
            ->assertJsonPath('honoree_name', 'Mara')
            ->assertJsonPath('product_name', 'Cumpleaños Jump')
            ->assertJsonPath('replies_open', true);

        // ❗❗ Lo que NO puede estar. El contrato ya lo prohíbe con `additionalProperties: false`;
        // esto lo dice por su nombre para que se lea como lo que es: quien tiene el enlace **no
        // puede averiguar quién va a la fiesta**.
        $body = $response->json();
        foreach (['replies_yes', 'replies_no', 'replies_pending', 'pending_replies', 'token', 'url'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $body, "la tarjeta pública filtró «{$forbidden}»");
        }
        $this->assertStringNotContainsString('Hugo', json_encode($body) ?: '');
    }

    /**
     * ⚠️⚠️ **El teléfono sale de la CUENTA y solo si el anfitrión lo marcó** (§4.5·12). Nunca se
     * teclea en la invitación: un campo libre ahí sería un sitio donde escribir el número al que uno
     * quiera que llame la gente.
     */
    public function test_the_host_phone_only_travels_when_the_host_asked_for_it(): void
    {
        [$reservation, $invitation] = $this->party();

        $this->getJson("/api/v1/invitations/{$invitation->token}")
            ->assertOk()->assertJsonPath('host_phone', null);

        $invitation->forceFill(['show_host_phone' => true])->save();

        $this->getJson("/api/v1/invitations/{$invitation->token}")
            ->assertOk()->assertJsonPath('host_phone', '600111222');
    }

    /**
     * ⚠️⚠️ **Los cuatro «no» son el MISMO 404** (§7.2·R10). Un 410 para «cancelada» frente a un 404
     * para «no existe» le confirmaría a un desconocido que ese token existió — y bastaba con
     * probar. Se comprueban los cuatro **con control**: el mismo fixture responde 200 antes.
     */
    public function test_every_way_of_not_being_available_answers_the_same_404(): void
    {
        // (a) Un token que no existe.
        $this->getJson('/api/v1/invitations/'.Str::random(12))->assertNotFound();

        // (b) El enlace ANULADO por el operador.
        [, $rotated] = $this->party();
        $this->getJson("/api/v1/invitations/{$rotated->token}")->assertOk();
        $old = (string) $rotated->token;
        $rotated->rotateToken();
        $this->getJson("/api/v1/invitations/{$old}")->assertNotFound();

        // (c) El pedido CANCELADO.
        [$cancelled, $invCancelled] = $this->party();
        $this->getJson("/api/v1/invitations/{$invCancelled->token}")->assertOk();
        $cancelled->order?->forceFill(['status' => Order::STATUS_CANCELLED])->save();
        $this->getJson("/api/v1/invitations/{$invCancelled->token}")->assertNotFound();

        // (d) El titular ANONIMIZADO.
        [$anon, $invAnon] = $this->party();
        $this->getJson("/api/v1/invitations/{$invAnon->token}")->assertOk();
        $anon->order?->user?->anonymize();
        $this->getJson("/api/v1/invitations/{$invAnon->token}")->assertNotFound();

        // (e) Y el producto con la invitación APAGADA después de repartir el enlace.
        [$off, $invOff] = $this->party();
        $this->getJson("/api/v1/invitations/{$invOff->token}")->assertOk();
        TicketType::query()->whereKey($off->ticket_type_id)->update(['guest_invitation' => false]);
        $this->getJson("/api/v1/invitations/{$invOff->token}")->assertNotFound();
    }

    /**
     * ⚠️⚠️ **La comprobación de la supresión es un CINTURÓN, y este caso la ejerce sola.**
     *
     * Desde `#577` anonimizar al titular **borra la invitación**, así que su enlace muere por esa vía
     * y el `isAnonymized()` de `resolvePublic()` no llegaría a ejecutarse nunca en el camino normal —
     * lo midió el arnés: quitarlo no ponía nada en rojo. Aquí se construye el estado que lo aísla: la
     * fila existe **y** el titular está anonimizado. Es el que quedaría si un día la supresión se
     * saltara una tabla, y `RGPD-03` dice que ese canal tiene que estar cerrado igual.
     */
    public function test_an_anonymised_host_closes_the_link_even_if_the_row_survived(): void
    {
        [$reservation, $invitation] = $this->party();

        $this->getJson("/api/v1/invitations/{$invitation->token}")->assertOk();

        $reservation->order?->user?->anonymize();

        // La supresión se llevó la fila: se vuelve a poner, que es justo el estado que se quiere aislar.
        PartyInvitation::query()->create([
            'order_item_id' => $reservation->getKey(),
            'token' => (string) $invitation->token,
            'theme' => PartyInvitation::THEME_DEFAULT,
            'honoree_name' => 'Mara',
            'host_line' => 'Te invita Mara',
            'show_host_phone' => false,
        ]);

        $this->getJson("/api/v1/invitations/{$invitation->token}")
            ->assertNotFound('el canal sigue abierto tras la supresión del titular');
    }

    // ── 2 · Contestar ─────────────────────────────────────────────────────────

    public function test_a_parent_answers_with_a_name_and_a_gesture(): void
    {
        [, $invitation] = $this->party();

        $this->postJson("/api/v1/invitations/{$invitation->token}", [
            'child_name' => 'Hugo Ruiz',
            'attending' => true,
        ])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('reason', null);

        $this->assertSame(1, InvitationReply::query()->count());
        $this->assertSame('Hugo Ruiz', (string) InvitationReply::query()->value('child_name'));
    }

    /**
     * ❗❗ **La prueba que ordena esta superficie.** Un nombre REPETIDO devuelve exactamente lo mismo
     * que la primera vez: `accepted: true` y sin motivo. Si respondiera distinto —o si existiera un
     * motivo «repetido»—, cualquiera con el enlace podría ir probando nombres hasta descubrir **quién
     * va a la fiesta de un niño**. La hoja es en blanco también en sus errores (§7.2·R1).
     */
    public function test_a_repeated_name_answers_exactly_like_the_first_time(): void
    {
        [, $invitation] = $this->party();

        $first = $this->postJson("/api/v1/invitations/{$invitation->token}", [
            'child_name' => 'Hugo Ruiz', 'attending' => true,
        ])->assertOk();

        $second = $this->postJson("/api/v1/invitations/{$invitation->token}", [
            'child_name' => 'Hugo Ruiz', 'attending' => true,
        ])->assertOk();

        $this->assertSame($first->json(), $second->json(), 'el repetido se distingue: es un oráculo de quién va');
        $this->assertSame(true, $second->json('accepted'));
        $this->assertNull($second->json('reason'));
    }

    /**
     * Un «sí» con el plazo pasado: 200 con su motivo, no un error del padre.
     *
     * ⚠️⚠️ **El reloj se congela y el corte se fija, y las dos cosas son necesarias** (`#579`). La
     * primera versión montaba la franja HOY a las 17:00 y no congelaba nada: a partir de las 19:00 de
     * Madrid la fiesta ya había terminado, `resolvePublic()` devolvía `null` y el caso recibía un 404
     * en vez del 200 — o sea, **se ponía rojo solo de siete de la tarde a dos de la mañana**, que es
     * justo cuando se cierran las sesiones y se corre el gate. Y el rojo mentía sobre su causa:
     * decía «esperaba 200, recibí 404», no «tu franja caducó».
     *
     * Aquí la fiesta es **mañana** —así que no ha terminado, y `closed` no puede taparle el sitio a
     * `cutoff`— y el corte se pone a 48 horas por ajuste, no por el valor que traiga la instalación.
     */
    public function test_the_deadline_is_a_reason_and_not_an_http_error(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 09:00:00', 'UTC'));
        Setting::query()->updateOrCreate(
            ['key' => GuestCountPolicy::SETTING_CUTOFF_HOURS],
            ['value' => '48'],
        );

        [, $invitation] = $this->party(slot: $this->slotIn(1));

        $this->postJson("/api/v1/invitations/{$invitation->token}", [
            'child_name' => 'Hugo Ruiz', 'attending' => true,
        ])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('reason', 'cutoff');
    }

    /**
     * ⚠️ El cuerpo se valida **después** de resolver el token. Si se validara antes, un cuerpo BIEN
     * formado distinguiría un token real de uno inventado — y ya habríamos contado que existe.
     */
    public function test_a_bad_body_on_an_unknown_token_is_still_just_a_404(): void
    {
        $this->postJson('/api/v1/invitations/'.Str::random(12), ['attending' => 'no-es-un-bool'])
            ->assertNotFound();

        [, $invitation] = $this->party();
        $this->postJson("/api/v1/invitations/{$invitation->token}", ['attending' => true])
            ->assertStatus(422);
    }

    /**
     * ⚠️ **Un valor anidado en `guest_data` es un 422, no un 500** (`#579`). El saneo del dominio hace
     * `trim((string) $value)`, así que un array llegaba hasta ahí y levantaba «Array to string
     * conversion»: el endpoint respondía 500 donde el contrato promete 422, y un cliente no puede
     * distinguir «lo que mandé está mal» de «el servidor se ha roto».
     */
    public function test_a_nested_value_in_guest_data_is_a_422_and_not_a_500(): void
    {
        [, $invitation] = $this->party();

        $this->postJson("/api/v1/invitations/{$invitation->token}", [
            'child_name' => 'Hugo Ruiz',
            'attending' => true,
            'guest_data' => ['allergy' => ['no', 'es', 'un', 'texto']],
        ])->assertStatus(422);

        $this->assertSame(0, InvitationReply::query()->count(), 'no se escribe nada con un cuerpo inválido');
    }

    // ── 3 · El ANFITRIÓN ──────────────────────────────────────────────────────

    public function test_the_host_sees_the_invitation_inside_the_guest_form(): void
    {
        [$reservation, $invitation] = $this->party();
        $this->replyOf($reservation, $invitation, 'Hugo Ruiz');
        $this->replyOf($reservation, $invitation, 'Lía Fernández', attending: false);

        $this->actingAs($this->hostOf($reservation))
            ->getJson("/api/v1/reservations/{$reservation->id}/guest-form")
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('invitation.token', (string) $invitation->token)
            ->assertJsonPath('invitation.replies_yes', 1)
            ->assertJsonPath('invitation.replies_no', 1)
            ->assertJsonPath('invitation.replies_pending', 2)
            ->assertJsonPath('invitation.pending_replies.0.child_name', 'Hugo Ruiz')
            // La ficha propuesta: la primera vacía, porque el anfitrión no ha escrito ningún nombre.
            ->assertJsonPath('invitation.pending_replies.0.slot_index', 0)
            // Un «no» no se pinta sobre ninguna ficha.
            ->assertJsonPath('invitation.pending_replies.1.slot_index', null);
    }

    /**
     * ⚠️⚠️ **`url` es `null` mientras la página pública no exista** (T5), y se rellena SOLA. Aquí se
     * declara una ruta con ese nombre y el campo aparece sin tocar una línea de producción: eso es lo
     * que hace que nadie tenga que acordarse de volver a rellenarlo.
     */
    public function test_the_share_url_appears_by_itself_the_day_the_public_page_exists(): void
    {
        [$reservation, $invitation] = $this->party();
        $host = $this->hostOf($reservation);

        $this->actingAs($host)
            ->getJson("/api/v1/reservations/{$reservation->id}/guest-form")
            ->assertOk()->assertJsonPath('invitation.url', null);

        Route::get('/invitacion/{token}', fn () => '')->name(PartyInvitations::PUBLIC_ROUTE);
        // El índice por nombre del router se construye una vez: sin refrescarlo, una ruta añadida
        // a mitad de una petición existe pero `Route::has()` no la ve.
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs($host)
            ->getJson("/api/v1/reservations/{$reservation->id}/guest-form")
            ->assertOk()
            ->assertJsonPath('invitation.url', url('/invitacion/'.$invitation->token));
    }

    /**
     * ⚠️ Un texto con un enlace **se rechaza sin 422**: ese campo se queda como estaba y el resto se
     * guarda. Una invitación bajo el dominio del parque no puede decir «paga el regalo aquí»
     * (§7.2·R9), y un 422 convertiría un descuido de redacción en un formulario que no guarda nada.
     */
    public function test_personalising_rejects_a_link_without_refusing_the_whole_form(): void
    {
        [$reservation] = $this->party();

        $this->actingAs($this->hostOf($reservation))
            ->putJson("/api/v1/reservations/{$reservation->id}/invitation", [
                'theme' => PartyInvitation::THEME_DEFAULT,
                'honoree_name' => 'Mira www.regalos.example',
                'honoree_age' => 8,
                'host_line' => 'Te invita Mara',
                'show_host_phone' => true,
            ])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('honoree_name', 'Mara')          // el anterior, intacto
            ->assertJsonPath('honoree_age', 8)                // y el resto SÍ se guardó
            ->assertJsonPath('host_line', 'Te invita Mara')
            ->assertJsonPath('show_host_phone', true);
    }

    /**
     * ⚠️⚠️ Personalizar **no toca el testigo de la reserva**. Si lo tocara, cambiar el color de una
     * banda dejaría obsoleta la página que el anfitrión tiene abierta y su siguiente guardado daría
     * 409.
     */
    public function test_personalising_never_moves_the_reservation_witness(): void
    {
        [$reservation] = $this->party();
        $before = (string) $reservation->updated_at?->toIso8601String();

        $this->travel(2)->minutes();

        $this->actingAs($this->hostOf($reservation))
            ->putJson("/api/v1/reservations/{$reservation->id}/invitation", [
                'theme' => PartyInvitation::THEME_DEFAULT,
                'honoree_name' => 'Mara',
                'honoree_age' => 9,
                'host_line' => 'Te invita Mara',
                'show_host_phone' => false,
            ])->assertOk();

        $this->assertSame($before, (string) $reservation->fresh()?->updated_at?->toIso8601String());
    }

    /** Un desconocido no personaliza la fiesta de nadie. */
    public function test_personalising_needs_the_same_door_as_the_guest_form(): void
    {
        [$reservation] = $this->party();

        $this->putJson("/api/v1/reservations/{$reservation->id}/invitation", [
            'theme' => PartyInvitation::THEME_DEFAULT, 'honoree_name' => 'Otro',
            'honoree_age' => null, 'host_line' => '', 'show_host_phone' => false,
        ])->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->putJson("/api/v1/reservations/{$reservation->id}/invitation", [
                'theme' => PartyInvitation::THEME_DEFAULT, 'honoree_name' => 'Otro',
                'honoree_age' => null, 'host_line' => '', 'show_host_phone' => false,
            ])->assertForbidden();

        $this->assertSame('Mara', (string) PartyInvitation::query()->value('honoree_name'));
    }

    // ── 4 · Adoptar y descartar ───────────────────────────────────────────────

    /**
     * La ADOPCIÓN viaja **fuera de `guests`** (§7.2·R3) y convierte la respuesta en una ficha del
     * anfitrión: deja de proponerse y deja de estar pendiente.
     */
    public function test_adopting_a_reply_turns_it_into_a_guest_of_the_host(): void
    {
        [$reservation, $invitation] = $this->party();
        $reply = $this->replyOf($reservation, $invitation, 'Hugo Ruiz');

        $this->actingAs($this->hostOf($reservation))
            ->putJson("/api/v1/reservations/{$reservation->id}/guest-form", [
                'guests' => [['name' => 'Hugo Ruiz'], ['name' => '']],
                'adopt' => [$reply->id],
            ])->assertOk()->assertValidResponse(200)
            ->assertJsonPath('invitation.replies_pending', 0)
            ->assertJsonPath('invitation.pending_replies', []);

        $this->assertNotNull($reply->fresh()?->adopted_at);
        $this->assertSame('hugo ruiz', (string) $reply->fresh()?->adopted_name_key);
    }

    /**
     * ⚠️ **Adoptar solo alcanza lo PENDIENTE.** Una respuesta que el anfitrión ya descartó no se
     * resucita mandando su id en `adopt[]`, y una ya adoptada no se re-adopta con fecha nueva: el
     * `pending()` es la guarda. Sin él, «no lo apuntes» se deshace con el siguiente guardado — y el
     * anfitrión volvería a quedarse con una plaza ocupada que creía haber liberado.
     */
    public function test_adopting_never_resurrects_a_dropped_reply(): void
    {
        [$reservation, $invitation] = $this->party();
        $reply = $this->replyOf($reservation, $invitation, 'Hugo Ruiz');
        $host = $this->hostOf($reservation);

        $this->actingAs($host)
            ->deleteJson("/api/v1/reservations/{$reservation->id}/invitation/replies/{$reply->id}")
            ->assertNoContent();

        $this->actingAs($host)->putJson("/api/v1/reservations/{$reservation->id}/guest-form", [
            'guests' => [['name' => 'Hugo Ruiz'], ['name' => '']],
            'adopt' => [$reply->id],
        ])->assertOk();

        $fresh = $reply->fresh();
        $this->assertNotNull($fresh?->dismissed_at, 'la descartada volvió a la vida');
        $this->assertNull($fresh?->adopted_at, 'una respuesta descartada no se adopta');
    }

    /**
     * ⚠️⚠️ **El EMPAREJADO de la regla 4, que es el que hace útil la propuesta.** El anfitrión pegó
     * «Hugo» en la segunda ficha con el pegado de la T2; el padre escribe «Hugo Ruiz», con apellidos,
     * porque es lo que se le pide para distinguir a dos niños que se llamen igual.
     *
     * La propuesta tiene que caer **sobre esa ficha**, no sobre la primera vacía: proponerla en otro
     * sitio le pediría al anfitrión que apuntara dos veces al mismo niño — y con V4, además, cada uno
     * ocupando su plaza.
     */
    public function test_a_reply_is_proposed_on_the_guest_whose_name_it_matches(): void
    {
        [$reservation, $invitation] = $this->party();
        $host = $this->hostOf($reservation);

        $this->actingAs($host)->putJson("/api/v1/reservations/{$reservation->id}/guest-form", [
            'guests' => [['name' => ''], ['name' => 'Hugo']],
        ])->assertOk();

        $this->replyOf($reservation->fresh() ?? $reservation, $invitation, 'Hugo Ruiz');

        $this->actingAs($host)
            ->getJson("/api/v1/reservations/{$reservation->id}/guest-form")
            ->assertOk()
            ->assertJsonPath('invitation.pending_replies.0.slot_index', 1);
    }

    /**
     * ❗❗ **EL CAMINO DE BANDERA de la feature, y hasta `#579` se rompía solo.**
     *
     * El anfitrión pega la lista de clase con nombres de pila —«Hugo»— y al padre se le pide **nombre
     * y apellidos** para distinguir a dos niños que se llamen igual, así que contesta «Hugo Ruiz». El
     * emparejado los une (regla 4, por la primera palabra) y la propuesta cae sobre esa ficha.
     *
     * Al adoptarla, la respuesta se marcaba con la clave del PADRE (`hugo ruiz`) y la reconciliación
     * la comparaba contra las claves de las FICHAS (`hugo`) con igualdad exacta: no casaba, así que la
     * daba por huérfana y **la descartaba en el mismo `PUT`**. El niño que confirmó dejaba de ocupar
     * plaza —justo el suelo que `#576` añadió para protegerlo— y desaparecía del resumen sin que el
     * anfitrión hubiera quitado nada.
     *
     * ▶ La raíz: `adopted_name_key` tiene que ser **la clave de la ficha sobre la que se adopta**, no
     * la del nombre que escribió el padre. Todo lo que la lee después compara contra fichas.
     */
    public function test_adopting_a_reply_matched_by_its_first_word_survives_the_save(): void
    {
        [$reservation, $invitation] = $this->party();
        $host = $this->hostOf($reservation);

        // El anfitrión pegó solo el nombre de pila.
        $this->actingAs($host)->putJson("/api/v1/reservations/{$reservation->id}/guest-form", [
            'guests' => [['name' => 'Hugo'], ['name' => '']],
        ])->assertOk();

        $reply = $this->replyOf($reservation->fresh() ?? $reservation, $invitation, 'Hugo Ruiz');

        // Y guarda adoptándola, sin tocar el nombre que ya había escrito.
        $this->actingAs($host)->putJson("/api/v1/reservations/{$reservation->id}/guest-form", [
            'guests' => [['name' => 'Hugo'], ['name' => '']],
            'adopt' => [$reply->id],
        ])->assertOk()
            ->assertJsonPath('invitation.replies_yes', 1)
            ->assertJsonPath('invitation.replies_pending', 0);

        $fresh = $reply->fresh();
        $this->assertNotNull($fresh?->adopted_at, 'no se adoptó');
        $this->assertNull($fresh?->dismissed_at, 'se adoptó y se descartó en el mismo guardado');

        // Y sigue ocupando su plaza: es el suelo que impide dejar fuera a quien confirmó.
        $this->assertSame(
            1,
            app(PartyGuests::class)->committedReplyIdsIn((int) $reservation->id) === []
                ? 0
                : 1,
            'el niño que confirmó dejó de ocupar plaza'
        );
    }

    /**
     * ⚠️⚠️ **Un id de OTRA fiesta no adopta nada.** El `where` de la reserva es la guarda, no una
     * optimización: sin él, cualquier anfitrión podría marcar como suya la respuesta de otra familia
     * con solo acertar un número.
     */
    public function test_a_reply_id_from_another_party_adopts_nothing(): void
    {
        [$mine] = $this->party();
        [$theirs, $theirInvitation] = $this->party();
        $foreign = $this->replyOf($theirs, $theirInvitation, 'Hugo Ruiz');

        $this->actingAs($this->hostOf($mine))
            ->putJson("/api/v1/reservations/{$mine->id}/guest-form", [
                'guests' => [['name' => 'Hugo Ruiz'], ['name' => '']],
                'adopt' => [$foreign->id],
            ])->assertOk();

        $this->assertNull($foreign->fresh()?->adopted_at, 'se adoptó la respuesta de otra fiesta');
    }

    /**
     * ⚠️ Una adoptada cuya ficha **desaparece** se descarta: el anfitrión la quitó (§4.7). Dejarla
     * adoptada la escondería para siempre —ni se propone ni se ve— **y seguiría ocupando su plaza**.
     */
    public function test_an_adopted_reply_whose_guest_was_deleted_is_dismissed(): void
    {
        [$reservation, $invitation] = $this->party();
        $reply = $this->replyOf($reservation, $invitation, 'Hugo Ruiz');
        $host = $this->hostOf($reservation);

        $this->actingAs($host)->putJson("/api/v1/reservations/{$reservation->id}/guest-form", [
            'guests' => [['name' => 'Hugo Ruiz'], ['name' => '']],
            'adopt' => [$reply->id],
        ])->assertOk();

        $this->actingAs($host)->putJson("/api/v1/reservations/{$reservation->id}/guest-form", [
            'guests' => [['name' => ''], ['name' => '']],
        ])->assertOk();

        $this->assertNotNull($reply->fresh()?->dismissed_at, 'la respuesta quedó adoptada y escondida');
    }

    /**
     * ❗❗ «No lo apuntes» (§7.2·R11). Sin este gesto el anfitrión se queda ATRAPADO: desde `#576` un
     * «sí» pendiente ocupa plaza y le impediría bajar el número de invitados.
     */
    public function test_the_host_can_drop_a_reply_and_doing_it_twice_is_the_same(): void
    {
        [$reservation, $invitation] = $this->party();
        $reply = $this->replyOf($reservation, $invitation, 'Hugo Ruiz');
        $host = $this->hostOf($reservation);

        $this->actingAs($host)
            ->deleteJson("/api/v1/reservations/{$reservation->id}/invitation/replies/{$reply->id}")
            ->assertNoContent();

        $this->assertNotNull($reply->fresh()?->dismissed_at);

        // Idempotente: dos pestañas del mismo anfitrión no se pelean por quién llegó antes.
        $this->actingAs($host)
            ->deleteJson("/api/v1/reservations/{$reservation->id}/invitation/replies/{$reply->id}")
            ->assertNoContent();

        // Y descartar NO borra: la poda de los 14 días es quien se la lleva.
        $this->assertSame(1, InvitationReply::query()->count());
    }

    /** Y la respuesta de otra fiesta tampoco se descarta desde aquí. */
    public function test_dropping_never_reaches_another_partys_reply(): void
    {
        [$mine] = $this->party();
        [$theirs, $theirInvitation] = $this->party();
        $foreign = $this->replyOf($theirs, $theirInvitation, 'Hugo Ruiz');

        $this->actingAs($this->hostOf($mine))
            ->deleteJson("/api/v1/reservations/{$mine->id}/invitation/replies/{$foreign->id}")
            ->assertNoContent();

        $this->assertNull($foreign->fresh()?->dismissed_at, 'se descartó la respuesta de otra fiesta');
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function hostOf(OrderItem $reservation): User
    {
        return $reservation->order?->user ?? User::factory()->create();
    }

    /**
     * Una franja dentro de `$days` días. `0` = hoy, o sea con el plazo ya pasado.
     *
     * ⚠️ `firstOrCreate` y no `create`: varios casos montan DOS fiestas, y una franja es única por
     * (zona, día, hora). Con `create` el segundo fixture reventaba contra el índice — y el caso se
     * caía por su decorado, no por lo que dice medir.
     */
    private function slotIn(int $days): Slot
    {
        return Slot::firstOrCreate(
            [
                'zone_id' => $this->zone->id,
                'date' => now()->addDays($days)->toDateString(),
                'start_time' => '17:00:00',
            ],
            ['end_time' => '19:00:00', 'capacity' => 40, 'online_capacity' => 40],
        );
    }

    /** @return array{0: OrderItem, 1: PartyInvitation} */
    private function party(?Slot $slot = null): array
    {
        $type = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_invitation' => true,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Homenajeado']],
            ],
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'allergy', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Alergia']],
            ],
        ]);

        $user = User::factory()->create(['name' => 'Mara Anfitriona', 'phone' => '600111222']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
        ]);
        $reservation = $order->items()->create([
            'ticket_type_id' => $type->id,
            'slot_id' => ($slot ?? $this->slotIn(30))->id,
            'quantity' => 2, 'unit_price' => 1000, 'seats' => 2,
            'event_data' => ['celebrant' => 'Mara'],
        ]);

        $invitation = app(PartyInvitations::class)
            ->forReservation($reservation->fresh(['ticketType', 'slot', 'order.user']) ?? $reservation);

        return [$reservation->fresh(['ticketType', 'slot', 'order.user']) ?? $reservation, $invitation];
    }

    private function replyOf(OrderItem $reservation, PartyInvitation $invitation, string $child, bool $attending = true): InvitationReply
    {
        return InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(),
            'order_item_id' => $reservation->getKey(),
            'attending' => $attending,
            'child_name' => $child,
            'child_key' => PersonNameKey::for($child),
        ]);
    }
}
