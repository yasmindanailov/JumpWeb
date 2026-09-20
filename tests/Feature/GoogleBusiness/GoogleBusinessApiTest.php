<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Exceptions\GoogleBusinessApiException;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\GoogleBusinessApi;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use App\Domain\Platform\Services\GoogleBusinessOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **T1·3a · El cliente de la API de la ficha** (`docs/specs/google-business-profile.md` §4.2·5–§4.2·7).
 *
 * ⚠️ `Http::preventStrayRequests()` en `setUp()`: ningún caso habla con Google.
 */
class GoogleBusinessApiTest extends TestCase
{
    use RefreshDatabase;

    /** El token que no puede aparecer en ningún sitio (§4.2·6). */
    private const CANARIO = '1//CANARIO-QUE-NO-PUEDE-SALIR-EN-NINGUN-LOG';

    /** @var list<string> */
    private array $registrado = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_ID_KEY], ['value' => 'central.apps.googleusercontent.com', 'group' => 'google']);
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY], ['value' => 'GOCSPX-secreto', 'group' => 'google']);

        // Se escucha TODO lo que se registra, mensaje y contexto, para el canario.
        Event::listen(MessageLogged::class, function (MessageLogged $evento): void {
            $this->registrado[] = $evento->message.' '.json_encode($evento->context);
        });
    }

    private function fakeToken(string $access = 'ya29.de-acceso'): array
    {
        return [GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(['access_token' => $access, 'expires_in' => 3600])];
    }

    // ─────────── Lo que pide y cómo lo pide ───────────

    public function test_las_cuentas_llegan_y_el_token_va_en_la_cabecera(): void
    {
        Http::fake($this->fakeToken() + [
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['accounts' => [['name' => 'accounts/1', 'accountName' => 'PlayJump']]]),
        ]);

        $cuentas = (new GoogleBusinessApi)->accounts(self::CANARIO);

        $this->assertSame('accounts/1', $cuentas[0]['name']);

        Http::assertSent(function ($request) {
            if (! str_starts_with($request->url(), GoogleBusinessApi::ACCOUNTS_ENDPOINT)) {
                return false;
            }

            // ⚠️ En la CABECERA, y no en la URL: una URL acaba en el log del servidor y del proxy.
            return $request->hasHeader('Authorization', 'Bearer ya29.de-acceso')
                && ! str_contains($request->url(), 'access_token');
        });
    }

    public function test_las_fichas_se_piden_con_el_read_mask(): void
    {
        // Sin `readMask` Google responde 400: no es una optimización, es obligatorio.
        Http::fake($this->fakeToken() + [
            GoogleBusinessApi::LOCATIONS_BASE.'*' => Http::response(['locations' => [['name' => 'locations/9', 'title' => 'PlayJump']]]),
        ]);

        $fichas = (new GoogleBusinessApi)->locations(self::CANARIO, 'accounts/1');

        $this->assertSame('locations/9', $fichas[0]['name']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'accounts/1/locations')
            && str_contains(urldecode($request->url()), 'readMask='.GoogleBusinessApi::LOCATION_READ_MASK));
    }

    public function test_se_recorren_todas_las_cuentas_del_token(): void
    {
        // Un administrador puede tener varias cuentas y la ficha del parque vivir en cualquiera:
        // mirar solo la primera dejaría la revalidación del §4.2·4 rechazando una ficha legítima.
        Http::fake($this->fakeToken() + [
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['accounts' => [['name' => 'accounts/1'], ['name' => 'accounts/2']]]),
            GoogleBusinessApi::LOCATIONS_BASE.'accounts/1/locations*' => Http::response(['locations' => [['name' => 'locations/9']]]),
            GoogleBusinessApi::LOCATIONS_BASE.'accounts/2/locations*' => Http::response(['locations' => [['name' => 'locations/77']]]),
        ]);

        $fichas = (new GoogleBusinessApi)->allLocations(self::CANARIO);

        $this->assertSame(['locations/9', 'locations/77'], array_column($fichas, 'name'));
    }

    public function test_una_respuesta_sin_la_clave_esperada_no_revienta(): void
    {
        // Google devuelve el objeto vacío cuando no hay nada: «sin cuentas» es un dato, no un fallo.
        Http::fake($this->fakeToken() + [GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response([])]);

        $this->assertSame([], (new GoogleBusinessApi)->accounts(self::CANARIO));
    }

    public function test_el_token_de_acceso_se_pide_una_sola_vez_y_se_reutiliza(): void
    {
        Http::fake($this->fakeToken() + [
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['accounts' => []]),
        ]);

        $api = new GoogleBusinessApi;
        $api->accounts(self::CANARIO);
        $api->accounts(self::CANARIO);

        // Dos llamadas a la API, UN solo canje: el token vive en memoria del objeto.
        Http::assertSentCount(3);
        $canjes = 0;
        Http::assertSent(function ($request) use (&$canjes) {
            if ($request->url() === GoogleBusinessOAuth::TOKEN_ENDPOINT) {
                $canjes++;
            }

            return true;
        });
        $this->assertSame(1, $canjes, 'se pidió el token de acceso más de una vez');
    }

    public function test_un_401_se_reintenta_una_sola_vez_con_token_nuevo(): void
    {
        Http::fake([
            GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::sequence()
                ->push(['access_token' => 'ya29.viejo', 'expires_in' => 3600])
                ->push(['access_token' => 'ya29.nuevo', 'expires_in' => 3600]),
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::sequence()
                ->push(['error' => ['status' => 'UNAUTHENTICATED']], 401)
                ->push(['accounts' => [['name' => 'accounts/1']]]),
        ]);

        $cuentas = (new GoogleBusinessApi)->accounts(self::CANARIO);

        $this->assertSame('accounts/1', $cuentas[0]['name']);
        // Y el segundo intento fue con el token NUEVO: si reusara el viejo, el reintento no arregla nada.
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer ya29.nuevo'));
    }

    public function test_un_401_que_persiste_es_una_conexion_caducada(): void
    {
        Http::fake($this->fakeToken() + [
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['error' => ['status' => 'UNAUTHENTICATED']], 401),
        ]);

        try {
            (new GoogleBusinessApi)->accounts(self::CANARIO);
            $this->fail('debería haber lanzado');
        } catch (GoogleBusinessApiException $e) {
            $this->assertSame(GoogleBusinessStatus::Expired, $e->status);
            $this->assertTrue($e->isPermanent());
        }
    }

    // ─────────── Qué clase de «no» es cada «no» ───────────

    #[DataProvider('negativas')]
    public function test_cada_negativa_de_google_se_traduce_a_su_estado(int $http, string $razon, ?GoogleBusinessStatus $esperado): void
    {
        $error = GoogleBusinessApiException::from($http, $razon);

        $this->assertSame($esperado, $error->status);
        $this->assertSame($esperado !== null, $error->isPermanent());
    }

    public static function negativas(): array
    {
        return [
            'permiso retirado' => [401, 'UNAUTHENTICATED', GoogleBusinessStatus::Expired],
            'refresco inválido' => [400, 'invalid_grant', GoogleBusinessStatus::Expired],
            'cliente inválido' => [400, 'invalid_client', GoogleBusinessStatus::Expired],
            // ⚠️ Los dos 403: el primero lo arregla el PARQUE, el segundo no lo arregla nadie del parque.
            'la cuenta perdió el rol' => [403, 'PERMISSION_DENIED', GoogleBusinessStatus::Forbidden],
            'la API no está habilitada' => [403, 'SERVICE_DISABLED', GoogleBusinessStatus::NoApiAccess],
            'cuota sin aprobar' => [403, 'accessNotConfigured', GoogleBusinessStatus::NoApiAccess],
            'la ficha ya no existe' => [404, 'NOT_FOUND', GoogleBusinessStatus::LocationLost],
            // ⚠️ Pasajeros: marcarlos como avería apagaría la conexión por un mal minuto de Google.
            'demasiadas peticiones' => [429, 'RESOURCE_EXHAUSTED', null],
            'Google caído' => [503, 'UNAVAILABLE', null],
            'error interno' => [500, '', null],
        ];
    }

    public function test_un_429_no_toca_el_estado_de_la_conexion(): void
    {
        Http::fake($this->fakeToken() + [
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429),
        ]);

        try {
            (new GoogleBusinessApi)->accounts(self::CANARIO);
            $this->fail('debería haber lanzado');
        } catch (GoogleBusinessApiException $e) {
            $this->assertNull($e->status, 'un 429 apagaría la conexión de un parque por un mal minuto');
            $this->assertFalse($e->isPermanent());
        }
    }

    // ─────────── El canario ───────────

    public function test_el_token_no_aparece_en_ningun_registro_pase_lo_que_pase(): void
    {
        // Se fuerza el camino que MÁS escribe: un fallo permanente, que registra.
        Http::fake($this->fakeToken() + [
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED', 'message' => 'The caller does not have permission']], 403),
        ]);

        try {
            (new GoogleBusinessApi)->accounts(self::CANARIO);
        } catch (GoogleBusinessApiException $e) {
            // El mensaje de la excepción viaja a `failed_jobs`: tampoco puede llevarlo.
            $this->assertStringNotContainsString(self::CANARIO, $e->getMessage());
            $this->assertStringNotContainsString('GOCSPX', $e->getMessage());
        }

        // El instrumento, comprobado ANTES de fiarse de él: algo se registró de verdad.
        $this->assertNotEmpty($this->registrado, 'no se registró nada: este caso no mide nada');

        $todo = implode("\n", $this->registrado);
        $this->assertStringNotContainsString(self::CANARIO, $todo, 'el token de refresco acabó en el log');
        $this->assertStringNotContainsString('ya29.de-acceso', $todo, 'el token de acceso acabó en el log');
        $this->assertStringNotContainsString('GOCSPX', $todo, 'el secreto del cliente acabó en el log');
        // …y lo que SÍ tiene que estar, o el registro no sirve para nada.
        $this->assertStringContainsString('PERMISSION_DENIED', $todo);
    }

    public function test_sin_credenciales_la_llamada_es_una_conexion_caducada(): void
    {
        Setting::query()->whereIn('key', [GoogleBusinessCredentials::CLIENT_ID_KEY, GoogleBusinessCredentials::CLIENT_SECRET_KEY])->delete();
        Setting::flushMemo();

        try {
            (new GoogleBusinessApi)->accounts(self::CANARIO);
            $this->fail('debería haber lanzado');
        } catch (GoogleBusinessApiException $e) {
            $this->assertSame(GoogleBusinessStatus::Expired, $e->status);
        }

        // Y no se llamó a Google: sin credenciales no hay nada que canjear.
        Http::assertNothingSent();
    }
}
