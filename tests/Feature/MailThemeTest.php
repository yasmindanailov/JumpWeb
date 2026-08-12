<?php

namespace Tests\Feature;

use App\Domain\Platform\Models\Setting;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica que el theme propio `brand` se aplica a los correos generados por `MailMessage`
 * y que los tokens de marca clave (color de acento, wordmark, tipografía) llegan al HTML
 * final. No es un snapshot rígido — solo asserta lo que define la identidad visual; el resto
 * del CSS puede evolucionar sin romper este test.
 */
class MailThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_theme_is_active_in_mail_config(): void
    {
        $this->assertSame('brand', config('mail.markdown.theme'));
    }

    public function test_brand_tokens_render_in_order_confirmation_email(): void
    {
        // Wordmark data-driven (Fase 1): el header lee `business.name` de BD, no config.
        Setting::create(['key' => 'business.name', 'value' => 'Negocio Demo', 'group' => 'business']);
        Setting::flushMemo();

        $user = User::factory()->create(['locale' => 'es']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-MAIL01',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1500, 'total' => 1500, 'currency' => 'EUR',
        ]);

        $html = (new OrderConfirmation($order))->toMail($user)->render();

        // Wordmark del header = nombre del negocio (BD) + punto en color de marca.
        $this->assertStringContainsString('Negocio Demo', $html);
        $this->assertStringContainsString('brand-dot', $html);

        // Color de acento por defecto del producto (ThemeSettings::DEFAULT_BRAND).
        $this->assertStringContainsString('#FF5B22', $html);

        // Tipografía Space Grotesk (con fallback defensivo a system-ui en caso de no cargar).
        $this->assertStringContainsString('Space Grotesk', $html);

        // CTA principal con el texto traducido (acción a "Mis pedidos").
        $this->assertStringContainsString('Ver mis reservas', $html);

        // Botón = el de la landing: fondo brand + texto blanco (--on-brand) + radius 14px (--r-btn).
        $this->assertStringContainsString('border-radius: 14px', $html);
        $this->assertStringContainsString('color: #FFFFFF', $html);
    }

    public function test_email_wordmark_falls_back_to_app_name_without_business_name(): void
    {
        // Instalación recién migrada sin settings: el header no puede quedar vacío.
        $user = User::factory()->create(['locale' => 'es']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-MAIL02',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
        ]);

        $html = (new OrderConfirmation($order))->toMail($user)->render();

        $this->assertStringContainsString(config('app.name'), $html);
    }
}
