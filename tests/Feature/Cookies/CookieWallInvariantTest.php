<?php

namespace Tests\Feature\Cookies;

use App\Domain\Identity\Services\CookieConsent;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * #219 — Invariante de diseño: NO hay «cookie wall». Rechazar (o no decidir) las cookies de tercero
 * NUNCA degrada el acceso a la web ni a la reserva, que dependen solo de cookies técnicas exentas
 * (AEPD: un muro de cookies sin alternativa es ilícito). El banner es un overlay, no un bloqueo.
 */
class CookieWallInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Cache::flush();
    }

    public function test_reservation_ui_available_without_any_consent(): void
    {
        $this->get('/')->assertOk()->assertSee('sidecart__panel', false);
    }

    public function test_reservation_ui_available_after_rejecting_all_cookies(): void
    {
        $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, CookieConsent::encode(['maps' => false, 'social' => false]))
            ->get('/')
            ->assertOk()
            ->assertSee('sidecart__panel', false);
    }

    public function test_purchase_entrypoint_reachable_when_rejected(): void
    {
        // El enlace profundo /entradas (abre el sidecart de compra) sigue 200 con cookies rechazadas.
        $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, CookieConsent::encode(['maps' => false, 'social' => false]))
            ->get('/entradas')
            ->assertOk();
    }
}
