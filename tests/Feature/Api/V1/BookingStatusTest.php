<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 4 · paso 4.0b — `GET /api/v1/booking/status`.
 *
 * Hasta este paso, la pausa de reservas (#218) solo existía por API como el código de un 409: el
 * cliente se enteraba **después** de intentar crear el pedido. En la web pasa lo contrario desde
 * siempre, así que sin este endpoint la SPA habría perdido una conducta que hoy existe.
 *
 * Los dos casos que de verdad importan son los que una reescritura tiende a romper:
 *  · los canales se ofrecen **a la vez**, no en cascada — con teléfono y WhatsApp se pintan los dos;
 *  · `contact_url` es el **último recurso** y llega ya decidido por el servidor, para que el cliente
 *    no tenga que evaluar la condición (y no pueda discrepar de la web).
 */
class BookingStatusTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/booking/status';

    private function pause(): void
    {
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1']);
        Setting::flushMemo();
    }

    private function contact(?string $phone = null, ?string $whatsapp = null): void
    {
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => $phone ?? '']);
        Setting::updateOrCreate(['key' => 'contact.whatsapp'], ['value' => $whatsapp ?? '']);
        Setting::flushMemo();
    }

    // ── El interruptor ────────────────────────────────────────────────────────────────────────

    public function test_reservations_are_open_by_default(): void
    {
        $this->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('reservations_paused', false);
    }

    public function test_it_reports_the_pause(): void
    {
        $this->pause();

        $this->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('reservations_paused', true);
    }

    /** Público: el aviso hay que enseñarlo a quien abre el cajón, tenga cuenta o no. */
    public function test_it_needs_no_session(): void
    {
        $this->assertGuest();

        $this->getJson(self::PATH)->assertOk();
    }

    /**
     * **El aviso viaja SIEMPRE**, también con las reservas abiertas. La forma de la respuesta no
     * puede cambiar con el estado: un objeto que aparece y desaparece obliga a cada cliente a
     * distinguir «no está» de «está vacío».
     */
    public function test_the_notice_travels_even_when_reservations_are_open(): void
    {
        $response = $this->getJson(self::PATH)->assertOk()->assertValidResponse(200);

        $this->assertNotSame('', $response->json('notice.title'));
        $this->assertNotSame('', $response->json('notice.message'));
    }

    // ── Los textos, editables por el panel ────────────────────────────────────────────────────

    public function test_the_operator_wording_wins_over_the_default(): void
    {
        $this->pause();
        Setting::updateOrCreate(['key' => 'reservations.title.es'], ['value' => 'Reservas cerradas hoy']);
        Setting::updateOrCreate(['key' => 'reservations.message.es'], ['value' => 'Estamos de mantenimiento']);
        Setting::flushMemo();

        $this->getJson(self::PATH)
            ->assertOk()
            ->assertJsonPath('notice.title', 'Reservas cerradas hoy')
            ->assertJsonPath('notice.message', 'Estamos de mantenimiento');
    }

    /** Sin override, cae al literal traducido — nunca a la clave ni al vacío. */
    public function test_without_an_override_the_notice_falls_back_to_the_default_wording(): void
    {
        $response = $this->getJson(self::PATH)->assertOk();

        $this->assertStringNotContainsString('tickets.paused', (string) $response->json('notice.title'));
        $this->assertStringNotContainsString('tickets.paused', (string) $response->json('notice.message'));
    }

    // ── Los canales: A LA VEZ, no en cascada ──────────────────────────────────────────────────

    /**
     * ⚠️ **El caso que una reescritura rompe.** Con teléfono Y WhatsApp configurados, los dos
     * viajan. Un diseño «en cascada» —teléfono, y si no WhatsApp— escondería el WhatsApp de toda
     * instalación que tenga teléfono, en silencio.
     */
    public function test_both_channels_travel_together(): void
    {
        $this->contact(phone: '+34 968 12 34 56', whatsapp: '+34 600-11-22-33');

        $this->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('notice.phone', '+34 968 12 34 56')
            ->assertJsonPath('notice.phone_tel', '+34968123456')
            ->assertJsonPath('notice.whatsapp', '34600112233')
            ->assertJsonPath('notice.contact_url', null);
    }

    /** Con un solo canal, el otro es `null` y el último recurso sigue sin proceder. */
    public function test_a_single_channel_still_rules_out_the_fallback(): void
    {
        $this->contact(phone: '968 12 34 56');

        $this->getJson(self::PATH)
            ->assertOk()
            ->assertJsonPath('notice.phone_tel', '968123456')
            ->assertJsonPath('notice.whatsapp', null)
            ->assertJsonPath('notice.contact_url', null);
    }

    /**
     * Y solo cuando NO hay ningún canal directo aparece el último recurso — decidido por el
     * servidor, no por el cliente.
     */
    public function test_the_fallback_appears_only_without_any_direct_channel(): void
    {
        $this->contact();

        $response = $this->getJson(self::PATH)->assertOk()->assertValidResponse(200);

        $response->assertJsonPath('notice.phone', null)
            ->assertJsonPath('notice.whatsapp', null);

        $this->assertSame(route('contacto'), $response->json('notice.contact_url'));
    }

    /** Un teléfono en blancos no es un teléfono: no puede colarse como canal. */
    public function test_a_blank_phone_is_not_a_channel(): void
    {
        $this->contact(phone: '   ');

        $this->getJson(self::PATH)
            ->assertOk()
            ->assertJsonPath('notice.phone', null)
            ->assertJsonPath('notice.phone_tel', null);
    }

    // ── Lo que NO puede llevar ────────────────────────────────────────────────────────────────

    /** Es público y describe la instalación: la sesión no puede cambiar ni una coma. */
    public function test_the_answer_is_the_same_with_and_without_a_session(): void
    {
        $this->pause();
        $this->contact(phone: '968 12 34 56');

        $anonymous = $this->getJson(self::PATH)->assertOk()->json();
        $authenticated = $this->actingAs(User::factory()->create())->getJson(self::PATH)->assertOk()->json();

        $this->assertSame($anonymous, $authenticated);
    }
}
