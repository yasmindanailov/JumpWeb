<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Exceptions\GoogleBusinessException;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\GoogleBusinessApi;
use App\Domain\Platform\Services\GoogleBusinessConnector;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use App\Domain\Platform\Services\GoogleBusinessLocation;
use App\Domain\Platform\Services\GoogleBusinessOAuth;
use App\Filament\Pages\GoogleBusinessProfilePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **T1·3b · Elegir y revalidar la ficha** (`docs/specs/google-business-profile.md` §4.2·4, §5·SEC-07).
 *
 * ⚠️ `Http::preventStrayRequests()`: ningún caso habla con Google.
 */
class GoogleBusinessLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_ID_KEY], ['value' => 'central.apps.googleusercontent.com', 'group' => 'google']);
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY], ['value' => 'GOCSPX-secreto', 'group' => 'google']);
    }

    /**
     * ⚠️ **El host del sitio NO se cambia por `config(['app.url' => …])` en un caso HTTP**: Filament
     * valida el host de la petición contra él y cualquier valor distinto de `localhost` hace saltar
     * «Untrusted Host» antes de llegar al controlador. Los casos por HTTP usan el host real del
     * entorno de pruebas; el emparejado en sí se mide aparte, pasándole el host a `matchesHost()`.
     */
    private function hostDelSitio(): string
    {
        return GoogleBusinessLocation::siteHost();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin']));

        return $user->fresh();
    }

    private function conectada(?string $placeId = null): GoogleBusinessConnection
    {
        return GoogleBusinessConnection::create([
            'status' => GoogleBusinessStatus::Connected,
            'refresh_token' => '1//el-refresco',
            'token_fingerprint' => GoogleBusinessConnection::fingerprint('1//el-refresco'),
            'place_id' => $placeId,
            'location_name' => $placeId === null ? null : 'locations/vieja',
        ]);
    }

    /** @param  list<array<string,mixed>>  $locations */
    private function fakeGoogle(array $locations): void
    {
        Http::fake([
            GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.x', 'expires_in' => 3600]),
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['accounts' => [['name' => 'accounts/1']]]),
            GoogleBusinessApi::LOCATIONS_BASE.'*' => Http::response(['locations' => $locations]),
        ]);
    }

    /** @return array<string,mixed> */
    private function ficha(string $name = 'locations/9', ?string $web = null): array
    {
        $web ??= 'https://'.$this->hostDelSitio();

        return [
            'name' => $name,
            'title' => 'Parque de prueba',
            'websiteUri' => $web,
            'storefrontAddress' => ['addressLines' => ['Calle Falsa 1'], 'postalCode' => '28001', 'locality' => 'Madrid'],
            'metadata' => [
                'placeId' => 'ChIJ'.$name,
                'mapsUri' => 'https://maps.google.com/maps?cid=1',
                'newReviewUri' => 'https://search.google.com/local/writereview?placeid=1',
            ],
        ];
    }

    // ─────────── El saneado, donde nace el dato (SEC-07) ───────────

    public function test_la_ficha_se_construye_con_lo_que_hace_falta(): void
    {
        $ficha = GoogleBusinessLocation::fromApi($this->ficha());

        $this->assertSame('locations/9', $ficha->name);
        $this->assertSame('Parque de prueba', $ficha->title);
        $this->assertSame('ChIJlocations/9', $ficha->placeId);
        $this->assertSame('https://maps.google.com/maps?cid=1', $ficha->mapsUri);
        $this->assertStringContainsString('Calle Falsa 1', (string) $ficha->address);
        $this->assertStringContainsString('Madrid', (string) $ficha->address);
    }

    public function test_sin_nombre_de_recurso_no_hay_ficha(): void
    {
        // Sin `name` no se la puede volver a pedir a Google: guardarla sería guardar un fantasma.
        $this->assertNull(GoogleBusinessLocation::fromApi(['title' => 'Sin nombre']));
        $this->assertNull(GoogleBusinessLocation::fromApi(['name' => '   ']));
    }

    #[DataProvider('urlsQueNoSePublican')]
    public function test_una_url_que_no_es_de_google_no_se_publica(string $url): void
    {
        // Llegan de una respuesta de red y van a un enlace público de la portada del parque: lo
        // único que las hace de fiar es que el host esté en la lista. Lo demás cae a `null`.
        $ficha = GoogleBusinessLocation::fromApi([
            'name' => 'locations/9',
            'metadata' => ['mapsUri' => $url, 'newReviewUri' => $url],
        ]);

        $this->assertNull($ficha->mapsUri, "«{$url}» no debería publicarse");
        $this->assertNull($ficha->newReviewUri);
    }

    public static function urlsQueNoSePublican(): array
    {
        return [
            'otro dominio' => ['https://evil.test/maps'],
            // El truco más viejo: termina en algo que un `str_ends_with` mal escrito daría por bueno.
            'sufijo tramposo' => ['https://maps.google.com.evil.test/x'],
            'subdominio no listado' => ['https://cualquiera.google.com/x'],
            'sin cifrar' => ['http://maps.google.com/maps?cid=1'],
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<script>'],
            // Un acortador es de Google, pero «es de Google» no implica «lleva a Google».
            'acortador' => ['https://maps.app.goo.gl/abc'],
            'vacía' => [''],
        ];
    }

    public function test_las_urls_de_google_si_se_publican(): void
    {
        $ficha = GoogleBusinessLocation::fromApi([
            'name' => 'locations/9',
            'metadata' => [
                'mapsUri' => 'https://maps.google.com/maps?cid=1',
                'newReviewUri' => 'https://g.page/r/abc/review',
            ],
        ]);

        $this->assertSame('https://maps.google.com/maps?cid=1', $ficha->mapsUri);
        $this->assertSame('https://g.page/r/abc/review', $ficha->newReviewUri);
    }

    #[DataProvider('hosts')]
    public function test_el_host_de_la_ficha_se_compara_con_el_del_sitio(string $web, string $sitio, bool $casa): void
    {
        $ficha = GoogleBusinessLocation::fromApi(['name' => 'locations/9', 'websiteUri' => $web]);

        $this->assertSame($casa, $ficha->matchesHost($sitio));
    }

    public static function hosts(): array
    {
        return [
            'el mismo' => ['https://parque.test', 'parque.test', true],
            // `www.` no cuenta: es el mismo negocio y rechazarlo sería un falso positivo garantizado.
            'con www en la ficha' => ['https://www.parque.test/reservas', 'parque.test', true],
            'con www en el sitio' => ['https://parque.test', 'www.parque.test', true],
            'mayúsculas' => ['https://PARQUE.test', 'parque.test', true],
            'otro parque' => ['https://otro-parque.test', 'parque.test', false],
            'subdominio' => ['https://blog.parque.test', 'parque.test', false],
            'sin web en la ficha' => ['', 'parque.test', false],
        ];
    }

    // ─────────── Elegirla ───────────

    public function test_elegir_una_ficha_la_guarda_entera(): void
    {
        $this->conectada();
        $this->fakeGoogle([$this->ficha()]);

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9'])
            ->assertSessionHas('status', 'google-business-location-chosen');

        $fila = GoogleBusinessConnection::current();
        $this->assertSame('locations/9', $fila->location_name);
        $this->assertSame('Parque de prueba', $fila->location_title);
        $this->assertSame('ChIJlocations/9', $fila->place_id);
        $this->assertSame('https://maps.google.com/maps?cid=1', $fila->maps_uri);
        $this->assertTrue(AuditLog::where('action', 'google_business.location_chosen')->exists());
    }

    public function test_una_ficha_que_no_esta_en_el_listado_se_rechaza(): void
    {
        // El identificador viaja por el navegador: sin revalidar, bastaría cambiarlo a mano para
        // apuntar la portada del parque a una ficha ajena.
        $this->conectada();
        $this->fakeGoogle([$this->ficha()]);

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/de-otro'])
            ->assertSessionHas('status', 'google-business-location-not-yours');

        $this->assertNull(GoogleBusinessConnection::current()->location_name);
    }

    /**
     * ⚠️ **Por el SERVICIO y no por HTTP, y el motivo se mide**: forzar `env = production` enciende la
     * verificación de CSRF que el entorno de pruebas apaga, así que el POST vuelve con **419** y el
     * caso mediría el token, no la guarda. La guarda vive en el servicio; HTTP no añade nada aquí.
     */
    public function test_un_nombre_que_es_prefijo_de_otro_no_cuela(): void
    {
        // ⚠️ El ataque que destapó el arnés: si la comparación fuera «empieza por», mandar
        // `locations/9` traería la ficha `locations/99`, que es OTRO negocio. Un nombre de recurso
        // es un identificador: se compara entero.
        $this->conectada();
        $this->fakeGoogle([$this->ficha(name: 'locations/99')]);

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9'])
            ->assertSessionHas('status', 'google-business-location-not-yours');

        $this->assertNull(GoogleBusinessConnection::current()->location_name);
    }

    public function test_en_produccion_una_ficha_con_otra_web_se_rechaza(): void
    {
        $conexion = $this->conectada();
        $this->fakeGoogle([$this->ficha(web: 'https://otro-parque.test')]);
        $this->app['env'] = 'production';

        $this->assertTrue(app()->isProduction(), 'el entorno no se forzó: este caso no mide nada');

        try {
            app(GoogleBusinessConnector::class)->chooseLocation($conexion->readToken(), 'locations/9', $this->admin()->id);
            $this->fail('debería haber rechazado la ficha de otro negocio');
        } catch (GoogleBusinessException $e) {
            $this->assertSame(GoogleBusinessException::LOCATION_HOST_MISMATCH, $e->reason);
        }

        $this->assertNull(GoogleBusinessConnection::current()->location_name);
    }

    public function test_fuera_de_produccion_la_web_distinta_solo_avisa(): void
    {
        // En desarrollo el sitio es `localhost` y la ficha apunta al dominio real: dura en todas
        // partes obligaría a saltársela para poder trabajar, y una guarda que se salta a diario
        // acaba desactivada donde sí importa.
        $this->conectada();
        $this->fakeGoogle([$this->ficha(web: 'https://otro-parque.test')]);

        $this->assertFalse(app()->isProduction());

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9'])
            ->assertSessionHas('status', 'google-business-location-chosen');

        $this->assertSame('locations/9', GoogleBusinessConnection::current()->location_name);
    }

    // ─────────── Cambiar de ficha ───────────

    public function test_cambiar_de_ficha_se_pregunta_antes(): void
    {
        // No es un ajuste: cambia de qué negocio son las reseñas que ve un visitante del parque.
        $this->conectada(placeId: 'ChIJla-de-antes');
        $this->fakeGoogle([$this->ficha()]);

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9'])
            ->assertSessionHas('status', 'google-business-location-changed');

        // Y nada ha cambiado mientras no confirme.
        $this->assertSame('ChIJla-de-antes', GoogleBusinessConnection::current()->place_id);
    }

    public function test_confirmado_el_cambio_se_guarda(): void
    {
        $this->conectada(placeId: 'ChIJla-de-antes');
        $this->fakeGoogle([$this->ficha()]);

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9', 'confirmed' => '1'])
            ->assertSessionHas('status', 'google-business-location-chosen');

        $this->assertSame('ChIJlocations/9', GoogleBusinessConnection::current()->place_id);
        $this->assertTrue(
            AuditLog::where('action', 'google_business.location_chosen')->get()
                ->contains(fn (AuditLog $log): bool => ($log->payload['changed'] ?? false) === true),
            'el rastro no dice que fue un CAMBIO, que es lo que nadie recuerda después'
        );
    }

    public function test_volver_a_elegir_la_misma_ficha_no_pregunta_nada(): void
    {
        // Mismo `placeId` = mismo negocio: preguntar aquí sería ruido, y el admin aprendería a
        // confirmar sin leer, que es como se cuela el cambio de verdad.
        $this->conectada(placeId: 'ChIJlocations/9');
        $this->fakeGoogle([$this->ficha()]);

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9'])
            ->assertSessionHas('status', 'google-business-location-chosen');
    }

    // ─────────── Permisos y bordes ───────────

    public function test_un_staff_no_puede_elegir_la_ficha(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'staff'], ['label' => 'Staff']));

        $this->actingAs($user->fresh())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9'])
            ->assertForbidden();
    }

    public function test_sin_conexion_no_se_elige_nada(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9'])
            ->assertSessionHas('status', 'google-business-failed');

        Http::assertNothingSent();
    }

    public function test_si_google_falla_al_elegir_no_se_guarda_nada(): void
    {
        $this->conectada();
        Http::fake([
            GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.x', 'expires_in' => 3600]),
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9'])
            ->assertSessionHas('status', 'google-business-api-failed');

        $this->assertNull(GoogleBusinessConnection::current()->location_name);
        // Y un 429 no ha apagado la conexión.
        $this->assertSame(GoogleBusinessStatus::Connected, GoogleBusinessConnection::current()->status);
    }

    // ─────────── La pantalla ───────────

    public function test_la_pantalla_lista_las_fichas_cuando_hay_permiso(): void
    {
        $this->conectada();
        $this->fakeGoogle([$this->ficha(), $this->ficha(name: 'locations/77')]);

        $this->actingAs($this->admin())
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertOk()
            ->assertSee(__('admin.google_business.choose_title'))
            ->assertSee('Parque de prueba')
            ->assertSee('locations/77', false);
    }

    public function test_si_google_no_contesta_la_pantalla_lo_dice_y_no_revienta(): void
    {
        $this->conectada();
        Http::fake([
            GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.x', 'expires_in' => 3600]),
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
        ]);

        $this->actingAs($this->admin())
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertOk()
            ->assertSee(__('admin.google_business.choose_failed'));

        // ⚠️ Y pintar NO ha apagado la conexión: un GET no escribe el estado.
        $this->assertSame(GoogleBusinessStatus::Connected, GoogleBusinessConnection::current()->status);
    }

    public function test_sin_permiso_la_pantalla_no_llama_a_google(): void
    {
        // Sin token no hay nada que listar, y una llamada aquí sería un 401 garantizado por render.
        GoogleBusinessConnection::create(['status' => GoogleBusinessStatus::ReadyToConnect]);

        $this->actingAs($this->admin())
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertOk()
            ->assertDontSee(__('admin.google_business.choose_title'));

        Http::assertNothingSent();
    }
}
