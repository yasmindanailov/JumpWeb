<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PasswordLogin;
use App\Http\Sidebar\SidebarEntry;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 3b — `POST /api/v1/auth/login` y `auth/logout`.
 *
 * La superficie que consumirá la SPA del sidebar. Lo que se comprueba aquí no es que la contraseña
 * se verifique —de eso ya hay tests, y de la regla en sí los de `PasswordLoginTest`— sino que la
 * API **hereda** la protección de `SEC-06` en vez de reimplementarla: los mismos dos limitadores,
 * el mismo mensaje genérico y la misma imposibilidad de averiguar qué correos existen.
 */
class AuthSessionTest extends ApiTestCase
{
    private function customer(string $email = 'cliente@jumpweb.test'): User
    {
        return User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    }

    /**
     * Petición como la haría la SPA: con `Origin` de un dominio declarado *stateful*.
     *
     * No es decoración del test. `EnsureFrontendRequestsAreStateful` mira ese encabezado para
     * decidir si monta sesión y CSRF; sin él, la petición llega SIN sesión y el endpoint no puede
     * abrir ninguna. Que el helper exista aquí documenta cómo debe llamar el cliente.
     *
     * @param  array<string, mixed>  $payload
     */
    private function fromSpa(string $path, array $payload = []): TestResponse
    {
        return $this->withHeader('Origin', (string) config('app.url'))->postJson(self::ROOT.$path, $payload);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('login-ip|127.0.0.1');
        parent::tearDown();
    }

