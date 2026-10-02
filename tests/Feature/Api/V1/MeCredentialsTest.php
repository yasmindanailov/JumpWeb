<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use App\Domain\Identity\Services\AccountCredentials;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Api\ApiTestCase;
use Tests\Support\IssuesCodes;

/**
 * **Tanda 2 · paso 6** — `POST /api/v1/me/sessions/revoke-others` y las identidades externas (`specs/area-cliente.md` §9).
 *
 * Lo que estas guardas protegen:
 *  - que cerrar las demás **revoque las otras credenciales y conserve la propia** (`RGPD-06`);
 *  - que la reconfirmación esté **LIMITADA** (`DECISIONES #120(n)`): cinco fallos por (titular, IP), también con el bueno,
 *    y acertar limpia el contador;
 *  - y que el código equivocado sea un **422 por campo y no un 401**: aquí ya sabemos quién es.
 *
 * ▶ Desde la A5 (`specs/acceso-con-codigo.md` §4.12, `#869`) se reconfirma solo con un CÓDIGO `confirm`. Lo que probaba
 * `PUT /me/password` se fue con él; el limitador, cerrar las demás y desvincular sobreviven y se re-apuntaron al código
 * (`CONVENCIONES` §3.quater).
 */
class MeCredentialsTest extends ApiTestCase
{
    use IssuesCodes;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear($this->limiterKey());
    }

    private function holder(): User
    {
        return User::factory()->create([
            'name' => 'Titular', 'email' => 'titular@ejemplo.test', 'email_verified_at' => now(), 'password' => null,
        ]);
    }

    private function limiterKey(int $id = 1): string
    {
        return 'account-credentials:'.$id.'|127.0.0.1';
    }

    // ── El limitador de la reconfirmación ─────────────────────────────────────────────────────

    public function test_it_blocks_after_five_wrong_codes_and_says_how_long(): void
    {
        $user = $this->holder();

        for ($i = 0; $i < AccountCredentials::MAX_ATTEMPTS; $i++) {
            $this->actingAs($user)
                ->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => '000000'])
                ->assertStatus(422);
        }

        $blocked = $this->actingAs($user)->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => '000000']);

        $blocked->assertStatus(429)->assertValidResponse(429);
        $this->assertNotEmpty($blocked->headers->get('Retry-After'), 'sin `Retry-After` el cliente no sabe cuándo volver');

        // ⚠️ Y **con el código BUENO también corta**: si el bloqueo se levantara al acertar, quien tiene la sesión podría
        // seguir probando indefinidamente intercalando el intento correcto. El techo es del intento, no del acierto.
        $this->actingAs($user)
            ->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => $this->confirmCodeFor($user)])
            ->assertStatus(429);
    }

    /** ⚠️ Y acertar LIMPIA el contador: el dueño legítimo no arrastra sus fallos el resto del minuto. */
    public function test_a_successful_attempt_clears_the_counter(): void
    {
        $user = $this->holder();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)
                ->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => '000000'])
                ->assertStatus(422);
        }

        $this->actingAs($user)
            ->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => $this->confirmCodeFor($user)])
            ->assertNoContent();

        $this->assertSame(0, RateLimiter::attempts($this->limiterKey($user->id)), 'los fallos de antes siguen contando después de acertar');
    }

    // ── Cerrar las demás sesiones ─────────────────────────────────────────────────────────────

    public function test_it_revokes_the_other_credentials(): void
    {
        $user = $this->holder();
        $user->createToken('otro-dispositivo');

        $this->actingAs($user)
            ->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => $this->confirmCodeFor($user)])
            ->assertNoContent()
            ->assertValidResponse(204);

        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
    }

    public function test_a_wrong_code_is_a_422_on_its_field_and_revokes_nothing(): void
    {
        $user = $this->holder();
        $user->createToken('otro-dispositivo');

        $response = $this->actingAs($user)
            ->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => $this->wrongCode($this->confirmCodeFor($user))]);

        // ⚠️ 422 y NO 401: el cliente **sí** está autenticado, y un 401 le diría «tu sesión no vale» cuando lo que pasa es
        // que se ha equivocado escribiendo.
        $response->assertStatus(422)->assertValidResponse(422);
        $this->assertNotEmpty($response->json('error.fields.code'));
        $this->assertSame(
            1, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count(),
            'se han revocado credenciales sin confirmar la identidad'
        );
    }

    // ── Puerta ────────────────────────────────────────────────────────────────────────────────

    public function test_it_rejects_an_anonymous_request(): void
    {
        $this->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => '123456'])->assertStatus(401);
    }

    /** El cuerpo es obligatorio: sin código no hay reconfirmación que valga. */
    public function test_the_code_is_required(): void
    {
        $this->actingAs($this->holder())->postJson(self::ROOT.'/me/sessions/revoke-others', [])->assertStatus(422);
    }

    // ── Las identidades externas (`specs/auth-con-google.md` §8) ──────────────────────────────

    public function test_it_lists_the_linked_accounts_without_the_provider_identifier(): void
    {
        $user = $this->holder();
        $this->linkGoogle($user);

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/identities');

        $response->assertOk()->assertValidResponse(200);
        $this->assertSame('google', $response->json('data.0.provider'));
        $this->assertSame('ana@gmail.test', $response->json('data.0.email_at_link'));

        // ⚠️ El `sub` NO sale: la pantalla necesita saber con qué cuenta se entra, no su identificador
        // en el proveedor. Ése viaja en el export del art. 20, que es un acto explícito del titular.
        $this->assertStringNotContainsString('110000000000000000001', (string) $response->getContent());
    }

    /**
     * **Desvincular es el contrapeso del aviso de vinculación**: sin esto, la única salida de un
     * vínculo que no se pidió era borrar la cuenta.
     */
    public function test_it_unlinks_with_the_code(): void
    {
        $user = $this->holder();
        $this->linkGoogle($user);

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me/identities/google', ['code' => $this->confirmCodeFor($user)])
            ->assertNoContent()
            ->assertValidResponse(204);

        $this->assertSame(0, UserIdentity::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'identities.unlinked', 'target_id' => $user->id]);
    }

    /**
     * ⚠️⚠️ **Y con el código equivocado el vínculo SOBREVIVE.** Es la mitad que de verdad importa: un endpoint que
     * borrara primero y comprobara después dejaría a cualquiera con una sesión robada quitar la forma de entrar del titular.
     */
    public function test_a_wrong_code_leaves_the_link_alone(): void
    {
        $user = $this->holder();
        $this->linkGoogle($user);

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me/identities/google', ['code' => $this->wrongCode($this->confirmCodeFor($user))])
            ->assertStatus(422)
            ->assertValidResponse(422);

        $this->assertSame(1, UserIdentity::query()->count());
    }

    /** Idempotente: el titular pide un ESTADO —«que no haya vínculo»—, no una transición. */
    public function test_unlinking_what_is_not_there_is_fine(): void
    {
        $user = $this->holder();

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me/identities/google', ['code' => $this->confirmCodeFor($user)])
            ->assertNoContent();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'identities.unlinked']);
    }

    public function test_the_identity_endpoints_reject_an_anonymous_request(): void
    {
        $this->getJson(self::ROOT.'/me/identities')->assertStatus(401);
        $this->deleteJson(self::ROOT.'/me/identities/google', ['code' => '123456'])->assertStatus(401);
    }

    /** Un proveedor que no existe no es una ruta: sin esto, `DELETE /me/identities/lo-que-sea` pasaría. */
    public function test_an_unknown_provider_is_not_a_route(): void
    {
        $user = $this->holder();

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me/identities/inventado', ['code' => $this->confirmCodeFor($user)])
            ->assertNotFound();
    }

    private function linkGoogle(User $user): UserIdentity
    {
        return UserIdentity::create([
            'user_id' => $user->id,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_id' => '110000000000000000001',
            'email_at_link' => 'ana@gmail.test',
            'linked_via' => UserIdentity::VIA_LOGIN,
            'linked_at' => now()->subDay(),
        ]);
    }
}
