<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use App\Domain\Identity\Services\GoogleAuth;
use App\Domain\Identity\Services\GoogleOAuth;
use App\Domain\Platform\Models\Setting;
use App\Http\Sidebar\SidebarEntry;
use App\Notifications\SocialIdentityLinked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * **Entrar y registrarse con Google — el mecanismo** (`docs/specs/auth-con-google.md`, tanda T1).
 *
 * ## Qué se ejercita y por qué así
 *
 * Los casos **conducen el flujo de verdad**: piden la ida, leen el `state` y el `nonce` del propio
 * redirect a Google y vuelven con ellos. No se siembra la sesión a mano en ningún caso — si se
 * hiciera, la custodia entre las dos peticiones (§6.3·3) quedaría sin probar y un renombrado de la
 * clave pasaría en verde.
 *
 * Lo único simulado es **Google**: `Http::fake` sobre el endpoint de canje. Todo lo demás —el reto,
 * la comprobación del token, la resolución de identidad, la sesión— es el código real.
 *
 * ## La mitad que más importa: los tokens FABRICADOS
 *
 * ⚠️⚠️ Un `id_token` es un JWT y **cualquiera puede escribir uno**. Lo que hace que la afirmación
 * valga es de dónde viene y qué se le comprueba, así que aquí hay un caso por cada comprobación
 * —emisor, destinatario, caducidad, `nonce`— y **un control** que demuestra que el mismo token, sin
 * esa manipulación, sí entra. Sin el control, un caso que rechaza no distingue «la guarda funciona»
 * de «esto no entra nunca».
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '1023005524676-pruebas.apps.googleusercontent.com';

    private const CLIENT_SECRET = 'GOCSPX-secreto-de-pruebas';

    /** El `sub` de OpenID Connect: 21 dígitos, como los de Google. */
    private const SUB = '110000000000000000001';

    private const EMAIL = 'ana.google@example.com';

    // ── El hueco por instalación ──────────────────────────────────────────────────────────────

    public function test_without_keys_both_routes_are_404(): void
    {
        $this->get('/auth/google')->assertNotFound();
        $this->get('/auth/google/callback?state=x&code=y')->assertNotFound();
    }

    /** Media configuración es NO configurado: la lección de `PublicConfigResource`. */
    public function test_only_the_client_id_is_not_enough(): void
    {
        Setting::updateOrCreate(['key' => GoogleAuth::CLIENT_ID_KEY], ['value' => self::CLIENT_ID, 'group' => 'auth']);
        Setting::flushMemo();
        GoogleAuth::flushCache();

        $this->assertFalse(GoogleAuth::enabled());
        $this->get('/auth/google')->assertNotFound();
    }

    // ── La ida ────────────────────────────────────────────────────────────────────────────────

    public function test_the_redirect_asks_google_for_the_three_minimum_scopes_with_a_state_and_a_nonce(): void
    {
        $this->configureKeys();

        $response = $this->get(route('auth.google.redirect'));

        $response->assertRedirect();
        $url = (string) $response->headers->get('Location');

        $this->assertStringStartsWith(GoogleOAuth::AUTHORIZE_ENDPOINT.'?', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame(route('auth.google.callback'), $query['redirect_uri']);
        $this->assertSame('code', $query['response_type'], 'El flujo es Authorization Code: el navegador no maneja tokens.');
        $this->assertSame('openid email profile', $query['scope'], 'Ni un ámbito más: cualquier sensible dispara la verificación de Google.');
        $this->assertSame('select_account', $query['prompt'], 'En un dispositivo compartido, entrar sin preguntar es un defecto.');
        $this->assertSame('online', $query['access_type'], 'Sin `refresh_token`: no llamamos a ninguna API de Google.');

        // Los dos secretos de un solo uso: aleatorios y largos.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $query['state']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $query['nonce']);
        $this->assertNotSame($query['state'], $query['nonce']);
    }

    /** El `state` es de esta sesión: dos idas distintas no comparten reto. */
    public function test_every_start_mints_a_new_state(): void
    {
        $this->configureKeys();

        $this->assertNotSame($this->startFlow()['state'], $this->startFlow()['state']);
    }

    // ── El canje, que es la raíz de confianza ─────────────────────────────────────────────────

    public function test_the_code_is_exchanged_server_to_server_with_the_client_secret(): void
    {
        $this->configureKeys();
        $flow = $this->startFlow();
        $this->fakeGoogle($flow);

        $this->returnFromGoogle($flow);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $request->url() === GoogleOAuth::TOKEN_ENDPOINT
                && $request->method() === 'POST'
                && $body['grant_type'] === 'authorization_code'
                && $body['client_id'] === self::CLIENT_ID
                && $body['client_secret'] === self::CLIENT_SECRET
                && $body['redirect_uri'] === route('auth.google.callback')
                && $body['code'] === 'codigo-de-un-solo-uso';
        });
    }

    /**
     * **El control de los cuatro casos de abajo.** Con el token intacto, la persona entra: eso es lo
     * que convierte a los otros en una guarda y no en «esto no funciona».
     */
    public function test_control_an_untouched_token_signs_the_person_in(): void
    {
        $this->configureKeys();
        $user = $this->linkedUser();

        $this->signInWithGoogle();

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_token_minted_for_another_application_is_refused(): void
    {
        $this->configureKeys();
        $this->linkedUser();

        $this->signInWithGoogle(['aud' => 'otra-aplicacion.apps.googleusercontent.com'])
            ->assertSessionHas('status', 'google-failed');

        $this->assertGuest();
    }

    public function test_a_token_from_another_issuer_is_refused(): void
    {
        $this->configureKeys();
        $this->linkedUser();

        $this->signInWithGoogle(['iss' => 'https://accounts.example.invalid'])
            ->assertSessionHas('status', 'google-failed');

        $this->assertGuest();
    }

    public function test_an_expired_token_is_refused(): void
    {
        $this->configureKeys();
        $this->linkedUser();

        $this->signInWithGoogle(['exp' => time() - 3600, 'iat' => time() - 7200])
            ->assertSessionHas('status', 'google-failed');

        $this->assertGuest();
    }

    /** El `nonce` ata el token a ESTA petición: uno capturado de otra no vale (replay). */
    public function test_a_token_with_another_nonce_is_refused(): void
    {
        $this->configureKeys();
        $this->linkedUser();

        $this->signInWithGoogle(['nonce' => bin2hex(random_bytes(32))])
            ->assertSessionHas('status', 'google-failed');

        $this->assertGuest();
    }

    /** Sin `email` no hay identidad que resolver, aunque el token esté perfectamente firmado. */
    public function test_a_token_without_email_is_refused(): void
    {
        $this->configureKeys();
        $this->linkedUser();

        $this->signInWithGoogle(['email' => null])->assertSessionHas('status', 'google-failed');

        $this->assertGuest();
    }

    /**
     * ⚠️⚠️ **Nada que llegue del navegador se acepta como token.** El caso manda un `id_token`
     * fabricado en la URL de vuelta —el que un atacante escribiría— junto a un canje legítimo de otra
     * persona: se entra como quien dice el CANJE, nunca como quien dice la URL.
     */
    public function test_an_id_token_sent_by_the_browser_is_ignored(): void
    {
        $this->configureKeys();
        $legitimate = $this->linkedUser();

        $victim = User::factory()->create(['email' => 'victima@example.com', 'email_verified_at' => now()]);

        $flow = $this->startFlow();
        $this->fakeGoogle($flow);

        $forged = $this->idToken([
            'sub' => '999999999999999999999',
            'email' => $victim->email,
            'nonce' => $flow['nonce'],
        ]);

        $this->get(route('auth.google.callback', [
            'state' => $flow['state'],
            'code' => 'codigo-de-un-solo-uso',
            'id_token' => $forged,
        ]));

        $this->assertAuthenticatedAs($legitimate->fresh());
        $this->assertDatabaseMissing('user_identities', ['user_id' => $victim->id]);
    }

    // ── El reto ───────────────────────────────────────────────────────────────────────────────

    public function test_a_return_without_a_matching_state_does_nothing(): void
    {
        $this->configureKeys();
        $this->linkedUser();
        $flow = $this->startFlow();
        $this->fakeGoogle($flow);

        $this->get(route('auth.google.callback', ['state' => bin2hex(random_bytes(32)), 'code' => 'codigo']))
            ->assertSessionHas('status', 'google-failed');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    /** Un reto es de UN SOLO USO: se consume aunque el intento falle. */
    public function test_the_state_cannot_be_replayed(): void
    {
        $this->configureKeys();
        $user = $this->linkedUser();
        $flow = $this->startFlow();
        $this->fakeGoogle($flow);

        $this->returnFromGoogle($flow);
        $this->assertAuthenticatedAs($user->fresh());

        $this->post(route('logout'));
        $this->assertGuest();

        $this->returnFromGoogle($flow)->assertSessionHas('status', 'google-failed');
        $this->assertGuest();
    }

    /** Cancelar en Google no es un fallo: se dice y se deja a la persona donde estaba. */
    public function test_cancelling_at_google_says_so_and_creates_nothing(): void
    {
        $this->configureKeys();
        $flow = $this->startFlow();

        $this->get(route('auth.google.callback', ['state' => $flow['state'], 'error' => 'access_denied']))
            ->assertRedirect(route('account'))
            ->assertSessionHas('status', 'google-cancelled');

        $this->assertGuest();
        $this->assertDatabaseCount('user_identities', 0);
    }

    // ── Las tres puertas (§5) ─────────────────────────────────────────────────────────────────

    public function test_an_existing_link_enters_with_no_screens(): void
    {
        $this->configureKeys();
        Notification::fake();
        $user = $this->linkedUser();

        $this->signInWithGoogle()->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseCount('user_identities', 1);
        Notification::assertNothingSent();
    }

    /** El vínculo se busca por el `sub`, **nunca** por el correo: cambiar de correo no cambia de dueño. */
    public function test_the_link_survives_the_google_account_changing_its_email(): void
    {
        $this->configureKeys();
        $user = $this->linkedUser();

        $this->signInWithGoogle(['email' => 'nueva.direccion@example.com']);

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseCount('user_identities', 1);
    }

    public function test_an_account_with_the_same_email_is_linked_automatically_and_told_by_email(): void
    {
        $this->configureKeys();
        Notification::fake();

        $user = User::factory()->create([
            'email' => self::EMAIL,
            'email_verified_at' => now(),
            'password' => 'contrasena-de-siempre',
        ]);

        $this->signInWithGoogle()->assertSessionHas('status', 'google-linked');

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseHas('user_identities', [
            'user_id' => $user->id,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_id' => self::SUB,
            'email_at_link' => self::EMAIL,
            'linked_via' => UserIdentity::VIA_LOGIN,
        ]);

        // Una cuenta YA verificada no se toca por dentro: sigue entrando con su contraseña.
        $this->assertTrue(Hash::check('contrasena-de-siempre', (string) $user->fresh()->password));

        Notification::assertSentTo($user, SocialIdentityLinked::class);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'identities.linked',
            'target_type' => 'user',
            'target_id' => $user->id,
        ]);
    }

    /**
     * **P12 · la cuenta que un tercero creó con tu correo.** El alta pública crea cuentas sin
     * verificar y `/mi-cuenta` no exige verificación, así que el ocupante podía estar dentro. Las
     * tres cosas van juntas: se promueve, se expulsa y la contraseña deja de servir.
     */
    public function test_an_unverified_destination_account_is_taken_over(): void
    {
        $this->configureKeys();
        Notification::fake();

        $squatter = User::factory()->create([
            'email' => self::EMAIL,
            'email_verified_at' => null,
            'password' => 'la-que-puso-el-tercero',
        ]);
        $squatter->createToken('sesion-del-ocupante');

        $this->signInWithGoogle();

        $fresh = $squatter->fresh();

        $this->assertAuthenticatedAs($fresh);
        $this->assertNotNull($fresh->email_verified_at, 'Google acredita el buzón: la cuenta queda verificada.');
        $this->assertFalse(
            Hash::check('la-que-puso-el-tercero', (string) $fresh->password),
            'La contraseña del ocupante tiene que dejar de servir: si no, vuelve a entrar con ella.'
        );
        $this->assertSame(0, DB::table('personal_access_tokens')->count(), 'Sus credenciales caen con `revokeAllAccess()`.');

        Notification::assertSentTo($squatter, SocialIdentityLinked::class);

        $this->assertDatabaseHas('audit_logs', ['action' => 'identities.linked', 'target_id' => $squatter->id]);
    }

    public function test_a_visitor_without_account_creates_nothing_and_is_sent_to_complete_the_signup(): void
    {
        $this->configureKeys();

        $this->signInWithGoogle()
            ->assertRedirect(route('registro'))
            ->assertSessionHas('status', 'google-complete-signup');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_identities', 0);

        // La identidad verificada espera en la SESIÓN del servidor, no en el navegador (§6.3·3).
        $this->assertSame(self::EMAIL, session('auth.google.profile')['email'] ?? null);
    }

    /**
     * **P1 · la guarda dura.** Con `email_verified: false` no se vincula ni se crea nada, **aunque el
     * correo coincida con una cuenta existente**. Nunca se degrada a «el correo coincide».
     */
    public function test_google_saying_the_email_is_not_verified_links_nothing(): void
    {
        $this->configureKeys();
        $user = User::factory()->create(['email' => self::EMAIL, 'email_verified_at' => now()]);

        $this->signInWithGoogle(['email_verified' => false])
            ->assertSessionHas('status', 'google-email-unverified');

        $this->assertGuest();
        $this->assertDatabaseCount('user_identities', 0);
        $this->assertNotNull($user->fresh(), 'La cuenta existente no se toca.');
    }

    /** Y `true` es lo ÚNICO que cuenta como verificado: `"1"`, `1` o ausente son «no». */
    public function test_anything_but_a_true_email_verified_is_treated_as_unverified(): void
    {
        $this->configureKeys();
        User::factory()->create(['email' => self::EMAIL, 'email_verified_at' => now()]);

        $this->signInWithGoogle(['email_verified' => 1])->assertSessionHas('status', 'google-email-unverified');

        $this->assertGuest();
    }

    // ── Cuentas que no pueden entrar por aquí ─────────────────────────────────────────────────

    public function test_an_anonymised_account_is_never_entered_by_its_link(): void
    {
        $this->configureKeys();
        $user = $this->linkedUser();
        $user->anonymize();

        // La purga se lleva el vínculo (art. 17), así que se recrea a mano para ejercitar justo la
        // guarda del camino del `sub`: sin ella, un vínculo anterior a la supresión sería la ÚNICA
        // forma de entrar en una cuenta anonimizada.
        UserIdentity::create([
            'user_id' => $user->id,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_id' => self::SUB,
            'email_at_link' => self::EMAIL,
            'linked_via' => UserIdentity::VIA_LOGIN,
            'linked_at' => now(),
        ]);

        $this->signInWithGoogle()->assertSessionHas('status', 'google-anonymized');

        $this->assertGuest();
    }

    /** Una cuenta, una llave por proveedor: la segunda se rechaza **con su motivo**, no en silencio. */
    public function test_a_second_google_account_on_the_same_user_is_refused(): void
    {
        $this->configureKeys();
        Notification::fake();

        $user = User::factory()->create(['email' => self::EMAIL, 'email_verified_at' => now()]);
        UserIdentity::create([
            'user_id' => $user->id,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_id' => 'el-sub-de-la-primera',
            'email_at_link' => self::EMAIL,
            'linked_via' => UserIdentity::VIA_LOGIN,
            'linked_at' => now(),
        ]);

        $this->signInWithGoogle()->assertSessionHas('status', 'google-provider-conflict');

        $this->assertGuest();
        $this->assertDatabaseCount('user_identities', 1);
        Notification::assertNothingSent();
    }

    /**
     * **`[DECIDIDO owner]` Q5: el EQUIPO sí puede vincular y entrar con su Google.**
     *
     * ⚠️ Con la consecuencia que hay que aceptar a sabiendas: `AdminPanelProvider` no declara
     * `authGuard`, así que esta sesión **es** la del panel. Es defendible porque quien comprometa ese
     * Gmail ya podía pedir un reset de contraseña y entrar igual — y lo que lo hace vigilable es el
     * aviso por correo, que por eso se asevera aquí.
     */
    public function test_a_team_account_can_link_and_enter(): void
    {
        $this->configureKeys();
        Notification::fake();

        $admin = User::factory()->create(['email' => self::EMAIL, 'email_verified_at' => now()]);
        $admin->roles()->attach(Role::create(['name' => 'admin', 'label' => 'Administrador']));

        $this->signInWithGoogle();

        $this->assertAuthenticatedAs($admin->fresh());
        $this->assertTrue($admin->fresh()->hasRole('admin'));
        Notification::assertSentTo($admin, SocialIdentityLinked::class);
    }

    // ── Los efectos de sesión que el dominio NO hace (§6.5) ───────────────────────────────────

    public function test_entering_seals_the_last_login(): void
    {
        $this->configureKeys();
        $user = $this->linkedUser();
        $user->forceFill(['last_login_at' => null])->saveQuietly();

        $this->signInWithGoogle();

        $this->assertNotNull($user->fresh()->last_login_at, 'Sin sello, el panel y el export tendrían un agujero con forma de Google.');
    }

    public function test_the_session_id_is_regenerated_when_entering(): void
    {
        $this->configureKeys();
        $this->linkedUser();
        $flow = $this->startFlow();
        $this->fakeGoogle($flow);

        $before = session()->getId();
        $this->returnFromGoogle($flow);

        $this->assertNotSame($before, session()->getId(), 'Regenerar cierra la fijación de sesión, y el `state` vivía en esa misma sesión.');
    }

    /** En un dispositivo compartido, el «pago denegado» de otra persona no puede aparecerle al que entra. */
    public function test_another_person_entering_clears_the_pending_purchase_outcome(): void
    {
        $this->configureKeys();
        $alice = User::factory()->create(['email' => 'alice@example.com', 'email_verified_at' => now()]);
        $bob = $this->linkedUser();

        $this->actingAs($alice);
        $this->withSession(['purchase.failed_code' => 'R-ALICE1']);

        $this->signInWithGoogle();

        $this->assertAuthenticatedAs($bob->fresh());
        $this->assertFalse(SidebarEntry::peek()->pending(), 'El desenlace de Alice no puede sobrevivir a que entre Bob.');
    }

    // ── RGPD ──────────────────────────────────────────────────────────────────────────────────

    /**
     * **Art. 17.** Y por BORRADO: una fila redactada dejaría el `sub` ocupado en el `UNIQUE` y esa
     * persona no podría volver a registrarse con su Google **nunca más**.
     */
    public function test_anonymising_deletes_the_identity_and_frees_the_sub(): void
    {
        $this->configureKeys();
        $user = $this->linkedUser();

        $this->assertDatabaseCount('user_identities', 1);

        $user->anonymize();

        $this->assertDatabaseCount('user_identities', 0);

        // El `sub` queda libre de verdad: otra cuenta puede reclamarlo.
        $otra = User::factory()->create(['email' => self::EMAIL, 'email_verified_at' => now()]);
        UserIdentity::create([
            'user_id' => $otra->id,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_id' => self::SUB,
            'email_at_link' => self::EMAIL,
            'linked_via' => UserIdentity::VIA_LOGIN,
            'linked_at' => now(),
        ]);

        $this->assertDatabaseHas('user_identities', ['user_id' => $otra->id, 'provider_id' => self::SUB]);
    }

    // ── Utillaje ──────────────────────────────────────────────────────────────────────────────

    private function configureKeys(): void
    {
        Setting::updateOrCreate(['key' => GoogleAuth::CLIENT_ID_KEY], ['value' => self::CLIENT_ID, 'group' => 'auth']);
        Setting::updateOrCreate(['key' => GoogleAuth::CLIENT_SECRET_KEY], ['value' => self::CLIENT_SECRET, 'group' => 'auth']);
        Setting::flushMemo();
        GoogleAuth::flushCache();
    }

    /** Una cuenta con el vínculo ya hecho: la puerta 1 (§5.1). */
    private function linkedUser(): User
    {
        $user = User::factory()->create(['email' => self::EMAIL, 'email_verified_at' => now()]);

        UserIdentity::create([
            'user_id' => $user->id,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_id' => self::SUB,
            'email_at_link' => self::EMAIL,
            'linked_via' => UserIdentity::VIA_LOGIN,
            'linked_at' => now(),
        ]);

        return $user;
    }

    /**
     * La IDA de verdad, y devuelve el reto que el servidor acaba de acuñar. **No se siembra la
     * sesión a mano**: así la custodia entre peticiones queda ejercitada.
     *
     * @return array{state: string, nonce: string}
     */
    private function startFlow(): array
    {
        $response = $this->get(route('auth.google.redirect'));
        $response->assertRedirect();

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return ['state' => (string) $query['state'], 'nonce' => (string) $query['nonce']];
    }

    /**
     * Google, simulado en lo único que se simula: la respuesta del canje.
     *
     * @param  array{state: string, nonce: string}  $flow
     * @param  array<string, mixed>  $claims  lo que se manipula del token
     */
    private function fakeGoogle(array $flow, array $claims = []): void
    {
        Http::fake([
            GoogleOAuth::TOKEN_ENDPOINT => Http::response([
                'access_token' => 'ya29.token-que-no-usamos',
                'expires_in' => 3599,
                'token_type' => 'Bearer',
                'id_token' => $this->idToken($claims + ['nonce' => $flow['nonce']]),
            ]),
        ]);
    }

    /** @param  array{state: string, nonce: string}  $flow */
    private function returnFromGoogle(array $flow): TestResponse
    {
        return $this->get(route('auth.google.callback', [
            'state' => $flow['state'],
            'code' => 'codigo-de-un-solo-uso',
        ]));
    }

    /** El recorrido entero: ida real, Google simulado y vuelta. */
    private function signInWithGoogle(array $claims = []): TestResponse
    {
        $flow = $this->startFlow();
        $this->fakeGoogle($flow, $claims);

        return $this->returnFromGoogle($flow);
    }

    /**
     * Un `id_token` con la forma exacta de uno de Google. **La firma es basura a propósito**: por el
     * canal servidor-a-servidor no se comprueba, y que estos casos pasen con una firma inventada es
     * la prueba de que lo que sostiene la confianza es el CANAL, no el JWT.
     *
     * @param  array<string, mixed>  $claims
     */
    private function idToken(array $claims): string
    {
        $payload = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'sub' => self::SUB,
            'email' => self::EMAIL,
            'email_verified' => true,
            'name' => 'Ana Pérez',
            'iat' => time() - 10,
            'exp' => time() + 3600,
        ], $claims);

        return implode('.', [
            $this->base64Url((string) json_encode(['alg' => 'RS256', 'kid' => 'de-prueba'])),
            $this->base64Url((string) json_encode($payload)),
            $this->base64Url('firma-que-este-canal-no-necesita'),
        ]);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
