<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica que el theme propio `jumpingjump` se aplica a los correos generados por
 * `MailMessage` y que los tokens de marca clave (color de acento, wordmark, tipografía)
 * llegan al HTML final. No es un snapshot rígido — solo asserta lo que define la identidad
 * visual; el resto del CSS puede evolucionar sin romper este test.
 */
class MailThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_jumpingjump_theme_is_active_in_mail_config(): void
    {
        $this->assertSame('jumpingjump', config('mail.markdown.theme'));
    }

    public function test_brand_tokens_render_in_order_confirmation_email(): void
    {
        $user = User::factory()->create(['locale' => 'es']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-MAIL01',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1500, 'total' => 1500, 'currency' => 'EUR',
        ]);

        $html = (new OrderConfirmation($order))->toMail($user)->render();

        // Wordmark del header con el punto en color de marca (sustituye al "block" del nav).
        $this->assertStringContainsString('Jumpingjump', $html);
        $this->assertStringContainsString('brand-dot', $html);

        // Color de acento (zona Jump, --zone-1 del mockup).
        $this->assertStringContainsString('#FF5B22', $html);

        // Tipografía Space Grotesk (con fallback defensivo a system-ui en caso de no cargar).
        $this->assertStringContainsString('Space Grotesk', $html);

        // CTA principal con el texto traducido (acción a "Mis pedidos").
        $this->assertStringContainsString('Ver mis reservas', $html);

        // Botón = el de la landing: fondo brand + texto blanco (--on-brand) + radius 14px (--r-btn).
        $this->assertStringContainsString('border-radius: 14px', $html);
        $this->assertStringContainsString('color: #FFFFFF', $html);
    }
}