    public function test_a_valid_login_opens_the_session_and_returns_the_profile(): void
    {
        $user = $this->customer();

        $this->fromSpa('/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('id', $user->id);

        $this->assertAuthenticatedAs($user->fresh());
    }

    /**
     * ⚠️⚠️ **Que entre OTRA persona descarta el desenlace de pago de la anterior.**
     *
     * `Http\Sidebar\SidebarEntry` guarda EN SESIÓN en qué quedó el último pago —confirmado, denegado
     * o verificando— para que el cajón lo enseñe al volver de la pasarela. `Session::regenerate()`
     * **conserva los datos**, así que sin este descarte, en un dispositivo compartido Bob se
     * encontraría el cajón abierto con el «pago denegado» de Alice y su código de pedido.
     *
     * ▶ **Este caso nace de la auditoría de A8** (`specs/auth-en-cajon.md` §8), y lo que destapó es
     * que la defensa vivía SOLO en `Livewire\Auth\Login` —el modal que se retira— y que su único
     * guardián era un test que se va con él. Medido por mutación: desactivarla tumbaba **un** test de
     * toda la suite. El login de la API, que es el que usa el cajón **desde 4.4a·2**, nunca la tuvo;
     * el propio controlador lo decía en un comentario («el sidebar Livewire hace lo mismo, más lo
     * suyo con la cesta») sin que nadie lo leyera como el hueco que era.
     *
     * ⚠️ El marcador es **quién estaba autenticado antes**, no la clave de sesión `purchase.user_id`
     * que usaba el modal: esa clave ya no la lee nadie —su consumidor era el `Purchase.php` que
     * `#112` retiró— y resucitarla habría sido inventar un segundo estado para decir lo mismo.
     */
    public function test_a_login_by_someone_else_discards_the_previous_payment_outcome(): void
    {
        $alice = $this->customer('alice@jumpweb.test');
        $bob = $this->customer('bob@jumpweb.test');

        $this->actingAs($alice);
        SidebarEntry::failed('R-DE-ALICE');

        $this->fromSpa('/auth/login', ['email' => $bob->email, 'password' => 'password'])->assertOk();

        $this->assertAuthenticatedAs($bob->fresh());
        $this->assertFalse(
            SidebarEntry::peek()->pending(),
            'Bob se encuentra el desenlace del pago de Alice: el cajón se le abriría con el pedido de otra persona'
        );
    }

    /**
     * **Y a la MISMA persona no se le descarta nada**, que es la otra mitad de la regla.
     *
     * ⚠️ Sin este control, «descartar siempre» pasaría el caso de arriba con matrícula — y le
     * borraría la confirmación de su compra a quien vuelve a identificarse en mitad del flujo, que es
     * justo lo que `SidebarEntry` existe para no perder.
     */
    public function test_and_the_same_person_keeps_it(): void
    {
        $alice = $this->customer('alice@jumpweb.test');

        $this->actingAs($alice);
        SidebarEntry::confirmed('R-DE-ALICE');

        $this->fromSpa('/auth/login', ['email' => $alice->email, 'password' => 'password'])->assertOk();

        $this->assertTrue(SidebarEntry::peek()->pending(), 'se ha perdido el desenlace de su propia compra');
    }

    /** El correo se normaliza en servidor: quien lo escribe con mayúsculas entra igual. */
    public function test_the_email_is_normalised(): void
    {
        // El alta guarda el correo ya normalizado; lo que aquí se prueba es que una ENTRADA
        // sucia —mayúsculas y espacios, como la escribe un móvil con autocapitalización— entra.
        $user = $this->customer('cliente@jumpweb.test');

        $this->fromSpa('/auth/login', ['email' => '  CLIENTE@jumpweb.TEST ', 'password' => 'password'])
            ->assertOk();

        $this->assertAuthenticatedAs($user->fresh());
    }

    /** Y la sesión se sella al entrar, como en la web. */
    public function test_a_valid_login_stamps_the_last_login(): void
    {
        $user = $this->customer();
        $this->assertNull($user->last_login_at);

        $this->fromSpa('/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    /**
     * `SEC-06`: una contraseña incorrecta y un correo que no existe dan **exactamente** la misma
     * respuesta. Si difirieran —en código, en mensaje o en status—, la API sería un oráculo de qué
     * correos están registrados, que es justo lo que la web evita desde el origen.
     */
    public function test_wrong_password_and_unknown_email_are_indistinguishable(): void
    {
        $this->customer();

        $wrongPassword = $this->fromSpa('/auth/login', [
            'email' => 'cliente@jumpweb.test', 'password' => 'no-es-esta',
        ])->assertStatus(401)->assertValidResponse(401);

        $unknownEmail = $this->fromSpa('/auth/login', [
            'email' => 'no-existe@jumpweb.test', 'password' => 'no-es-esta',
        ])->assertStatus(401);

        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
        $this->assertSame('invalid_credentials', $wrongPassword->json('error.code'));
        $this->assertGuest();
    }

    public function test_the_credentials_are_validated(): void
    {
        $this->fromSpa('/auth/login', ['email' => 'no-es-un-email'])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonStructure(['error' => ['fields' => ['email', 'password']]]);
    }

    /** Un campo que el servidor ignoraría no se acepta en silencio: el contrato lo prohíbe. */
    public function test_an_undeclared_field_breaks_the_contract(): void
    {
        $this->fromSpa('/auth/login', [
            'email' => 'cliente@jumpweb.test', 'password' => 'password', 'admin' => true,
        ])->assertInvalidRequest();
    }

    /**
     * Primer limitador de `SEC-06`, por (correo, IP). Al agotarlo la respuesta es **429 con
     * `retry_after`**, no otro 401: a un cliente legítimo que se ha equivocado hay que decirle
     * cuándo puede volver, y eso no revela nada de la cuenta.
     */
    public function test_repeated_failures_on_one_account_are_rate_limited(): void
    {
        $this->customer();

        foreach (range(1, PasswordLogin::MAX_ATTEMPTS) as $ignored) {
            $this->fromSpa('/auth/login', [
                'email' => 'cliente@jumpweb.test', 'password' => 'no-es-esta',
            ])->assertStatus(401);
        }

        $blocked = $this->fromSpa('/auth/login', [
            'email' => 'cliente@jumpweb.test', 'password' => 'password',
        ])->assertStatus(429)->assertValidResponse(429);

        $this->assertSame('too_many_requests', $blocked->json('error.code'));
        $this->assertGreaterThan(0, $blocked->json('error.params.retry_after'));
        $this->assertGuest();  // la contraseña correcta tampoco entra mientras dure el bloqueo
    }

    /**
     * Segundo limitador, por IP SOLA (auditoría Fase 1, A5) — el que de verdad frena el
     * password-spraying: un intento por cuenta nunca acumula 5 en la clave compuesta, así que sin
     * este un atacante barrería cuentas indefinidamente desde el mismo origen.
     */
    public function test_spraying_many_accounts_from_one_ip_is_rate_limited(): void
    {
        foreach (range(1, PasswordLogin::MAX_ATTEMPTS_PER_IP) as $i) {
            $this->fromSpa('/auth/login', [
                'email' => "victima{$i}@jumpweb.test", 'password' => 'probando',
            ])->assertStatus(401);
        }

        $this->fromSpa('/auth/login', [
            'email' => 'otra-mas@jumpweb.test', 'password' => 'probando',
        ])->assertStatus(429);
    }

    // ── Cierre de sesión ──────────────────────────────────────────────────────────────────────

    /**
     * Cerrar sesión responde 204 y deja de haber usuario identificado.
     *
     * ⚠️ Lo que este test NO puede probar, y por qué: la suite corre con `SESSION_DRIVER=array`
     * (`SUITE-06`), así que la sesión no viaja entre dos peticiones del mismo test — una segunda
     * llamada a `/me` daría 200 por el guard que quedó cacheado en memoria, no porque la cookie
     * siguiera valiendo. Sería un falso positivo. El ciclo entero (entrar → usar → salir → ya no
     * entrar) se verificó con `curl` y su tarro de cookies contra el servidor real, y está
     * registrado en `DECISIONES #30`.
     */
    public function test_logout_closes_the_session(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->withHeader('Origin', (string) config('app.url'))->postJson(self::ROOT.'/auth/logout')
            ->assertNoContent()
            ->assertValidResponse(204);

        $this->assertGuest();
        $this->assertFalse(auth('sanctum')->check(), 'el guard de la petición sigue viendo al usuario');
    }

    public function test_logout_requires_an_identity(): void
    {
        $this->fromSpa('/auth/logout')
            ->assertStatus(401)
            ->assertValidResponse(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    /**
     * La vía Bearer, ya cableada aunque la emisión llegue en Fase 6 (`DECISIONES #29`): cerrar
     * sesión revoca **el token de la petición**, no la sesión —que no existe— ni los demás tokens.
     * Sin esto, el día que haya emisor un `logout` desde la app dejaría el token vivo.
     */
    public function test_logout_revokes_only_the_token_that_made_the_request(): void
    {
        $user = $this->customer();
        $used = $user->createToken('el-de-esta-peticion')->accessToken;
        $other = $user->createToken('otro-dispositivo')->accessToken;

        Sanctum::actingAs($user, ['*']);
        $user->withAccessToken($used);

        $this->fromSpa('/auth/logout')->assertNoContent();

        $this->assertSame([$other->id], $user->fresh()->tokens()->pluck('id')->all());
    }
}
