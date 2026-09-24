<?php

namespace Tests\Unit;

use App\Domain\Identity\Services\CookieConsent;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * #219 — Autoridad del consentimiento de cookies (servicio puro, sin BD → tests/Unit, conv. §3.ter).
 * Verifica el fail-safe a privacidad: cualquier cookie ausente/corrupta/caducada ⇒ no consentido.
 *
 * T3a de la analítica (`specs/analitica.md` §4.3): las categorías salen de `OPTIONAL` —cuatro— y el
 * estado y la cookie las recorren; una cookie de la v2 de la política (dos categorías) está CADUCADA.
 */
class CookieConsentStateTest extends TestCase
{
    private function requestWith(?string $cookieValue): Request
    {
        $cookies = $cookieValue === null ? [] : [CookieConsent::COOKIE_NAME => $cookieValue];

        return Request::create('/', 'GET', [], $cookies);
    }

    public function test_the_four_purposes_of_the_t3_are_the_optional_categories_in_the_order_of_the_panel(): void
    {
        $this->assertSame(['maps', 'social', 'analytics', 'marketing'], CookieConsent::OPTIONAL);
        $this->assertSame(['maps' => false, 'social' => false, 'analytics' => false, 'marketing' => false], CookieConsent::allSetTo(false));
    }

    public function test_no_cookie_is_not_decided_and_blocks_everything(): void
    {
        $state = CookieConsent::state($this->requestWith(null));

        $this->assertFalse($state['decided']);
        foreach (CookieConsent::OPTIONAL as $category) {
            $this->assertFalse($state[$category], $category);
        }
        $this->assertSame([...CookieConsent::OPTIONAL, 'decided'], array_keys($state), 'una clave por categoría y `decided`, en ese orden');
    }

    public function test_valid_cookie_round_trips(): void
    {
        $value = CookieConsent::encode(['maps' => true, 'social' => false, 'analytics' => true, 'marketing' => false]);

        $state = CookieConsent::state($this->requestWith($value));

        $this->assertTrue($state['decided']);
        $this->assertTrue($state['maps']);
        $this->assertFalse($state['social']);
        $this->assertTrue($state['analytics']);
        $this->assertFalse($state['marketing']);
    }

    public function test_corrupt_cookie_is_not_decided(): void
    {
        foreach (['garbage', base64_encode('not-json'), base64_encode('{"broken":')] as $bad) {
            $state = CookieConsent::state($this->requestWith($bad));

            $this->assertFalse($state['decided'], "valor: {$bad}");
            $this->assertFalse($state['maps']);
            $this->assertFalse($state['analytics']);
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

    /** La v2 (`2026-09-13`) solo conocía dos categorías: quien la tenga vuelve a decidir, con las cuatro. */
    public function test_a_cookie_of_the_previous_policy_version_is_stale_too(): void
    {
        $this->assertSame('2026-09-24', CookieConsent::POLICY_VERSION, 'la v3 es la de la T3a');

        $v2 = base64_encode((string) json_encode(['v' => '2026-09-13', 'cats' => ['maps' => true, 'social' => true]]));
        $state = CookieConsent::state($this->requestWith($v2));

        $this->assertFalse($state['decided']);
        $this->assertFalse($state['maps']);
        $this->assertFalse($state['analytics']);
    }

    public function test_encode_only_emits_known_categories_and_current_version(): void
    {
        $value = CookieConsent::encode(['maps' => true, 'social' => true, 'evil' => true]);
        $decoded = json_decode(base64_decode($value, true), true);

        $this->assertSame(CookieConsent::POLICY_VERSION, $decoded['v']);
        $this->assertSame(CookieConsent::OPTIONAL, array_keys($decoded['cats']));
        $this->assertTrue($decoded['cats']['maps']);
        $this->assertTrue($decoded['cats']['social']);
        $this->assertFalse($decoded['cats']['analytics'], 'lo que no se dice es NO');
        $this->assertFalse($decoded['cats']['marketing']);
    }

    public function test_missing_categories_default_to_false(): void
    {
        $value = base64_encode((string) json_encode(['v' => CookieConsent::POLICY_VERSION, 'cats' => ['maps' => true]]));

        $state = CookieConsent::state($this->requestWith($value));

        $this->assertTrue($state['decided']);
        $this->assertTrue($state['maps']);
        $this->assertFalse($state['social']);
        $this->assertFalse($state['analytics']);
        $this->assertFalse($state['marketing']);
    }
}
