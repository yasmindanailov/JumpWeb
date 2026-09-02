<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use App\Domain\Identity\Services\AccountCredentials;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Api\ApiTestCase;

/**
 * **Tanda 2 · paso 6** — `PUT /api/v1/me/password` y `POST /api/v1/me/sessions/revoke-others`
 * (`specs/area-cliente.md` §9).
 *
 * Lo que estas guardas protegen, más allá de «cambia la contraseña»:
 *  - que el cambio **revoque las demás credenciales y conserve la propia** (`RGPD-06`): cambiarla
 *    por sospecha de robo no sirve de nada si el intruso conserva un Bearer vivo;
 *  - que los fallos estén **LIMITADOS**, que es lo que la web no hace y esta superficie sí
 *    (`DECISIONES #120(n)`);
 *  - que un formato inválido **no gaste intento**, para no castigar a quien se equivoca escribiendo;
 *  - y que la contraseña equivocada sea un **422 por campo y no un 401**: aquí ya sabemos quién es.
 */
class MeCredentialsTest extends ApiTestCase
{
    //  ya lo declara  (protected): redeclararlo en privado rompe la herencia.

    private const PASSWORD = 'contrasena-actual-9';

    private const NUEVA = 'Rd8!zqLm4-Vt7wXe';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear($this->limiterKey());
    }

    private function holder(): User
    {
        $user = new User;
        $user->name = 'Titular';
        $user->email = 'titular@ejemplo.test';
        $user->password = self::PASSWORD;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function limiterKey(int $id = 1): string
    {
        return 'account-credentials:'.$id.'|127.0.0.1';
    }

    // ── Cambiar la contraseña ─────────────────────────────────────────────────────────────────

    public function test_it_changes_the_password_and_the_old_one_stops_working(): void
    {
        $user = $this->holder();

        $this->actingAs($user)
            ->putJson(self::ROOT.'/me/password', [
                'current_password' => self::PASSWORD,
                'password' => self::NUEVA,
            ])
            ->assertNoContent();

        $user->refresh();

        $this->assertTrue(Hash::check(self::NUEVA, (string) $user->password));
        $this->assertFalse(Hash::check(self::PASSWORD, (string) $user->password), 'la anterior sigue valiendo');
    }

    /**
     * ⚠️ **La mitad que de verdad importa**: sin ella, cambiar la contraseña por sospecha de robo
     * dejaría al intruso dentro con su token (`RGPD-06`).
     */
    public function test_it_revokes_the_other_credentials_and_keeps_the_current_one(): void
    {
        $user = $this->holder();
        $user->createToken('movil-viejo');
        $user->createToken('portatil-ajeno');

        $this->assertSame(2, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());

        $this->actingAs($user)
            ->putJson(self::ROOT.'/me/password', [
                'current_password' => self::PASSWORD,
                'password' => self::NUEVA,
            ])
            ->assertNoContent();

        $this->assertSame(
            0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count(),
            'los tokens de los otros dispositivos han sobrevivido al cambio de contraseña'
        );
    }

    public function test_a_wrong_current_password_is_a_422_on_its_field_and_changes_nothing(): void
    {
        $user = $this->holder();

        $response = $this->actingAs($user)
            ->putJson(self::ROOT.'/me/password', [
                'current_password' => 'no-es-esta',
                'password' => self::NUEVA,
            ]);

        // ⚠️ 422 y NO 401: el cliente **sí** está autenticado, y un 401 le diría «tu sesión no vale»
        // cuando lo que pasa es que se ha equivocado escribiendo.
        $response->assertStatus(422)->assertValidResponse(422);
        $this->assertNotEmpty($response->json('error.fields.current_password'));

        $this->assertTrue(Hash::check(self::PASSWORD, (string) $user->refresh()->password));
    }

    /**
     * ⚠️ **Un formato inválido NO gasta intento.** Si lo gastara, quien escribe una contraseña nueva
     * demasiado corta cinco veces se quedaría bloqueado sin haber intentado adivinar nada.
     */
    public function test_a_weak_new_password_does_not_burn_a_rate_limit_attempt(): void
    {
        $user = $this->holder();

        for ($i = 0; $i < AccountCredentials::MAX_ATTEMPTS + 2; $i++) {
            $this->actingAs($user)
                ->putJson(self::ROOT.'/me/password', [
                    'current_password' => self::PASSWORD,
                    'password' => 'corta',
                ])
                ->assertStatus(422);
        }

        // Y la de verdad sigue funcionando: no ha quedado bloqueado por equivocarse de formato.
        $this->actingAs($user)
            ->putJson(self::ROOT.'/me/password', [
                'current_password' => self::PASSWORD,
                'password' => self::NUEVA,
            ])
            ->assertNoContent();
    }

    // ── El limitador ──────────────────────────────────────────────────────────────────────────

    public function test_it_blocks_after_five_wrong_attempts_and_says_how_long(): void
    {
        $user = $this->holder();

        for ($i = 0; $i < AccountCredentials::MAX_ATTEMPTS; $i++) {
            $this->actingAs($user)
                ->putJson(self::ROOT.'/me/password', ['current_password' => 'mal-'.$i, 'password' => self::NUEVA])
                ->assertStatus(422);
        }

        $blocked = $this->actingAs($user)
            ->putJson(self::ROOT.'/me/password', ['current_password' => 'mal-otra', 'password' => self::NUEVA]);

        $blocked->assertStatus(429)->assertValidResponse(429);
        $this->assertNotEmpty($blocked->headers->get('Retry-After'), 'sin `Retry-After` el cliente no sabe cuándo volver');

        // ⚠️ Y **con la contraseña BUENA también corta**: si el bloqueo se levantara al acertar, un
        // atacante podría seguir probando indefinidamente intercalando el intento correcto del día
        // que dé con ella. El techo es del intento, no del acierto.
        $this->actingAs($user)
            ->putJson(self::ROOT.'/me/password', ['current_password' => self::PASSWORD, 'password' => self::NUEVA])
            ->assertStatus(429);
    }

    /** ⚠️ Y acertar LIMPIA el contador: el dueño legítimo no arrastra sus fallos el resto del minuto. */
    public function test_a_successful_attempt_clears_the_counter(): void
    {
        $user = $this->holder();

        foreach (['mal-1', 'mal-2', 'mal-3'] as $wrong) {
            $this->actingAs($user)
                ->putJson(self::ROOT.'/me/password', ['current_password' => $wrong, 'password' => self::NUEVA])
                ->assertStatus(422);
        }

        $this->actingAs($user)
            ->putJson(self::ROOT.'/me/password', ['current_password' => self::PASSWORD, 'password' => self::NUEVA])
            ->assertNoContent();

        $this->assertSame(
            0, RateLimiter::attempts($this->limiterKey($user->id)),
            'los fallos de antes siguen contando después de acertar'
        );
    }

    // ── Cerrar las demás sesiones ─────────────────────────────────────────────────────────────

    public function test_it_revokes_the_other_sessions_without_touching_the_password(): void
    {
        $user = $this->holder();
        $user->createToken('otro-dispositivo');

        $this->actingAs($user)
            ->postJson(self::ROOT.'/me/sessions/revoke-others', ['current_password' => self::PASSWORD])
            ->assertNoContent();

        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
        $this->assertTrue(
            Hash::check(self::PASSWORD, (string) $user->refresh()->password),
            'cerrar sesiones ha cambiado la contraseña, y no debe'
        );
    }

    public function test_revoking_sessions_also_needs_the_right_password(): void
    {
        $user = $this->holder();
        $user->createToken('otro-dispositivo');

        $this->actingAs($user)
            ->postJson(self::ROOT.'/me/sessions/revoke-others', ['current_password' => 'no-es-esta'])
            ->assertStatus(422);

        $this->assertSame(
            1, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count(),
            'se han revocado credenciales sin confirmar la identidad'
        );
    }

    // ── Puerta ────────────────────────────────────────────────────────────────────────────────

    public function test_both_endpoints_reject_an_anonymous_request(): void
    {
        $this->putJson(self::ROOT.'/me/password', ['current_password' => 'x', 'password' => self::NUEVA])
            ->assertStatus(401);

        $this->postJson(self::ROOT.'/me/sessions/revoke-others', ['current_password' => 'x'])
            ->assertStatus(401);
    }

    /** El cuerpo es obligatorio: sin contraseña no hay reconfirmación que valga. */
    public function test_the_current_password_is_required(): void
    {
        $user = $this->holder();

        $this->actingAs($user)->putJson(self::ROOT.'/me/password', ['password' => self::NUEVA])->assertStatus(422);
        $this->actingAs($user)->postJson(self::ROOT.'/me/sessions/revoke-others', [])->assertStatus(422);
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
    public function test_it_unlinks_with_the_current_password(): void
    {
        $user = $this->holder();
        $this->linkGoogle($user);

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me/identities/google', ['current_password' => self::PASSWORD])
            ->assertNoContent()
            ->assertValidResponse(204);

        $this->assertSame(0, UserIdentity::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'identities.unlinked', 'target_id' => $user->id]);
    }

    /**
     * ⚠️⚠️ **Y con la contraseña equivocada el vínculo SOBREVIVE.** Es la mitad que de verdad importa:
     * un endpoint que borrara primero y comprobara después dejaría a cualquiera con una sesión robada
     * quitar la forma de entrar del titular.
     */
    public function test_a_wrong_password_leaves_the_link_alone(): void
    {
        $user = $this->holder();
        $this->linkGoogle($user);

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me/identities/google', ['current_password' => 'la-que-no-es'])
            ->assertStatus(422)
            ->assertValidResponse(422);

        $this->assertSame(1, UserIdentity::query()->count());
    }

    /** Idempotente: el titular pide un ESTADO —«que no haya vínculo»—, no una transición. */
    public function test_unlinking_what_is_not_there_is_fine(): void
    {
        $user = $this->holder();

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me/identities/google', ['current_password' => self::PASSWORD])
            ->assertNoContent();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'identities.unlinked']);
    }

    public function test_the_identity_endpoints_reject_an_anonymous_request(): void
    {
        $this->getJson(self::ROOT.'/me/identities')->assertStatus(401);
        $this->deleteJson(self::ROOT.'/me/identities/google', ['current_password' => 'x'])->assertStatus(401);
    }

    /** Un proveedor que no existe no es una ruta: sin esto, `DELETE /me/identities/lo-que-sea` pasaría. */
    public function test_an_unknown_provider_is_not_a_route(): void
    {
        $this->actingAs($this->holder())
            ->deleteJson(self::ROOT.'/me/identities/inventado', ['current_password' => self::PASSWORD])
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
