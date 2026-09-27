<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * F8b de `specs/fiesta-sistema-nuevo.md` §4.14 (`[DECIDIDO owner]` `#753`): **CADA ENVÍO, MEDIDO**.
 *
 * Hasta F8 los botones de WhatsApp eran enlaces sin rastro: la lista DEDUCÍA que la invitación había salido y nadie sabía
 * qué envío traía familias. Lo que se afirma: el envío es un hecho de la reserva (canal y zona) que la primera vez marca
 * `shared_at`; tiene su PROPIO cupo (copiar no puede negarle el Guardar al anfitrión); solo lo escribe quien entra al
 * formulario; y el canal del enlace llega a la visita y a la respuesta de la familia.
 */
class EnvioDeLaInvitacionTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    public function test_a_share_is_a_fact_of_the_reservation_and_the_first_one_marks_the_invitation(): void
    {
        ['reservation' => $r, 'invitation' => $inv, 'host' => $host, 'order' => $order] = $this->mountParty();
        $this->assertNull($inv->shared_at);

        $this->actingAs($host)->post(route('reservation.invitation.share', ['reservation' => $r]), ['via' => 'whatsapp', 'where' => 'invitation'])->assertNoContent();

        $primera = $inv->refresh()->shared_at;
        $this->assertNotNull($primera);
        $hecho = AnalyticsEvent::query()->where('name', 'invitation_shared')->sole();
        $this->assertSame($order->id, $hecho->order_id);
        $this->assertNull($hecho->user_id, 'un hecho de la reserva y de nadie, aunque entre con su cuenta');
        $this->assertSame(['via' => 'whatsapp', 'where' => 'invitation', 'reservation' => $r->id], array_intersect_key($hecho->props, ['via' => 0, 'where' => 0, 'reservation' => 0]));

        // La segunda no mueve la fecha (solo el PRIMER envío): el hecho, sí.
        Carbon::setTestNow(now()->addHour());
        $this->actingAs($host)->post(route('reservation.invitation.share', ['reservation' => $r]), ['via' => 'copy', 'where' => 'number'])->assertNoContent();
        $this->assertEquals($primera, $inv->refresh()->shared_at);
        $this->assertSame(2, AnalyticsEvent::query()->where('name', 'invitation_shared')->count());
        Carbon::setTestNow();
    }

    public function test_only_the_known_channels_and_zones_are_written(): void
    {
        ['reservation' => $r, 'invitation' => $inv, 'host' => $host] = $this->mountParty();

        $this->actingAs($host)->postJson(route('reservation.invitation.share', ['reservation' => $r]), ['via' => 'sms', 'where' => 'invitation'])->assertUnprocessable();
        $this->actingAs($host)->postJson(route('reservation.invitation.share', ['reservation' => $r]), ['via' => 'copy', 'where' => 'portada'])->assertUnprocessable();

        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'invitation_shared')->count());
        $this->assertNull($inv->refresh()->shared_at);
    }

    public function test_only_who_opens_the_form_can_say_it_went_out(): void
    {
        ['reservation' => $r, 'invitation' => $inv] = $this->mountParty();

        $ajeno = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($ajeno)->post(route('reservation.invitation.share', ['reservation' => $r]), ['via' => 'whatsapp', 'where' => 'invitation'])->assertForbidden();
        $this->assertNull($inv->refresh()->shared_at);

        // CONTROL: el enlace firmado del correo (sin sesión), sí.
        $this->post($r->invitationSignedShareUrl(), ['via' => 'whatsapp', 'where' => 'invitation'])->assertNoContent();
        $this->assertNotNull($inv->refresh()->shared_at);
    }

    public function test_sharing_has_its_own_quota_and_never_spends_the_one_of_saving(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();

        // Más de los 30 por minuto del Guardar: si compartieran cubo, el Guardar de abajo saldría 429.
        for ($i = 0; $i < 35; $i++) {
            $this->actingAs($host)->post(route('reservation.invitation.share', ['reservation' => $r]), ['via' => 'copy', 'where' => 'invitation'])->assertNoContent();
        }

        $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $r]), [
            'expected_version' => PostFormAddons::versionOf($r->fresh() ?? $r),
            'guests' => [['name' => 'Ana']],
        ])->assertRedirect();
    }

    public function test_after_the_party_nothing_is_marked(): void
    {
        ['reservation' => $r, 'invitation' => $inv, 'host' => $host] = $this->mountParty();
        $this->travelTo($r->slot->date->copy()->addDays(2));

        $this->actingAs($host)->post(route('reservation.invitation.share', ['reservation' => $r]), ['via' => 'whatsapp', 'where' => 'invitation'])->assertNoContent();

        $this->assertNull($inv->refresh()->shared_at);
        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'invitation_shared')->count());
    }

    public function test_the_channel_of_the_link_reaches_the_visit_and_the_reply(): void
    {
        ['reservation' => $r, 'invitation' => $inv] = $this->mountParty();

        $html = $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $inv->token, 'c' => 'wa']))->assertOk()->getContent();
        $this->assertSame('wa', AnalyticsEvent::query()->where('name', 'invitation_viewed')->sole()->props['channel'] ?? null);
        // El formulario de respuesta lleva el canal en su URL.
        $this->assertStringContainsString(e(route('invitation.reply', ['token' => $inv->token, 'c' => 'wa'])), $html);

        $this->post(route('invitation.reply', ['token' => $inv->token, 'c' => 'wa']), ['child_name' => 'Hugo Ruiz', 'attending' => '1'])->assertRedirect();
        $this->assertSame('wa', AnalyticsEvent::query()->where('name', 'invitation_replied')->sole()->props['channel'] ?? null);

        // Un canal fuera de la lista no se escribe (y el formulario no lo arrastra).
        $otra = $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $inv->token, 'c' => 'spam']))->assertOk()->getContent();
        $ultima = AnalyticsEvent::query()->where('name', 'invitation_viewed')->orderByDesc('id')->first();
        $this->assertArrayNotHasKey('channel', $ultima?->props ?? []);
        $this->assertStringNotContainsString('c=spam', $otra);
        $this->assertSame($r->id, $ultima?->props['reservation'] ?? null);
    }
}
