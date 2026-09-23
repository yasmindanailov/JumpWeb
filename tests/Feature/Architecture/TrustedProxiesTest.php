<?php

namespace Tests\Feature\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **NINGÚN PROXY DE CONFIANZA** (`SEC-13`, cierre de la T1 de `specs/analitica.md`, `#678`).
 *
 * `bootstrap/app.php` llevaba `trustProxies(at: '*')` desde la Fase 4, escrito suponiendo un proxy delante que
 * terminaba el TLS. Medido en staging el 2026-09-23: PHP habla con LiteSpeed en el mismo host y no hay nada
 * delante — y con `*` la `X-Forwarded-For` del CLIENTE se honraba: 65 peticiones con una XFF falsa rotatoria
 * no tocaban el limitador por IP (0 × 429) mientras 65 sin cabecera caían en la #61. Es decir: cualquier
 * navegador podía saltarse el limitador y falsificar la IP de auditoría.
 *
 * Aquí se fija la conducta —lo que un `grep` no puede— y el fuente, que es donde se vuelve a poner el `*`.
 */
class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_forwarded_headers_sent_by_the_client_are_ignored(): void
    {
        $this->withHeaders([
            'X-Forwarded-For' => '203.0.113.9',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'ejemplo.invalid',
            'X-Forwarded-Port' => '8443',
        ])->get('/api/v1/catalog/zones')->assertOk();

        $request = app('request');

        $this->assertSame('127.0.0.1', $request->ip(), 'la IP la decide el par TCP, no una cabecera que escribe el cliente');
        $this->assertSame('localhost', $request->getHost(), 'el host tampoco');
        $this->assertFalse($request->isSecure(), 'ni el esquema');
    }

    /** Y el fuente: el `*` no vuelve sin que este caso lo diga. Si un día hay un CDN delante, se acota a SUS rangos. */
    public function test_no_wildcard_proxy_trust_is_configured(): void
    {
        $bootstrap = (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', (string) file_get_contents(base_path('bootstrap/app.php')));

        $this->assertDoesNotMatchRegularExpression(
            '/trustProxies\s*\(\s*at:\s*[\'"]\*+[\'"]/',
            $bootstrap,
            "`trustProxies(at: '*')` ha vuelto: confiaría en el CLIENTE como proxy (medido: la XFF falsa se honraba)",
        );
    }
}
