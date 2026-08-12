<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\MaintenanceSettings;
use App\Livewire\Tickets\Purchase;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reservas en pausa (#218, item 3) — helper fail-safe + cara visible.
 *
 * Los botones de «Reservar» NO cambian: siguen abriendo el sidecart. Lo que cambia es que, con las
 * reservas en pausa, el SIDECART muestra un aviso de mantenimiento (mensaje EDITABLE desde el panel)
 * + los canales de contacto (teléfono / WhatsApp) en lugar del flujo de compra. El banner global se
 * RETIRÓ (2026-06-16, decisión clienta): el aviso vive SOLO en el sidecart. El guard de servidor
 * (enforcement) se cubre en `ReservationPauseGuardTest`.
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

    public function test_sidecart_shows_editable_paused_title_and_message(): void
    {
        app()->setLocale('es');
        Setting::updateOrCreate(['key' => 'reservations.title.es'], ['value' => 'Reservas en pausa', 'group' => 'maintenance']);
        Setting::updateOrCreate(['key' => 'reservations.message.es'], ['value' => 'Mensaje personalizado de la dueña', 'group' => 'maintenance']);
        $this->pause();

        Livewire::test(Purchase::class)
            ->assertSet('step', 1)
            ->assertSee('Reservas en pausa')                  // título override
            ->assertSee('Mensaje personalizado de la dueña')  // mensaje override
            ->assertDontSee(__('tickets.paused.title'))       // los overrides SUSTITUYEN a los textos por defecto
            ->assertDontSee(__('tickets.paused.body'));
    }

    // ─── Aviso de mantenimiento DENTRO del sidecart ──────────────────────────────

    public function test_sidecart_shows_normal_flow_when_reservations_open(): void
    {
        Livewire::test(Purchase::class)
            ->assertSet('step', 1)
            ->assertSee(__('tickets.section_entries'))          // el catálogo normal
            ->assertDontSee(__('tickets.paused.title'));
    }

    public function test_sidecart_shows_maintenance_notice_with_phone_and_whatsapp_when_paused(): void
    {
        Setting::updateOrCreate(['key' => 'contact.whatsapp'], ['value' => '+34 968 22 22 22', 'group' => 'contact']);
        $this->pause();

        Livewire::test(Purchase::class)
            ->assertSet('step', 1)
            ->assertSee(__('tickets.paused.title'))
            ->assertDontSee(__('tickets.section_entries'))      // el flujo de compra NO se muestra
            ->assertSee('tel:968222222', false)              // CTA «Llamar»
            ->assertSee('wa.me/34968222222', false);         // CTA «WhatsApp» (dígitos)
    }

    public function test_sidecart_notice_shows_only_on_booking_steps(): void
    {
        // Los pasos de RESULTADO de pago (6/9/10/11) y la verificación de email (7) NO muestran el
        // aviso: son acciones ya iniciadas que deben poder completarse aunque se pausen las reservas.
        $this->pause();
        $purchase = Livewire::test(Purchase::class)->instance();

        foreach ([1, 2, 3, 4, 5, 8] as $bookingStep) {
            $purchase->step = $bookingStep;
            $this->assertTrue($purchase->showPausedNotice(), "Paso {$bookingStep} (reserva) → aviso.");
        }
        foreach ([6, 7, 9, 10, 11] as $outcomeStep) {
            $purchase->step = $outcomeStep;
            $this->assertFalse($purchase->showPausedNotice(), "Paso {$outcomeStep} (resultado) → sin aviso.");
        }
    }

    public function test_sidecart_notice_falls_back_to_contact_without_phone_or_whatsapp(): void
    {
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '', 'group' => 'contact']);
        Setting::updateOrCreate(['key' => 'contact.whatsapp'], ['value' => '', 'group' => 'contact']);
        $this->pause();

        Livewire::test(Purchase::class)
            ->assertSee(__('tickets.paused.title'))
            ->assertSee(route('contacto'), false)            // sin tel/WA → enlace a /contacto
            ->assertDontSee('tel:', false)
            ->assertDontSee('wa.me', false);
    }
}
