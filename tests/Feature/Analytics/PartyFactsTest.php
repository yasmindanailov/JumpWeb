<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Services\Analytics\AttributionContext;
use App\Domain\Platform\Services\Analytics\Contract;
use App\Domain\Platform\Services\Analytics\PartyFacts;
use App\Domain\Platform\Services\Analytics\Recorder;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * **LOS HECHOS DE LA FIESTA, cada uno desde su página** (`docs/specs/analitica-fiesta.md` §4.2, T1): abrir y guardar
 * el post-form, ver la invitación, bajar su calendario y contestar, abrir el justificante y firmarlo. Todos son
 * hechos de la RESERVA —`order_id`— y de nadie: sin visitante, sin sesión y sin titular, aunque el anfitrión entre
 * con su cuenta. Los tres primeros nombres estaban en el contrato desde la T1 de la analítica y nadie los emitía.
 *
 * Lo que vigila además: que un robot no deje hecho (las vistas previas de los chats abren el enlace), que
 * `days_before` se cuente en días del PARQUE y exacto, que la firma solo cuente cuando se CREA («un niño, un
 * papel»), y que ninguna prop tenga pinta de dato personal (`RGPD-02`).
 */
class PartyFactsTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    private function fact(string $name): AnalyticsEvent
    {
        return AnalyticsEvent::query()->where('name', $name)->sole();
    }

    private function assertIsOfTheReservationAndOfNobody(AnalyticsEvent $fact, int $orderId): void
    {
        $this->assertSame($orderId, $fact->order_id, "«{$fact->name}» no es de la reserva");
        $this->assertNull($fact->visitor_id, "«{$fact->name}» lleva visitante");
        $this->assertNull($fact->session_id, "«{$fact->name}» lleva sesión");
        $this->assertNull($fact->user_id, "«{$fact->name}» lleva titular");
    }

    // ─── El post-form ─────────────────────────────────────────────────────────

    public function test_opening_the_form_leaves_a_fact_of_the_reservation_and_of_nobody(): void
    {
        $party = $this->mountParty();

        $this->get($party['reservation']->guestFormSignedUrl())->assertOk();

        $fact = $this->fact('guest_form_opened');
        $this->assertIsOfTheReservationAndOfNobody($fact, $party['order']->id);
        $this->assertEquals(['reservation' => $party['reservation']->id, 'days_before' => self::DAYS_BEFORE, 'device' => 'desktop', 'locale' => 'es'], $fact->props);
    }

    public function test_the_host_with_her_session_open_leaves_no_user_on_the_fact(): void
    {
        $party = $this->mountParty();

        $this->actingAs($party['host'])
            ->get(route('reservation.guests', ['reservation' => $party['reservation']]))
            ->assertOk();

        $this->assertIsOfTheReservationAndOfNobody($this->fact('guest_form_opened'), $party['order']->id);
    }

    public function test_saving_the_form_says_what_moved_and_not_who(): void
    {
        $party = $this->mountParty();

        $this->post($party['reservation']->guestFormSignedStoreUrl(), [
            'guests' => [['name' => 'Ana Pérez'], ['name' => 'Luis Gil']],
        ])->assertRedirect();

        $fact = $this->fact('guest_form_submitted');
        $this->assertIsOfTheReservationAndOfNobody($fact, $party['order']->id);
        $this->assertEquals(['reservation' => $party['reservation']->id, 'days_before' => self::DAYS_BEFORE, 'guests_delta' => 0, 'extras_cents' => 0, 'replies_adopted' => 0], $fact->props);
    }

    // ─── La invitación ────────────────────────────────────────────────────────

    public function test_the_invitation_page_its_calendar_and_a_reply_leave_their_facts(): void
    {
        $party = $this->mountParty();
        $token = $party['invitation']->token;

        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $token]))->assertOk();
        $this->get(route('invitation.calendar', ['token' => $token]))->assertOk();
        $this->post(route('invitation.reply', ['token' => $token]), ['child_name' => 'Hugo Ruiz', 'attending' => '1'])
            ->assertRedirect()
            ->assertSessionHas('invitation_status', 'yes');

        $id = $party['reservation']->id;
        $viewed = $this->fact('invitation_viewed');
        $this->assertIsOfTheReservationAndOfNobody($viewed, $party['order']->id);
        $this->assertEquals(['reservation' => $id, 'days_before' => self::DAYS_BEFORE, 'device' => 'desktop', 'locale' => 'es'], $viewed->props);

        $calendar = $this->fact('invitation_calendar_downloaded');
        $this->assertIsOfTheReservationAndOfNobody($calendar, $party['order']->id);
        $this->assertEquals(['reservation' => $id, 'days_before' => self::DAYS_BEFORE], $calendar->props);

        $replied = $this->fact('invitation_replied');
        $this->assertIsOfTheReservationAndOfNobody($replied, $party['order']->id);
        $this->assertEquals(['reservation' => $id, 'attending' => 'yes', 'companion' => false, 'days_before' => self::DAYS_BEFORE], $replied->props);
    }

    public function test_a_no_is_a_reply_too_and_says_no(): void
    {
        $party = $this->mountParty();

        $this->post(route('invitation.reply', ['token' => $party['invitation']->token]), ['child_name' => 'Hugo Ruiz', 'attending' => '0'])
            ->assertRedirect()
            ->assertSessionHas('invitation_status', 'no');

        $this->assertSame('no', $this->fact('invitation_replied')->props['attending']);
    }

    /** Las vistas previas de las apps de mensajería abren el enlace de la invitación: no son aperturas. */
    public function test_a_robot_leaves_no_fact(): void
    {
        $party = $this->mountParty();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
            ->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $party['invitation']->token]))
            ->assertOk();
        $this->withHeader('User-Agent', 'WhatsApp/2.23.20 A')
            ->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $party['invitation']->token]))
            ->assertOk();

        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'invitation_viewed')->count());
    }

    // ─── El justificante ──────────────────────────────────────────────────────

    public function test_the_authorization_counts_the_opening_the_signature_and_the_hours_between(): void
    {
        $party = $this->mountParty();

        $this->get($party['reservation']->guardianAuthorizationSignedUrl())->assertOk();
        $this->post($this->signedAuthorizationStoreUrl($party['reservation']), $this->authorizationPayload($party['document']))
            ->assertRedirect()
            ->assertSessionHas('guardian_status', 'signed');

        $opened = $this->fact('authorization_opened');
        $this->assertIsOfTheReservationAndOfNobody($opened, $party['order']->id);
        $this->assertEquals(['reservation' => $party['reservation']->id, 'via' => 'link', 'days_before' => self::DAYS_BEFORE, 'device' => 'desktop'], $opened->props);

        $signed = $this->fact('authorization_signed');
        $this->assertIsOfTheReservationAndOfNobody($signed, $party['order']->id);
        $this->assertSame('link', $signed->props['via']);
        $this->assertSame(self::DAYS_BEFORE, $signed->props['days_before']);
        $this->assertIsNumeric($signed->props['hours_since_open']);
        $this->assertLessThan(1, $signed->props['hours_since_open']);
    }

    /**
     * El reenvío del mismo padre es IDEMPOTENTE (vuelve «signed», la autorización no se crea de nuevo) y no es
     * una firma nueva: el hecho cuenta solo cuando `created` es verdadero.
     */
    public function test_a_resent_signature_of_the_same_child_is_not_a_second_fact(): void
    {
        $party = $this->mountParty();
        $url = $this->signedAuthorizationStoreUrl($party['reservation']);

        $this->post($url, $this->authorizationPayload($party['document']))->assertSessionHas('guardian_status', 'signed');
        $this->post($url, $this->authorizationPayload($party['document']))->assertSessionHas('guardian_status', 'signed');

        $this->assertSame(1, AnalyticsEvent::query()->where('name', 'authorization_signed')->count(), 'el reenvío dejó un segundo hecho de firma');
    }

    /** Sin apertura en esta sesión (el padre firma desde otra pestaña o tras cerrar), sin dato: la prop no viaja. */
    public function test_without_an_opening_in_the_session_there_are_no_hours(): void
    {
        $party = $this->mountParty();

        $this->post($this->signedAuthorizationStoreUrl($party['reservation']), $this->authorizationPayload($party['document']))
            ->assertSessionHas('guardian_status', 'signed');

        $this->assertArrayNotHasKey('hours_since_open', $this->fact('authorization_signed')->props);
    }

    // ─── El régimen y los días ────────────────────────────────────────────────

    /** El registrador no mira el contexto de atribución para un hecho de la reserva, ni siquiera con un visitante resuelto. */
    public function test_a_fact_of_order_ignores_a_resolved_visitor_context(): void
    {
        $party = $this->mountParty();
        $request = Request::create('/invitacion/x', 'GET', cookies: [Visitor::COOKIE => Visitor::mint()]);
        app(AttributionContext::class)->resolveFrom($request);

        app(Recorder::class)->factOfOrder('invitation_viewed', $party['order']->id, ['days_before' => 3]);

        $this->assertIsOfTheReservationAndOfNobody($this->fact('invitation_viewed'), $party['order']->id);
    }

    /**
     * Solo nombres de SERVIDOR del contrato: un hecho de cliente («drawer_opened») escrito desde PHP sería una manera
     * de saltarse la ingesta, y un nombre inventado no es nada. Lo cazó el arnés: sin este caso, quitar la guarda
     * sobrevivía.
     */
    public function test_a_fact_of_order_refuses_a_client_event_and_an_unknown_name(): void
    {
        $party = $this->mountParty();

        app(Recorder::class)->factOfOrder('drawer_opened', $party['order']->id, ['product' => 'jump']);
        app(Recorder::class)->factOfOrder('party_started', $party['order']->id);

        // El pedido pagado del fixture deja sus propios hechos (`order_*`): se mira solo lo que aquí se rechazó.
        $this->assertSame(0, AnalyticsEvent::query()->whereIn('name', ['drawer_opened', 'party_started'])->count(), 'un nombre que no es un hecho de servidor entró en el libro');
    }

    public function test_days_before_are_park_days_and_exact(): void
    {
        $today = DisplayTime::today();

        $this->assertSame(12, PartyFacts::daysBefore($today->copy()->addDays(12)->toDateString()));
        $this->assertSame(0, PartyFacts::daysBefore($today->copy()));
        $this->assertSame(-1, PartyFacts::daysBefore($today->copy()->subDay()->toDateString()));
        $this->assertNull(PartyFacts::daysBefore(null));
        $this->assertNull(PartyFacts::daysBefore(''));
    }

    public function test_no_prop_of_any_party_fact_looks_like_personal_data(): void
    {
        $party = $this->mountParty();
        $token = $party['invitation']->token;

        $this->get($party['reservation']->guestFormSignedUrl())->assertOk();
        $this->post($party['reservation']->guestFormSignedStoreUrl(), ['guests' => [['name' => 'Ana Pérez']]])->assertRedirect();
        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $token]))->assertOk();
        $this->post(route('invitation.reply', ['token' => $token]), ['child_name' => 'Hugo Ruiz', 'attending' => '1'])->assertRedirect();
        $this->get($party['reservation']->guardianAuthorizationSignedUrl())->assertOk();
        $this->post($this->signedAuthorizationStoreUrl($party['reservation']), $this->authorizationPayload($party['document']))->assertRedirect();

        $facts = AnalyticsEvent::query()->whereIn('name', ['guest_form_opened', 'guest_form_submitted', 'invitation_viewed', 'invitation_replied', 'authorization_opened', 'authorization_signed'])->get();
        $this->assertCount(6, $facts);

        foreach ($facts as $fact) {
            foreach ((array) $fact->props as $key => $value) {
                $this->assertFalse(Contract::isPiiKey((string) $key), "«{$fact->name}.{$key}» tiene pinta de dato personal");
                $this->assertFalse(is_string($value) && Contract::looksLikePii($value), "«{$fact->name}.{$key}» lleva un valor con pinta de dato personal");
            }
        }
    }
}
