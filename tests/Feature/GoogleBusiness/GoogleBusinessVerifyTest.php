<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\GoogleBusinessApi;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use App\Domain\Platform\Services\GoogleBusinessLocation;
use App\Domain\Platform\Services\GoogleBusinessOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\TestCase;

/**
 * **T1·5 · `business-profile:verify`** (`docs/specs/google-business-profile.md` §4.2·10).
 *
 * El comando que se corre después de conectar una instalación y el primero cuando alguien dice «no
 * salen las reseñas». Su salida acaba en el log de un despliegue y en capturas de pantalla, así que
 * la mitad de estos casos vigilan **lo que NO imprime**.
 */
class GoogleBusinessVerifyTest extends TestCase
{
    use RefreshDatabase;

    /** Los tres secretos que no pueden salir por ninguna parte. */
    private const CANARIO_REFRESCO = '1//CANARIO-REFRESCO-QUE-NO-SALE';

    private const CANARIO_ACCESO = 'ya29.CANARIO-ACCESO-QUE-NO-SALE';

    private const CANARIO_SECRETO = 'GOCSPX-CANARIO-SECRETO-QUE-NO-SALE';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_ID_KEY], ['value' => 'central.apps.googleusercontent.com', 'group' => 'google']);
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY], ['value' => self::CANARIO_SECRETO, 'group' => 'google']);
    }

    private function conectada(?string $cuenta = 'accounts/1', ?string $ficha = 'locations/9'): GoogleBusinessConnection
    {
        return GoogleBusinessConnection::create([
            'status' => GoogleBusinessStatus::Connected,
            'refresh_token' => self::CANARIO_REFRESCO,
            'token_fingerprint' => GoogleBusinessConnection::fingerprint(self::CANARIO_REFRESCO),
            'location_name' => $ficha,
            'account_name' => $cuenta,
            'location_title' => 'Parque de prueba',
        ]);
    }

    private function fakeGoogle(array $extra = []): void
    {
        Http::fake(array_merge([
            GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(['access_token' => self::CANARIO_ACCESO, 'expires_in' => 3600]),
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['accounts' => [['name' => 'accounts/1']]]),
            GoogleBusinessApi::LOCATIONS_BASE.'*' => Http::response(['locations' => [
                ['name' => 'locations/9', 'title' => 'Parque de prueba', 'metadata' => ['placeId' => 'ChIJx']],
            ]]),
            GoogleBusinessApi::REVIEWS_BASE.'*' => Http::response(['averageRating' => 4.6, 'totalReviewCount' => 37]),
        ], $extra));
    }

    // ─────────── El camino bueno ───────────

    public function test_con_todo_en_orden_dice_lo_que_contesta_google(): void
    {
        $this->conectada();
        $this->fakeGoogle();

        $this->artisan('business-profile:verify')
            ->expectsOutputToContain('connected')
            ->expectsOutputToContain('Parque de prueba')
            ->expectsOutputToContain('37')
            ->assertSuccessful();
    }

    public function test_las_resenas_se_piden_con_la_cuenta_delante(): void
    {
        // ⚠️⚠️ La trampa de la tanda, medida contra la doc oficial: `locations.list` devuelve
        // `locations/9` y `reviews.list` exige `accounts/1/locations/9`. Pedirlo con el `name` a
        // secas devuelve un 404 que se lee como «ficha perdida» y manda a reconectar para nada.
        $this->conectada();
        $this->fakeGoogle();

        $this->artisan('business-profile:verify')->assertSuccessful();

        Http::assertSent(fn ($request) => str_starts_with($request->url(), GoogleBusinessApi::REVIEWS_BASE)
            && str_contains($request->url(), 'accounts/1/locations/9/reviews'));
    }

    public function test_una_conexion_sin_cuenta_lo_dice_en_vez_de_dar_un_404(): void
    {
        // Las elegidas antes de la T1·5 no la guardaron.
        $this->conectada(cuenta: null);
        $this->fakeGoogle();

        $this->artisan('business-profile:verify')
            ->expectsOutputToContain('no guardó la CUENTA')
            ->assertFailed();

        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), GoogleBusinessApi::REVIEWS_BASE));
    }

    // ─────────── Lo que NO se imprime ───────────

    public function test_no_imprime_ningun_secreto_en_el_camino_bueno(): void
    {
        $this->conectada();
        $this->fakeGoogle();

        $salida = $this->artisanOutput('business-profile:verify');

        $this->assertNotSame('', $salida, 'el comando no imprimió nada: este caso no mide nada');
        $this->assertStringNotContainsString(self::CANARIO_REFRESCO, $salida);
        $this->assertStringNotContainsString(self::CANARIO_ACCESO, $salida);
        $this->assertStringNotContainsString(self::CANARIO_SECRETO, $salida);
    }

    public function test_no_imprime_ningun_secreto_cuando_google_falla(): void
    {
        // El camino que más escribe: un fallo permanente, con un cuerpo de Google que trae texto.
        $this->conectada();
        $this->fakeGoogle([
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response([
                'error' => ['status' => 'PERMISSION_DENIED', 'message' => 'The caller does not have permission'],
            ], 403),
        ]);

        $salida = $this->artisanOutput('business-profile:verify');

        $this->assertStringContainsString('PERMISSION_DENIED', $salida, 'sin el motivo el diagnóstico no sirve');
        $this->assertStringNotContainsString(self::CANARIO_REFRESCO, $salida);
        $this->assertStringNotContainsString(self::CANARIO_ACCESO, $salida);
        $this->assertStringNotContainsString(self::CANARIO_SECRETO, $salida);
        // Y tampoco el CUERPO de la respuesta, que puede traer de vuelta lo que se mandó.
        $this->assertStringNotContainsString('The caller does not have permission', $salida);
    }

    /** Sin red, el diagnóstico lo dice y sale en rojo — no con una traza (`#733`). */
    public function test_sin_red_lo_dice_y_sale_distinto_de_cero(): void
    {
        $this->conectada();
        $this->fakeGoogle([GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::failedConnection()]);

        $salida = new BufferedOutput;
        $codigo = Artisan::call('business-profile:verify', [], $salida);
        $texto = $salida->fetch();

        $this->assertNotSame(0, $codigo);
        $this->assertStringContainsString('no ha llegado a Google', $texto);
        $this->assertStringNotContainsString(self::CANARIO_REFRESCO, $texto);
    }

    public function test_no_imprime_ningun_secreto_ni_con_el_detalle_al_maximo(): void
    {
        // ⚠️ El §4.2·10 dice «en ningún nivel de detalle», así que se mide con `-vvv`: es donde
        // Laravel suelta trazas, y una traza lleva los ARGUMENTOS de cada llamada.
        $this->conectada();
        $this->fakeGoogle();

        $salida = $this->artisanOutput('business-profile:verify', OutputInterface::VERBOSITY_DEBUG);

        $this->assertNotSame('', $salida);
        $this->assertStringNotContainsString(self::CANARIO_REFRESCO, $salida);
        $this->assertStringNotContainsString(self::CANARIO_ACCESO, $salida);
        $this->assertStringNotContainsString(self::CANARIO_SECRETO, $salida);
    }

    // ─────────── Los estados que no llaman ───────────

    public function test_sin_conexion_no_llama_a_google_y_dice_que_hacer(): void
    {
        $this->artisan('business-profile:verify')
            ->expectsOutputToContain('ready_to_connect')
            ->expectsOutputToContain('Ajustes')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_sin_credenciales_dice_que_no_se_arregla_desde_el_parque(): void
    {
        Setting::query()->whereIn('key', [GoogleBusinessCredentials::CLIENT_ID_KEY, GoogleBusinessCredentials::CLIENT_SECRET_KEY])->delete();
        Setting::flushMemo();
        $this->conectada();

        $this->artisan('business-profile:verify')
            ->expectsOutputToContain('unconfigured')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_un_token_ilegible_sale_como_caducada_y_nombra_la_app_key(): void
    {
        // ⚠️ No hay una rama propia para esto, y el arnés destapó por qué: el resolvedor de estado
        // ya convierte un token ilegible en «caducada» antes de que el comando mire nada. Lo que sí
        // hace falta es que el consejo NOMBRE la `APP_KEY`, porque a las once de la noche nadie
        // piensa en ella.
        $fila = $this->conectada();
        DB::table('google_business_connections')->where('id', $fila->id)->update(['refresh_token' => 'ceniza']);

        $this->artisan('business-profile:verify')
            ->expectsOutputToContain('expired')
            ->expectsOutputToContain('APP_KEY')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_si_la_ficha_ya_no_esta_en_el_listado_lo_dice(): void
    {
        // «¿Hay fichas?» no es la pregunta: la pregunta es si sigue estando la NUESTRA.
        $this->conectada(ficha: 'locations/la-que-ya-no-esta');
        $this->fakeGoogle();

        $this->artisan('business-profile:verify')
            ->expectsOutputToContain('ya no la administra')
            ->assertFailed();
    }

    public function test_el_comando_no_escribe_nada(): void
    {
        // Un diagnóstico que cambia lo que mide deja de servir para medirlo.
        $fila = $this->conectada();
        $this->fakeGoogle([
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403),
        ]);

        $this->artisan('business-profile:verify')->assertFailed();

        $despues = GoogleBusinessConnection::current();
        $this->assertSame(GoogleBusinessStatus::Connected, $despues->status, 'el diagnóstico ha apagado la conexión');
        $this->assertSame($fila->updated_at->timestamp, $despues->updated_at->timestamp);
    }

    public function test_el_parent_de_resenas_no_es_el_nombre_de_la_ficha(): void
    {
        $this->assertSame('accounts/1/locations/9', GoogleBusinessLocation::reviewsParentFor('accounts/1', 'locations/9'));
        $this->assertNull(GoogleBusinessLocation::reviewsParentFor(null, 'locations/9'));
        $this->assertNull(GoogleBusinessLocation::reviewsParentFor('accounts/1', null));
        $this->assertNull(GoogleBusinessLocation::reviewsParentFor('', ''));
    }

    /**
     * Corre el comando y devuelve su salida ENTERA, con la verbosidad que se pida.
     *
     * ⚠️ Con un `BufferedOutput` propio y no con `$this->artisan()->run()` + `Artisan::output()`:
     * aquello devolvía **cadena vacía** y los tres casos del canario pasaban sin mirar nada. Lo cazó
     * el `assertNotSame('', …)` que cada uno lleva delante — el patrón de la casa: *si tu caso
     * captura, fuerza o simula algo, aserta primero que lo consiguió*.
     */
    private function artisanOutput(string $comando, int $verbosidad = OutputInterface::VERBOSITY_NORMAL): string
    {
        $salida = new BufferedOutput($verbosidad);

        Artisan::call($comando, [], $salida);

        return $salida->fetch();
    }
}
