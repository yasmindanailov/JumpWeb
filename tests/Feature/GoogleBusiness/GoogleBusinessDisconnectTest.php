<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\GoogleBusinessApi;
use App\Domain\Platform\Services\GoogleBusinessConnectionState;
use App\Domain\Platform\Services\GoogleBusinessConnector;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use App\Domain\Platform\Services\GoogleBusinessLocation;
use App\Domain\Platform\Services\GoogleBusinessOAuth;
use App\Filament\Pages\GoogleBusinessProfilePage;
use App\Notifications\GoogleBusinessLocationChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * **T1·4 · Desconectar, quién conectó y el aviso del cambio de ficha**
 * (`docs/specs/google-business-profile.md` §4.2·1, §4.2·4 y §4.2·8).
 */
class GoogleBusinessDisconnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_ID_KEY], ['value' => 'central.apps.googleusercontent.com', 'group' => 'google']);
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY], ['value' => 'GOCSPX-secreto', 'group' => 'google']);
    }

    private function admin(string $name = 'Ana'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin']));

        return $user->fresh();
    }

    private function conectada(?string $placeId = null, ?int $porUsuario = null): GoogleBusinessConnection
    {
        return GoogleBusinessConnection::create([
            'status' => GoogleBusinessStatus::Connected,
            'refresh_token' => '1//el-refresco',
            'token_fingerprint' => GoogleBusinessConnection::fingerprint('1//el-refresco'),
            'place_id' => $placeId,
            'location_title' => $placeId === null ? null : 'La ficha de antes',
            'location_name' => $placeId === null ? null : 'locations/vieja',
            'connected_by_user_id' => $porUsuario,
            'connected_at' => now(),
        ]);
    }

    // ─────────── Desconectar ───────────

    public function test_desconectar_borra_la_fila_entera(): void
    {
        // Media conexión —sin llave pero con la ficha dentro— es un estado que nadie sabe leer.
        $this->conectada(placeId: 'ChIJalgo');

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.disconnect'))
            ->assertSessionHas('status', 'google-business-disconnected-not-revoked');

        $this->assertNull(GoogleBusinessConnection::current());
        // Y el estado efectivo vuelve a la verdad: hay credenciales, no hay permiso.
        $this->assertSame(GoogleBusinessStatus::ReadyToConnect, GoogleBusinessConnectionState::current());
    }

    public function test_desconectar_deja_rastro_antes_de_borrar(): void
    {
        // Después no habría fila a la que apuntar, y éste es justo el gesto del que alguien
        // preguntará dentro de un mes.
        $this->conectada();

        $this->actingAs($this->admin())->post(route('admin.google_business.disconnect'));

        $this->assertTrue(AuditLog::where('action', 'google_business.disconnected')->exists());
    }

    public function test_fuera_de_produccion_desconectar_no_revoca_y_lo_dice(): void
    {
        // Fuera de producción los entornos comparten las fichas reales por el proyecto de
        // desarrollo: revocar retiraría la autorización de TODO el proyecto.
        Http::fake([GoogleBusinessOAuth::REVOKE_ENDPOINT => Http::response('', 200)]);
        $this->conectada();

        $this->assertFalse(app()->isProduction());

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.disconnect'))
            // Al admin se le dice que retire el permiso a mano: es lo único accionable que queda.
            ->assertSessionHas('status', 'google-business-disconnected-not-revoked');

        Http::assertNotSent(fn ($request) => $request->url() === GoogleBusinessOAuth::REVOKE_ENDPOINT);
    }

    public function test_en_produccion_desconectar_si_revoca(): void
    {
        Http::fake([GoogleBusinessOAuth::REVOKE_ENDPOINT => Http::response('', 200)]);
        $this->conectada();
        $this->app['env'] = 'production';

        $this->assertTrue(app()->isProduction(), 'el entorno no se forzó: este caso no mide nada');

        $revocado = app(GoogleBusinessConnector::class)->revoke('1//el-refresco');

        $this->assertTrue($revocado);
        Http::assertSent(fn ($request) => $request->url() === GoogleBusinessOAuth::REVOKE_ENDPOINT
            && $request['token'] === '1//el-refresco');
    }

    public function test_si_google_no_confirma_la_retirada_se_borra_igual_y_se_dice(): void
    {
        // Lo de casa ya no está: un fallo de Google no puede dejar una llave viva en una
        // instalación que se cree desconectada.
        $this->conectada();

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.disconnect'))
            ->assertSessionHas('status', 'google-business-disconnected-not-revoked');

        $this->assertNull(GoogleBusinessConnection::current());
    }

    public function test_desconectar_sin_conexion_no_hace_nada(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.google_business.disconnect'))
            ->assertSessionHas('status', 'google-business-not-connected');

        Http::assertNothingSent();
    }

    public function test_un_staff_no_puede_desconectar(): void
    {
        $this->conectada();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'staff'], ['label' => 'Staff']));

        $this->actingAs($user->fresh())
            ->post(route('admin.google_business.disconnect'))
            ->assertForbidden();

        $this->assertNotNull(GoogleBusinessConnection::current(), 'la conexión sobrevive a quien no puede tocarla');
    }

    public function test_desconectar_solo_por_post(): void
    {
        // Retirar el permiso sobre la ficha del parque no puede depender de abrir un enlace.
        $this->conectada();

        $this->actingAs($this->admin())
            ->get('/admin/ficha-google/desconectar')
            ->assertStatus(405);

        $this->assertNotNull(GoogleBusinessConnection::current());
    }

    // ─────────── Quién conectó ───────────

    public function test_la_pantalla_dice_quien_conecto_y_cuando(): void
    {
        // ⚠️⚠️ **Mira BERTA una conexión de ANA, y no es un adorno**: Filament pinta el nombre del
        // usuario en su barra superior, así que si el que mira fuera el mismo que conectó,
        // `assertSee` pasaría por la cabecera aunque esta línea no existiera. Lo destapó el arnés:
        // con `conectadaPor()` devolviendo `null`, el caso seguía verde.
        $ana = $this->admin('Ana Pérez');
        $berta = $this->admin('Berta Ruiz');
        $conexion = $this->conectada(porUsuario: $ana->id);
        // Con permiso, la pantalla lista las fichas (§4.2·4): sin el doble, `preventStrayRequests`
        // rompe el render y este caso mediría la red, no el nombre.
        $this->fakeGoogle([$this->ficha()]);

        $this->actingAs($berta)
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertOk()
            ->assertSee(__('admin.google_business.connected_by', [
                'name' => 'Ana Pérez',
                'date' => $conexion->connected_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ]));
    }

    public function test_sin_conexion_la_pantalla_no_ofrece_desconectar(): void
    {
        $this->actingAs($this->admin())
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertOk()
            ->assertDontSee(route('admin.google_business.disconnect'), false);
    }

    // ─────────── El aviso del cambio de ficha ───────────

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
    private function ficha(string $name = 'locations/9'): array
    {
        return [
            'name' => $name,
            'title' => 'La ficha nueva',
            'websiteUri' => 'https://'.GoogleBusinessLocation::siteHost(),
            'metadata' => ['placeId' => 'ChIJnueva'],
        ];
    }

    public function test_cambiar_de_ficha_avisa_a_todos_los_admins(): void
    {
        Notification::fake();
        $quienLoHace = $this->admin('Ana Pérez');
        $otroAdmin = $this->admin('Berta');
        $this->conectada(placeId: 'ChIJvieja');
        $this->fakeGoogle([$this->ficha()]);

        $this->actingAs($quienLoHace)
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9', 'confirmed' => '1'])
            ->assertSessionHas('status', 'google-business-location-chosen');

        // A los dos: el aviso existe para quien NO estaba delante.
        Notification::assertSentTo([$quienLoHace, $otroAdmin], GoogleBusinessLocationChanged::class);
    }

    public function test_el_aviso_dice_quien_y_desde_que_ficha(): void
    {
        // Sin el «desde qué» el correo no deja saber si fue un error, que es para lo que se manda.
        Notification::fake();
        $ana = $this->admin('Ana Pérez');
        $this->conectada(placeId: 'ChIJvieja');
        $this->fakeGoogle([$this->ficha()]);

        $this->actingAs($ana)->post(route('admin.google_business.choose'), ['location' => 'locations/9', 'confirmed' => '1']);

        Notification::assertSentTo($ana, GoogleBusinessLocationChanged::class,
            function (GoogleBusinessLocationChanged $aviso): bool {
                return $aviso->locationTitle === 'La ficha nueva'
                    && $aviso->previousTitle === 'La ficha de antes'
                    && $aviso->actor === 'Ana Pérez';
            });
    }

    public function test_la_primera_eleccion_no_avisa_a_nadie(): void
    {
        // No es un cambio: no había ficha. Un aviso aquí enseñaría a ignorarlos.
        Notification::fake();
        $this->conectada();
        $this->fakeGoogle([$this->ficha()]);

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9'])
            ->assertSessionHas('status', 'google-business-location-chosen');

        Notification::assertNothingSent();
    }

    public function test_el_aviso_no_llega_a_una_cuenta_anonimizada(): void
    {
        // Su correo es sintético y rebota (`RGPD-01`).
        Notification::fake();
        $ana = $this->admin('Ana Pérez');
        $borrada = $this->admin('Fantasma');
        $borrada->forceFill(['email' => 'deleted_'.$borrada->id.'@'.User::ANONYMIZED_EMAIL_DOMAIN])->save();

        $this->conectada(placeId: 'ChIJvieja');
        $this->fakeGoogle([$this->ficha()]);

        $this->actingAs($ana)->post(route('admin.google_business.choose'), ['location' => 'locations/9', 'confirmed' => '1']);

        Notification::assertSentTo($ana, GoogleBusinessLocationChanged::class);
        Notification::assertNotSentTo($borrada, GoogleBusinessLocationChanged::class);
    }

    public function test_el_aviso_alcanza_a_quien_tiene_el_permiso_sin_ser_admin(): void
    {
        // El rol `admin` no es la única forma de poder tocar esto: quien tenga `settings.manage`
        // también, y enterarse es justo lo que el aviso persigue.
        Notification::fake();
        $ana = $this->admin('Ana Pérez');

        $encargado = User::factory()->create(['name' => 'Encargado']);
        $rol = Role::firstOrCreate(['name' => 'staff'], ['label' => 'Staff']);
        $rol->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['name' => 'settings.manage'], ['label' => 'Ajustes'])->id,
        ]);
        $encargado->roles()->attach($rol);

        $this->conectada(placeId: 'ChIJvieja');
        $this->fakeGoogle([$this->ficha()]);

        $this->actingAs($ana)->post(route('admin.google_business.choose'), ['location' => 'locations/9', 'confirmed' => '1']);

        Notification::assertSentTo($encargado->fresh(), GoogleBusinessLocationChanged::class);
    }

    public function test_si_el_aviso_falla_la_eleccion_se_guarda_igual(): void
    {
        // El cambio ya está guardado y auditado: perderlo por un SMTP sería el peor desenlace.
        $this->conectada(placeId: 'ChIJvieja');
        $this->fakeGoogle([$this->ficha()]);

        Notification::shouldReceive('send')->andThrow(new \RuntimeException('smtp caído'));

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.choose'), ['location' => 'locations/9', 'confirmed' => '1'])
            ->assertSessionHas('status', 'google-business-location-chosen');

        $this->assertSame('ChIJnueva', GoogleBusinessConnection::current()->place_id);
    }
}
