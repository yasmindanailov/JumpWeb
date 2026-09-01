<?php

namespace Tests\Feature;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
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

    /**
     * Un `public/` TEMPORAL para que el caso no dependa de lo que haya instalado en la máquina:
     * `client-logo@4x.png` está gitignorado (es del cliente), y con él presente la cabecera cambia
     * de rama. La trampa de `#302`: un test que lee un fichero gitignorado pasa aquí y falla en un
     * clon limpio — o al revés.
     */
    private function publicDir(bool $withClientLogo): string
    {
        $dir = sys_get_temp_dir().'/mail-theme-'.uniqid();
        mkdir($dir.'/img', 0777, true);
        if ($withClientLogo) {
            file_put_contents($dir.'/img/client-logo@4x.png', 'png-falso');
        }
        $this->app->usePublicPath($dir);

        return $dir;
    }

    public function test_brand_tokens_render_in_order_confirmation_email(): void
    {
        $this->publicDir(withClientLogo: false);

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

    /**
     * **Con el LOGOTIPO de la instalación presente, la cabecera lo enseña** (lanzamiento 2026-09-01,
     * `#325`): `<img>` al PNG con el nombre del negocio como `alt` — quien bloquea imágenes sigue
     * viendo el wordmark — y ya no se pinta el punto de marca del texto.
     */
    public function test_email_header_uses_the_client_logo_when_the_installation_has_it(): void
    {
        $this->publicDir(withClientLogo: true);
        Setting::create(['key' => 'business.name', 'value' => 'Negocio Demo', 'group' => 'business']);
        Setting::flushMemo();

        $user = User::factory()->create(['locale' => 'es']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-MAIL03',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1500, 'total' => 1500, 'currency' => 'EUR',
        ]);

        $html = (new OrderConfirmation($order))->toMail($user)->render();

        $this->assertStringContainsString('img/client-logo@4x.png', $html);
        $this->assertStringContainsString('alt="Negocio Demo"', $html);
        $this->assertStringNotContainsString('brand-dot', $html);
    }

    public function test_email_wordmark_falls_back_to_app_name_without_business_name(): void
    {
        $this->publicDir(withClientLogo: false);

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
