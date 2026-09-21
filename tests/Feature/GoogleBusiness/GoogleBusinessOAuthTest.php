<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\GoogleBusinessConnectionState;
use App\Domain\Platform\Services\GoogleBusinessConnector;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use App\Domain\Platform\Services\GoogleBusinessOAuth;
use App\Filament\Pages\GoogleBusinessProfilePage;
use App\Http\Auth\GoogleBusinessOAuthSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * **T1·2 · La ida y la vuelta de OAuth** (`docs/specs/google-business-profile.md` §4.2·2;
 * `DECISIONES #524`).
 *
 * ⚠️ **Ningún caso habla con Google**: `Http::preventStrayRequests()` en `setUp()`, así que una
 * petición que se escape rompe el caso en vez de salir a internet (§5, fila SUITE).
 */
class GoogleBusinessOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function credentials(): void
    {
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_ID_KEY], ['value' => 'central.apps.googleusercontent.com', 'group' => 'google']);
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY], ['value' => 'GOCSPX-secreto', 'group' => 'google']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin']));

        return $user->fresh();
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'staff'], ['label' => 'Staff']));

        return $user->fresh();
    }

    /** La respuesta buena de Google al canje. */
    private function fakeExchange(array $overrides = []): void
    {
        Http::fake([
            GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(array_merge([
                'access_token' => 'ya29.de-acceso',
                'refresh_token' => '1//refresco-de-verdad',
                'scope' => GoogleBusinessOAuth::SCOPE,
                'expires_in' => 3599,
            ], $overrides)),
        ]);
    }

    // ─────────── La ida ───────────

    public function test_la_ida_manda_a_google_con_pkce_y_consentimiento(): void
    {
        $this->credentials();

        $respuesta = $this->actingAs($this->admin())->post(route('admin.google_business.connect'));

        $destino = $respuesta->headers->get('Location');
        parse_str((string) parse_url($destino, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith(GoogleBusinessOAuth::AUTHORIZE_ENDPOINT, $destino);
        // Sin `offline` no hay token de refresco, y sin `consent` Google no lo reemite a una cuenta
        // que ya había autorizado: la conexión moriría en una hora sin que nada fallara hoy.
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent select_account', $query['prompt']);
        $this->assertSame(GoogleBusinessOAuth::SCOPE, $query['scope']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['code_challenge']);
        $this->assertNotEmpty($query['state']);
    }

    public function test_el_code_verifier_no_viaja_a_google(): void
    {
        // La mitad que hace útil a PKCE: a Google va el HASH, y el secreto se queda en la sesión.
        $this->credentials();

        $respuesta = $this->actingAs($this->admin())->post(route('admin.google_business.connect'));
        $destino = (string) $respuesta->headers->get('Location');

        $reto = session('google_business.oauth.challenge');
        $this->assertIsArray($reto, 'el reto no se ha guardado: este caso no mide nada');

        $this->assertStringNotContainsString($reto['verifier'], $destino);
        parse_str((string) parse_url($destino, PHP_URL_QUERY), $query);
        $this->assertSame(
            rtrim(strtr(base64_encode(hash('sha256', $reto['verifier'], true)), '+/', '-_'), '='),
            $query['code_challenge']
        );
    }

    public function test_sin_credenciales_la_ida_no_sale_y_lo_dice(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.google_business.connect'))
            ->assertRedirect(route('filament.admin.pages.ficha-google'))
            ->assertSessionHas('status', 'google-business-not-configured');
    }

    public function test_un_staff_no_puede_conectar_la_ficha(): void
    {
        // `panel_role` le deja pasar; `settings.manage` no. La ruta sola no basta.
        $this->credentials();

        $this->actingAs($this->staff())
            ->post(route('admin.google_business.connect'))
            ->assertForbidden();
    }

    public function test_sin_sesion_no_hay_ida_ni_vuelta(): void
    {
        $this->post(route('admin.google_business.connect'))->assertRedirect(route('login'));
        $this->get(route('admin.google_business.callback', ['state' => 'x', 'code' => 'y']))->assertRedirect(route('login'));
    }

    // ─────────── La pantalla ───────────

    public function test_la_pantalla_pinta_el_estado_efectivo_y_el_boton(): void
    {
        $this->credentials();

        $this->actingAs($this->admin())
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertOk()
            ->assertSee(__('admin.google_business.states.ready_to_connect.label'))
            // El texto dice QUÉ HACER, que es lo que necesita quien lo lee.
            ->assertSee(__('admin.google_business.states.ready_to_connect.what_to_do'))
            ->assertSee(route('admin.google_business.connect'), false);
    }

    public function test_sin_credenciales_la_pantalla_no_ofrece_el_boton(): void
    {
        // Pulsar «Conectar» sin credenciales no haría nada: el botón sobra y el texto tiene que
        // decir que esto no se arregla desde el parque.
        $this->actingAs($this->admin())
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertOk()
            ->assertSee(__('admin.google_business.states.unconfigured.label'))
            ->assertDontSee(route('admin.google_business.connect'), false);
    }

    public function test_un_staff_no_ve_la_pantalla(): void
    {
        $this->actingAs($this->staff())
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertForbidden();
    }

    public function test_la_pantalla_enseña_el_desenlace_del_viaje_a_google(): void
    {
        $this->credentials();

        $this->actingAs($this->admin())
            ->withSession(['status' => 'google-business-missing-refresh-token'])
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertOk()
            ->assertSee(__('admin.google_business.results.missing-refresh-token'));
    }

    // ─────────── La vuelta ───────────

    public function test_la_vuelta_guarda_el_token_y_deja_la_conexion_conectada(): void
    {
        $this->credentials();
        $this->fakeExchange();
        $admin = $this->admin();

        $reto = $this->startChallengeFor($admin);

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'el-codigo']))
            ->assertRedirect(route('filament.admin.pages.ficha-google'))
            ->assertSessionHas('status', 'google-business-connected');

        $fila = GoogleBusinessConnection::current();
        $this->assertNotNull($fila);
        $this->assertSame('1//refresco-de-verdad', $fila->readToken());
        $this->assertSame($admin->id, $fila->connected_by_user_id);
        $this->assertSame(GoogleBusinessStatus::Connected, GoogleBusinessConnectionState::current());
        $this->assertTrue(AuditLog::where('action', 'google_business.connected')->exists());
    }

    public function test_el_canje_presenta_el_code_verifier(): void
    {
        $this->credentials();
        $this->fakeExchange();
        $admin = $this->admin();
        $reto = $this->startChallengeFor($admin);

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'el-codigo']));

        Http::assertSent(function ($request) use ($reto) {
            return $request->url() === GoogleBusinessOAuth::TOKEN_ENDPOINT
                && $request['code_verifier'] === $reto['verifier']
                && $request['grant_type'] === 'authorization_code';
        });
    }

    public function test_un_state_que_no_es_el_nuestro_no_hace_nada(): void
    {
        $this->credentials();
        $admin = $this->admin();
        $reto = $this->startChallengeFor($admin);

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => 'fabricado', 'code' => 'el-codigo']))
            ->assertSessionHas('status', 'google-business-failed');

        $this->assertNull(GoogleBusinessConnection::current());
    }

    public function test_el_reto_solo_vale_una_vez(): void
    {
        $this->credentials();
        $this->fakeExchange();
        $admin = $this->admin();
        $reto = $this->startChallengeFor($admin);

        $sesion = ['google_business.oauth.challenge' => $reto];

        // El primero entra…
        $this->actingAs($admin)->withSession($sesion)
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'c1']))
            ->assertSessionHas('status', 'google-business-connected');

        // …y el segundo, con el MISMO state pero ya sin reto en sesión, no.
        $this->actingAs($admin)->withSession([])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'c2']))
            ->assertSessionHas('status', 'google-business-failed');
    }

    public function test_un_reto_caducado_no_vale(): void
    {
        $this->credentials();
        $this->fakeExchange();
        $admin = $this->admin();
        $reto = $this->startChallengeFor($admin);

        $this->travel(GoogleBusinessOAuthSession::CHALLENGE_TTL_SECONDS + 1)->seconds();

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'el-codigo']))
            ->assertSessionHas('status', 'google-business-failed');

        $this->assertNull(GoogleBusinessConnection::current());
    }

    public function test_si_vuelve_otro_usuario_no_se_conecta_nada(): void
    {
        // Entre la ida y la vuelta caben un logout y un login con otra cuenta. Sin esta guarda, el
        // permiso sobre la ficha del parque quedaría anotado a nombre de quien volvió.
        $this->credentials();
        $this->fakeExchange();
        $quienFue = $this->admin();
        $quienVuelve = $this->admin();
        $reto = $this->startChallengeFor($quienFue);

        $this->actingAs($quienVuelve)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'el-codigo']))
            ->assertSessionHas('status', 'google-business-failed');

        $this->assertNull(GoogleBusinessConnection::current());
    }

    public function test_cancelar_en_google_no_es_un_fallo(): void
    {
        $this->credentials();
        $admin = $this->admin();
        $reto = $this->startChallengeFor($admin);

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'error' => 'access_denied']))
            ->assertSessionHas('status', 'google-business-cancelled');

        $this->assertNull(GoogleBusinessConnection::current());
    }

    public function test_sin_token_de_refresco_no_se_guarda_nada(): void
    {
        // El fallo más silencioso de la feature: Google contesta 200 con un token de ACCESO y ya.
        $this->credentials();
        $this->fakeExchange(['refresh_token' => null]);
        $admin = $this->admin();
        $reto = $this->startChallengeFor($admin);

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'el-codigo']))
            ->assertSessionHas('status', 'google-business-missing-refresh-token');

        $this->assertNull(GoogleBusinessConnection::current(), 'se guardó media conexión');
    }

    public function test_sin_el_ambito_concedido_no_se_guarda_nada(): void
    {
        // El consentimiento de Google es granular: se puede desmarcar y seguir.
        $this->credentials();
        $this->fakeExchange(['scope' => 'https://www.googleapis.com/auth/userinfo.email']);
        $admin = $this->admin();
        $reto = $this->startChallengeFor($admin);

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'el-codigo']))
            ->assertSessionHas('status', 'google-business-scope-not-granted');

        $this->assertNull(GoogleBusinessConnection::current());
    }

    public function test_un_ambito_que_solo_empieza_igual_no_cuenta(): void
    {
        // `str_contains` daría por bueno esto, y lo que hay en juego es el permiso de escritura.
        $this->assertFalse(GoogleBusinessOAuth::grants(GoogleBusinessOAuth::SCOPE.'.readonly'));
        $this->assertFalse(GoogleBusinessOAuth::grants(''));
        $this->assertFalse(GoogleBusinessOAuth::grants(null));
        // Y con más ámbitos de los pedidos, sí cuenta: Google puede devolver de más.
        $this->assertTrue(GoogleBusinessOAuth::grants('openid '.GoogleBusinessOAuth::SCOPE.' email'));
    }

    public function test_google_rechazando_el_canje_no_deja_nada_a_medias(): void
    {
        $this->credentials();
        Http::fake([GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(['error' => 'invalid_grant'], 400)]);
        $admin = $this->admin();
        $reto = $this->startChallengeFor($admin);

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'caducado']))
            ->assertSessionHas('status', 'google-business-token-exchange-failed');

        $this->assertNull(GoogleBusinessConnection::current());
    }

    /**
     * ⚠️ **Sin red, el canje no es un «Google ha rechazado»** (`#733`): hasta ahora el corte salía
     * como `ConnectionException` y la vuelta daba un 500. Se dice lo que ha pasado, porque se
     * arregla de otra forma —esperar y repetir— y no se guarda nada.
     */
    public function test_sin_red_en_el_canje_se_dice_y_no_se_guarda_nada(): void
    {
        $this->credentials();
        Http::fake([GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::failedConnection()]);
        $admin = $this->admin();
        $reto = $this->startChallengeFor($admin);

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'bueno']))
            ->assertSessionHas('status', 'google-business-unreachable');

        $this->assertNull(GoogleBusinessConnection::current());
        $this->assertNotSame(
            'admin.google_business.results.unreachable',
            __('admin.google_business.results.unreachable'),
            'el desenlace existe pero la pantalla pintaría la clave en crudo',
        );
    }

    // ─────────── Reconectar ───────────

    public function test_reconectar_sustituye_el_token_y_deja_una_sola_fila(): void
    {
        $this->credentials();
        $admin = $this->admin();

        GoogleBusinessConnection::create([
            'status' => GoogleBusinessStatus::Expired,
            'refresh_token' => '1//el-viejo',
            'token_fingerprint' => GoogleBusinessConnection::fingerprint('1//el-viejo'),
        ]);

        $this->fakeExchange(['refresh_token' => '1//el-nuevo']);
        $reto = $this->startChallengeFor($admin);

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'el-codigo']))
            ->assertSessionHas('status', 'google-business-connected');

        $fila = GoogleBusinessConnection::current();
        $this->assertSame('1//el-nuevo', $fila->readToken());
        $this->assertSame(GoogleBusinessStatus::Connected, $fila->status);
        // Sigue habiendo UNA fila: reconectar sustituye, no añade.
        $this->assertSame(1, GoogleBusinessConnection::query()->count());
    }

    public function test_reconectar_con_el_mismo_token_no_lo_revoca(): void
    {
        // ⚠️ El modo de fallo que menos ruido hace: Google puede devolver el mismo token de refresco.
        // Revocarlo como «el anterior» mataría el que se acaba de guardar, y el panel diría «conectada».
        $this->credentials();
        $admin = $this->admin();

        GoogleBusinessConnection::create([
            'status' => GoogleBusinessStatus::Connected,
            'refresh_token' => '1//el-mismo',
            'token_fingerprint' => GoogleBusinessConnection::fingerprint('1//el-mismo'),
        ]);

        $this->fakeExchange(['refresh_token' => '1//el-mismo']);
        // ⚠️⚠️ **En PRODUCCIÓN y con el endpoint FINGIDO, a propósito.** Sin las dos cosas este caso
        // no mide nada: fuera de producción no se revoca nunca —la otra guarda taparía a ésta— y sin
        // fingir el endpoint la llamada sería imposible, así que «no se llamó» sería cierto por el
        // motivo equivocado. Lo destapó el arnés: la mutación sobrevivía.
        Http::fake([GoogleBusinessOAuth::REVOKE_ENDPOINT => Http::response('', 200)]);
        $this->app['env'] = 'production';

        $reto = $this->startChallengeFor($admin);

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'el-codigo']))
            ->assertSessionHas('status', 'google-business-connected');

        Http::assertNotSent(fn ($request) => $request->url() === GoogleBusinessOAuth::REVOKE_ENDPOINT);
        $this->assertSame('1//el-mismo', GoogleBusinessConnection::current()->readToken());
    }

    public function test_fuera_de_produccion_no_se_revoca_en_google(): void
    {
        // Los entornos que no son producción comparten las fichas reales por el proyecto de
        // desarrollo, y revocar retira la autorización de TODO el proyecto.
        $this->credentials();
        $admin = $this->admin();

        GoogleBusinessConnection::create([
            'status' => GoogleBusinessStatus::Connected,
            'refresh_token' => '1//el-viejo',
            'token_fingerprint' => GoogleBusinessConnection::fingerprint('1//el-viejo'),
        ]);

        $this->fakeExchange(['refresh_token' => '1//el-nuevo']);
        // ⚠️ El endpoint va FINGIDO aunque la tesis sea que no se llama: si no, «no se llamó» sería
        // cierto porque la llamada era imposible, y `revoke()` se traga la excepción del cortafuegos
        // de peticiones. Con el endpoint disponible, lo único que separa a Google de una revocación
        // es la guarda de producción. Lo destapó el arnés.
        Http::fake([GoogleBusinessOAuth::REVOKE_ENDPOINT => Http::response('', 200)]);
        $reto = $this->startChallengeFor($admin);

        $this->assertFalse(app()->isProduction(), 'el entorno del test no es el que cree: no mide nada');

        $this->actingAs($admin)
            ->withSession(['google_business.oauth.challenge' => $reto])
            ->get(route('admin.google_business.callback', ['state' => $reto['state'], 'code' => 'el-codigo']));

        Http::assertNotSent(fn ($request) => $request->url() === GoogleBusinessOAuth::REVOKE_ENDPOINT);
    }

    public function test_en_produccion_el_token_viejo_se_revoca_y_va_en_el_cuerpo(): void
    {
        // La otra mitad de la reconexión, que solo existe en producción. Se mide aquí y no por el
        // flujo entero porque el flujo, en test, nunca la ejecuta — y un caso que afirma algo que su
        // entorno impide es un caso que miente.
        Http::fake([GoogleBusinessOAuth::REVOKE_ENDPOINT => Http::response('', 200)]);
        $this->app['env'] = 'production';

        $this->assertTrue(app()->isProduction(), 'el entorno no se forzó: este caso no mide nada');

        $revocado = app(GoogleBusinessConnector::class)->revoke('1//el-viejo');

        $this->assertTrue($revocado);
        Http::assertSent(function ($request) {
            return $request->url() === GoogleBusinessOAuth::REVOKE_ENDPOINT
                // En el CUERPO: una URL acaba en el log del servidor y en el del proxy.
                && $request['token'] === '1//el-viejo'
                && ! str_contains($request->url(), 'token=');
        });
    }

    /**
     * Abre un reto como lo abriría la ida, sin pasar por HTTP: los casos de la vuelta necesitan el
     * `verifier` en la mano para comprobar qué se canjea.
     *
     * @return array{state: string, verifier: string, holder: int, at: int}
     */
    private function startChallengeFor(User $user): array
    {
        return [
            'state' => bin2hex(random_bytes(32)),
            'verifier' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
            'holder' => $user->id,
            'at' => now()->getTimestamp(),
        ];
    }
}
