<?php

namespace Tests\Unit;

use App\Domain\Identity\Services\CookieConsent;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * #219 — Autoridad del consentimiento de cookies (servicio puro, sin BD → tests/Unit, conv. §3.ter).
 * Verifica el fail-safe a privacidad: cualquier cookie ausente/corrupta/caducada ⇒ no consentido.
 */
class CookieConsentStateTest extends TestCase
{
    private function requestWith(?string $cookieValue): Request
    {
        $cookies = $cookieValue === null ? [] : [CookieConsent::COOKIE_NAME => $cookieValue];

        return Request::create('/', 'GET', [], $cookies);
    }

    public function test_no_cookie_is_not_decided_and_blocks_everything(): void
    {
        $state = CookieConsent::state($this->requestWith(null));

        $this->assertFalse($state['decided']);
        $this->assertFalse($state['maps']);
        $this->assertFalse($state['social']);
    }

    public function test_valid_cookie_round_trips(): void
    {
        $value = CookieConsent::encode(['maps' => true, 'social' => false]);

        $state = CookieConsent::state($this->requestWith($value));

        $this->assertTrue($state['decided']);
        $this->assertTrue($state['maps']);
        $this->assertFalse($state['social']);
    }

    public function test_corrupt_cookie_is_not_decided(): void
    {
        foreach (['garbage', base64_encode('not-json'), base64_encode('{"broken":')] as $bad) {
            $state = CookieConsent::state($this->requestWith($bad));

            $this->assertFalse($state['decided'], "valor: {$bad}");
            $this->assertFalse($state['maps']);
            $this->assertFalse($state['social']);
        }
    }

    public function test_stale_policy_version_forces_reconsent(): void
    {
        // Cookie de una versión anterior de la política → se ignora (re-pedir, Guía AEPD).
        $value = base64_encode((string) json_encode(['v' => '1999-01-01', 'cats' => ['maps' => true, 'social' => true]]));

        $state = CookieConsent::state($this->requestWith($value));

        $this->assertFalse($state['decided']);
        $this->assertFalse($state['maps']);
        $this->assertFalse($state['social']);
    }

    public function test_encode_only_emits_known_categories_and_current_version(): void
    {
        $value = CookieConsent::encode(['maps' => true, 'social' => true, 'evil' => true]);
        $decoded = json_decode(base64_decode($value, true), true);

        $this->assertSame(CookieConsent::POLICY_VERSION, $decoded['v']);
        $this->assertSame(['maps', 'social'], array_keys($decoded['cats']));
        $this->assertTrue($decoded['cats']['maps']);
        $this->assertTrue($decoded['cats']['social']);
    }

    public function test_missing_categories_default_to_false(): void
    {
        $value = base64_encode((string) json_encode(['v' => CookieConsent::POLICY_VERSION, 'cats' => ['maps' => true]]));

        $state = CookieConsent::state($this->requestWith($value));

        $this->assertTrue($state['decided']);
        $this->assertTrue($state['maps']);
        $this->assertFalse($state['social']);
    }
}
