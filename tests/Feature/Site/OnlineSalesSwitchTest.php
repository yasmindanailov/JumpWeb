<?php

namespace Tests\Feature\Site;

use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LA COMPRA ONLINE SE PUEDE CERRAR, y el que cierra es el SERVIDOR** (lanzamiento 2026-09-01).
 *
 * `[DECIDIDO owner]`: la web sale a producción sin claves de Redsys, con el catálogo visible y la
 * compra cerrada. Dos mitades, y la guarda mira las dos por separado a propósito:
 *
 *  1. **La API dice que no** (503 `online_sales_disabled`) en las rutas que hacen avanzar una
 *     compra. Es la mitad que importa: la SPA cargada en un navegador sigue pudiendo llamar a la
 *     API aunque el Blade haya escondido el botón.
 *  2. **El Blade lo ENSEÑA**: los CTA de compra pasan a `tel:` y ningún `purchase.open()` queda
 *     en la página. Sin esto el visitante pulsa «Reservar», el cajón se abre, y el pedido muere
 *     con un 503 que él no entiende.
 *
 * Con el interruptor ausente o en `1` NADA cambia: es la conducta de siempre, y el caso de control
 * lo fija para que una instalación existente no se cierre por un valor por defecto mal leído.
 */
class OnlineSalesSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function salesOnline(bool $on): void
    {
        Setting::query()->updateOrCreate(['key' => 'sales.online_enabled'], ['value' => $on ? '1' : '0', 'group' => 'sales']);
        Setting::flushMemo();
    }

    public function test_the_purchase_api_answers_503_when_online_sales_are_closed(): void
    {
        $this->salesOnline(false);

        $this->postJson('/api/v1/orders/quote', [])
            ->assertStatus(503)
            ->assertJsonPath('code', 'online_sales_disabled');

        $this->postJson('/api/v1/cart/validate-line', [])
            ->assertStatus(503)
            ->assertJsonPath('code', 'online_sales_disabled');
    }

    public function test_the_purchase_api_is_untouched_when_online_sales_are_open(): void
    {
        // Control: con el interruptor en 1 (o ausente) la ruta llega a su validación de siempre,
        // que con una cesta vacía responde 422 y NUNCA 503.
        $this->salesOnline(true);
        $this->postJson('/api/v1/orders/quote', [])->assertStatus(422);

        Setting::query()->where('key', 'sales.online_enabled')->delete();
        Setting::flushMemo();
        $this->postJson('/api/v1/orders/quote', [])->assertStatus(422);
    }

    public function test_the_pricing_page_offers_the_phone_instead_of_the_drawer_when_closed(): void
    {
        Setting::query()->updateOrCreate(['key' => 'contact.phone'], ['value' => '+34 641 99 57 14', 'group' => 'contact']);
        $this->salesOnline(false);

        $html = $this->get('/precios')->assertOk()->getContent();

        $this->assertStringNotContainsString('purchase.open()', $html, 'con la compra cerrada, /precios sigue abriendo el cajón');
        $this->assertStringContainsString('href="tel:', $html, 'con la compra cerrada, /precios no ofrece el teléfono');
    }

    public function test_the_pricing_page_opens_the_drawer_when_open(): void
    {
        $this->salesOnline(true);

        $this->assertStringContainsString('purchase.open()', $this->get('/precios')->assertOk()->getContent());
    }
}
