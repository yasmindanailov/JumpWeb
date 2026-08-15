<?php

namespace Tests\Feature\Sales;

use App\Domain\Platform\Models\Setting;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Registro «del parque» en la pantalla final de la compra (#223, paso 6): si hay URL de registro
 * externo configurada (apartado Registro del panel), se muestra un botón (etiqueta del config) +
 * un texto informativo (campo nuevo `registration.description.{loc}`, con fallback i18n).
 *
 * ⚠️ **Muere con el componente, y es REDUNDANTE ya hoy** (medido el 2026-08-15, `DECISIONES #95`).
 * Sus cuatro reglas las cubre `Api\V1\PublicConfigTest`, que es quien publica el bloque:
 * `test_the_registration_block_travels_translated`, `test_an_untranslated_block_falls_back_to_the_
 * default_wording`, `test_a_dangerous_registration_url_never_reaches_the_client` —con CUATRO URLs
 * hostiles, que aquí no se prueban— y el `registration: null` de una instalación desnuda.
 * Medido quitando los `?:` de `RegistrationLink::current()`: caen dos casos, y el superviviente es el
 * de `PublicConfigTest`. El quinto caso —en qué pasos se enseña— es superficie pura.
 */
class PurchaseRegistrationPromptTest extends TestCase
{
    use RefreshDatabase;

    private function set(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'registration']);
    }

    public function test_confirmation_shows_registration_block_when_url_configured(): void
    {
        app()->setLocale('es');
        $this->set('registration.url', 'https://registro.ejemplo.com/alta');
        $this->set('registration.label.es', 'Regístrate en el parque');
        $this->set('registration.description.es', 'Completa tu registro de acceso antes de venir.');

        Livewire::test(Purchase::class)
            ->set('step', 6)
            ->assertSee('purchase__reginfo', false)
            ->assertSee('Regístrate en el parque')                        // etiqueta del botón (config)
            ->assertSee('Completa tu registro de acceso antes de venir.')  // texto del config
            ->assertSee('https://registro.ejemplo.com/alta', false)        // enlace externo
            ->assertSee('target="_blank"', false);
    }

    public function test_label_and_description_fall_back_to_defaults_when_empty(): void
    {
        app()->setLocale('es');
        $this->set('registration.url', 'https://registro.ejemplo.com/alta');
        // Sin label ni description → caen a los textos i18n por defecto.

        Livewire::test(Purchase::class)
            ->set('step', 6)
            ->assertSee('purchase__reginfo', false)
            ->assertSee(__('landing.nav.register'))        // etiqueta por defecto «Registro»
            ->assertSee(__('landing.nav.register_info'));  // texto por defecto
    }

    public function test_no_registration_block_without_url(): void
    {
        Livewire::test(Purchase::class)
            ->set('step', 6)
            ->assertDontSee('purchase__reginfo', false);
    }

    public function test_non_http_url_is_rejected(): void
    {
        // Defensa: solo http(s); un esquema peligroso por BD directa no debe colar un href.
        $this->set('registration.url', 'javascript:alert(1)');

        Livewire::test(Purchase::class)
            ->set('step', 6)
            ->assertDontSee('purchase__reginfo', false);
    }

    public function test_block_not_shown_on_earlier_steps(): void
    {
        $this->set('registration.url', 'https://registro.ejemplo.com/alta');

        Livewire::test(Purchase::class)
            ->set('step', 1)
            ->assertDontSee('purchase__reginfo', false);
    }
}
