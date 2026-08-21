<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\MaintenanceSettings;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reservas en pausa (#218, item 3) — helper fail-safe + cara visible.
 *
 * Los botones de «Reservar» NO cambian: siguen abriendo el sidecart. Lo que cambia es que, con las
 * reservas en pausa, el SIDECART muestra un aviso de mantenimiento (mensaje EDITABLE desde el panel)
 * + los canales de contacto (teléfono / WhatsApp) en lugar del flujo de compra. El banner global se
 * RETIRÓ (2026-06-16, decisión clienta): el aviso vive SOLO en el sidecart. El guard de servidor
 * (enforcement) se cubre en `ReservationPauseGuardTest`.
 *
 * ⚠️ **Los cinco casos `sidecart_*` se fueron con `Tickets\Purchase`** (4.7·2b·3, ejecutando la
 * clasificación que `DECISIONES #98` dejó medida el 2026-08-16: el fichero NO era homogéneo y había
 * que operar DENTRO). Los seis que quedan no tocaban el componente —son de `MaintenanceSettings`:
 * el fail-safe, que solo el literal «1» pausa, el valor corrupto que deja las reservas abiertas, la
 * ausencia de banner global y los dos de textos— y su guarda no dependía de la retirada.
 *
 * El equivalente de los cinco estaba localizado y medido antes de borrarlos, uno a uno, en
 * `SidebarPausedParityTest` (desde `#81`):
 *
 *  · título y mensaje editables → `test_the_title_and_message_come_from_the_panel_not_from_the_
 *    dictionary` (y `test_the_notice_is_the_panels_text_in_every_locale`);
 *  · flujo normal con las reservas abiertas → `test_nothing_is_covered_when_reservations_are_open`;
 *  · canales de teléfono y WhatsApp → `test_the_contact_links_come_from_the_panel_settings`, que los
 *    recorre en sus CUATRO estados —incluido el respaldo a `/contacto` sin ninguno—, más
 *    `test_the_call_button_shows_one_form_of_the_phone_and_links_the_other`;
 *  · en qué pasos tapa → `test_the_notice_covers_exactly_the_steps_it_must_cover`.
 *
 * Medido entonces: quitando el WhatsApp de `BookingStatusResource` caían tres casos y **dos
 * sobrevivían** (`Api\V1\BookingStatusTest::test_both_channels_travel_together` y esa paridad),
 * mientras que este fichero **no caía** — que es justo la prueba de que ejercía el camino del Blade.
 */
class ReservationPauseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']); // copy de la web determinista
    }

    private function pause(): void
    {
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1', 'group' => 'maintenance']);
    }

    // ─── Helper: dirección fail-safe ────────────────────────────────────────────

    public function test_reservations_open_by_default(): void
    {
        $this->assertFalse(MaintenanceSettings::reservationsPaused());
    }

    public function test_corrupt_value_keeps_reservations_open(): void
    {
        foreach (['no', '2', 'false', 'off', ''] as $bad) {
            Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => $bad, 'group' => 'maintenance']);
            $this->assertFalse(MaintenanceSettings::reservationsPaused(), "«{$bad}» NO debe pausar las reservas.");
        }
    }

    public function test_only_literal_one_pauses_reservations(): void
    {
        $this->pause();
        $this->assertTrue(MaintenanceSettings::reservationsPaused());
    }

    // ─── El banner global se RETIRÓ (2026-06-16): el aviso vive SOLO en el sidecart ──────────────

    public function test_no_global_banner_even_when_paused(): void
    {
        // Decisión clienta (2026-06-16): el banner empujaba el hero bajo el nav fijo; se retiró. El
        // aviso de reservas en pausa se muestra únicamente al abrir el sidecart de compra.
        $this->pause();
        $this->get('/')->assertOk()->assertDontSee('resv-banner');
    }

    // ─── Título y mensaje del aviso EDITABLES desde el panel (override con fallback i18n) ─────────

    public function test_paused_texts_fall_back_to_default(): void
    {
        $this->assertSame(__('tickets.paused.title', [], 'es'), MaintenanceSettings::reservationTitle('es'));
        $this->assertSame(__('tickets.paused.body', [], 'es'), MaintenanceSettings::reservationMessage('es'));
    }

    public function test_paused_texts_use_panel_override(): void
    {
        Setting::updateOrCreate(['key' => 'reservations.title.es'], ['value' => 'Reservas en pausa', 'group' => 'maintenance']);
        Setting::updateOrCreate(['key' => 'reservations.message.es'], ['value' => 'Volvemos el 20 de junio, reserva por teléfono.', 'group' => 'maintenance']);

        $this->assertSame('Reservas en pausa', MaintenanceSettings::reservationTitle('es'));
        $this->assertSame('Volvemos el 20 de junio, reserva por teléfono.', MaintenanceSettings::reservationMessage('es'));
    }
}
