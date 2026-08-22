<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\User;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 0 — `GET /api/v1/me`, el primer endpoint de la API.
 *
 * Además de probar el endpoint, es el test que demuestra que los CIMIENTOS funcionan de verdad:
 * enrutado con prefijo, guard de Sanctum, sobre de error, `RGPD-04` por defecto y validación de la
 * respuesta real contra `openapi/v1.yaml` (no una comparación de listas de rutas — spec §2.2).
 */
class MeTest extends ApiTestCase
{
    public function test_it_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'phone' => '600111222',
            'locale' => 'es',
            'marketing_opt_in' => true,
        ]);

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me');

        $response->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJson([
                'id' => $user->id,
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.test',
                'phone' => '600111222',
                'locale' => 'es',
                'marketing_opt_in' => true,
            ]);
    }

    /**
     * El recurso va en la RAÍZ, no envuelto en `data` (spec §4.3). Un cliente que tuviera que
     * desenvolver unas respuestas sí y otras no acabaría equivocándose en alguna.
     */
    public function test_the_resource_is_not_wrapped(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me');

        $this->assertArrayNotHasKey('data', $response->json());
        $this->assertSame($user->id, $response->json('id'));
    }

    /**
     * Allowlist, no denylist (`SEC-10`): la respuesta contiene EXACTAMENTE los campos declarados
     * en el contrato. Si alguien añade una columna al modelo, este test no cambia y la columna no
     * sale — que es la dirección segura del error.
     */
    public function test_it_exposes_only_the_allowlisted_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me');

        $this->assertSame([
            'id',
            'name',
            'email',
            'phone',
            'locale',
            'marketing_opt_in',
            'email_verified_at',
            'pending_email',
            'pending_email_sent_at',
            // ⚠️ **Derivado, no una columna**: lo compone `AccountProfile::pendingEmailExpiresAt()`
            // para que el cliente no tenga que quemar la ventana de validez en su código (tanda 2 ·
            // paso 7). No añade PII — es un instante calculado sobre un campo que ya salía.
            'pending_email_expires_at',
            'created_at',
        ], array_keys($response->json()));
    }

    /** Ninguna credencial sale por la API, ni siquiera su hash. */
    public function test_it_never_leaks_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me');

        $response->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token');
        $this->assertStringNotContainsString($user->getAuthPassword(), $response->getContent() ?: '');
    }

    /**
     * Registro «pay-first»: la cuenta existe sin verificar y el cliente debe poder leer ese estado
     * para ofrecer el reenvío. Por eso `me` NO exige `verified`, a diferencia de `/mi-cuenta`.
     */
    public function test_an_unverified_account_can_read_its_own_profile(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->getJson(self::ROOT.'/me')
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('email_verified_at', null);
    }

    /** `RGPD-04`: toda respuesta autenticada de la API es `no-store`. */
    public function test_the_authenticated_response_is_not_stored(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me');

        // Se comprueba la directiva, no la cadena: Symfony normaliza y reordena `Cache-Control`
        // alfabéticamente. Mismo criterio que el resto de superficies con PII del repo
        // (`PrivacyTest`, `GuestFormTest`, `ReservationSlipTest`).
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('Pragma', 'no-cache');
    }

    /** Sin identidad no hay perfil, y el rechazo llega en el sobre único, no como redirección. */
    public function test_it_rejects_an_anonymous_request(): void
    {
        $this->getJson(self::ROOT.'/me')
            ->assertUnauthorized()
            ->assertValidResponse(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    /**
     * La identidad sale del guard y solo del guard: no hay parámetro que manipular. El test fija
     * esa propiedad para que un refactor futuro (p. ej. aceptar `?user=`) no la pierda en silencio.
     */
    public function test_each_user_only_ever_sees_their_own_profile(): void
    {
        $ada = User::factory()->create(['name' => 'Ada']);
        $grace = User::factory()->create(['name' => 'Grace']);

        $this->actingAs($ada)->getJson(self::ROOT.'/me')->assertJsonPath('name', 'Ada');
        $this->actingAs($grace)->getJson(self::ROOT.'/me')->assertJsonPath('name', 'Grace');
    }
}
