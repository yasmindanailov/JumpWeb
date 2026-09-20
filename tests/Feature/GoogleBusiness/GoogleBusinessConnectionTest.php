<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\GoogleBusinessConnectionState;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **T1·1 · La conexión con la ficha de Google** (`docs/specs/google-business-profile.md` §4.2;
 * `DECISIONES #524`, `#719`).
 *
 * Lo que fija este caso es el CIMIENTO: dónde vive el token, que no se pueda leer por accidente, y
 * que los siete estados de §4.2·7 salgan de una sola cuenta.
 */
class GoogleBusinessConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function writeCredentials(string $id = 'central.apps.googleusercontent.com', string $secret = 'GOCSPX-secreto'): void
    {
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_ID_KEY], ['value' => $id, 'group' => 'google']);
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY], ['value' => $secret, 'group' => 'google']);
    }

    private function connectedRow(string $token = 'refresh-de-verdad'): GoogleBusinessConnection
    {
        return GoogleBusinessConnection::create([
            'status' => GoogleBusinessStatus::Connected,
            'refresh_token' => $token,
            'token_fingerprint' => GoogleBusinessConnection::fingerprint($token),
            'location_name' => 'accounts/1/locations/2',
        ]);
    }

    // ─────────── Los estados derivados (§4.2·7) ───────────

    public function test_sin_credenciales_esta_sin_configurar_aunque_haya_token(): void
    {
        // Con token guardado y todo: sin las credenciales de JumpSystem no hay con qué canjearlo.
        $this->connectedRow();

        $this->assertSame(GoogleBusinessStatus::Unconfigured, GoogleBusinessConnectionState::current());
    }

    public function test_media_credencial_no_basta(): void
    {
        // La lección de `PublicConfigResource` que ya pagó el login con Google: con una sola clave la
        // ida sale y lo que falla es el canje, al final y con un error de Google que no dice cuál falta.
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_ID_KEY], ['value' => 'solo-el-id', 'group' => 'google']);

        $this->assertFalse(GoogleBusinessCredentials::fresh()->configured());
        $this->assertSame(GoogleBusinessStatus::Unconfigured, GoogleBusinessConnectionState::current());

        Setting::query()->where('key', GoogleBusinessCredentials::CLIENT_ID_KEY)->delete();
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY], ['value' => 'solo-el-secreto', 'group' => 'google']);

        $this->assertFalse(GoogleBusinessCredentials::fresh()->configured());
        $this->assertSame(GoogleBusinessStatus::Unconfigured, GoogleBusinessConnectionState::current());
    }

    public function test_con_credenciales_y_sin_fila_esta_lista_para_conectar(): void
    {
        $this->writeCredentials();

        $this->assertSame(GoogleBusinessStatus::ReadyToConnect, GoogleBusinessConnectionState::current());
    }

    public function test_una_fila_sin_token_sigue_estando_lista_para_conectar(): void
    {
        $this->writeCredentials();
        GoogleBusinessConnection::create(['status' => GoogleBusinessStatus::ReadyToConnect]);

        $this->assertSame(GoogleBusinessStatus::ReadyToConnect, GoogleBusinessConnectionState::current());
    }

    public function test_con_token_manda_el_estado_guardado(): void
    {
        $this->writeCredentials();
        $row = $this->connectedRow();

        $this->assertSame(GoogleBusinessStatus::Connected, GoogleBusinessConnectionState::current());

        $row->update(['status' => GoogleBusinessStatus::Forbidden]);

        $this->assertSame(GoogleBusinessStatus::Forbidden, GoogleBusinessConnectionState::current());
    }

    // ─────────── El token: cifrado, ilegible, invisible ───────────

    public function test_el_token_va_cifrado_en_la_base(): void
    {
        $row = $this->connectedRow('refresh-de-verdad');

        $crudo = (string) DB::table('google_business_connections')->where('id', $row->id)->value('refresh_token');

        $this->assertNotSame('refresh-de-verdad', $crudo, 'el token está en claro en la BD');
        $this->assertStringNotContainsString('refresh-de-verdad', $crudo);
        // …y sigue siendo legible por el camino bueno.
        $this->assertSame('refresh-de-verdad', $row->fresh()->readToken());
    }

    public function test_un_token_ilegible_es_una_conexion_caducada(): void
    {
        $this->writeCredentials();
        $row = $this->connectedRow();

        // La `APP_KEY` rotó sin `APP_PREVIOUS_KEYS`: lo que hay guardado ya no descifra. Se escribe
        // por el constructor de consultas para no pasar por el cast.
        DB::table('google_business_connections')->where('id', $row->id)->update(['refresh_token' => 'ceniza-de-otra-clave']);

        $recargada = GoogleBusinessConnection::current();

        // Y esto es lo que importa: HAY token guardado (no es «lista para conectar»)…
        $this->assertTrue($recargada->hasStoredToken());
        // …pero no se puede leer, y eso no revienta: se convierte en «caducada».
        $this->assertNull($recargada->readToken());
        $this->assertSame(GoogleBusinessStatus::Expired, GoogleBusinessConnectionState::current());
    }

    public function test_el_token_recien_puesto_se_lee_antes_de_guardar(): void
    {
        // El desfase que mide este caso (medido el 2026-09-20): `getRawOriginal()` devuelve lo que se
        // LEYÓ de la base, así que entre poner el token nuevo y guardarlo seguiría entregando el
        // viejo. Es media reconexión escribiendo con la llave anterior, y no rompe nada visible.
        $row = $this->connectedRow('token-viejo');

        $row->refresh_token = 'token-nuevo';

        $this->assertTrue($row->hasStoredToken());
        $this->assertSame('token-nuevo', $row->readToken(), 'la lectura se quedó en el token anterior');
    }

    public function test_ni_el_token_ni_su_huella_salen_al_serializar(): void
    {
        // La pantalla del panel es Livewire y serializa sus propiedades al snapshot que viaja al
        // navegador: sin `$hidden`, el token cifrado acabaría en el HTML de Ajustes.
        $fila = $this->connectedRow()->fresh()->toArray();

        $this->assertArrayNotHasKey('refresh_token', $fila);
        $this->assertArrayNotHasKey('token_fingerprint', $fila);
        $this->assertStringNotContainsString('refresh-de-verdad', json_encode($fila));
    }

    public function test_el_secreto_del_cliente_no_aparece_en_un_volcado(): void
    {
        $this->writeCredentials(secret: 'GOCSPX-el-secreto-de-jumpsystem');

        $volcado = print_r(GoogleBusinessCredentials::fresh(), true);

        $this->assertStringNotContainsString('GOCSPX-el-secreto-de-jumpsystem', $volcado);
        $this->assertStringContainsString('(oculto)', $volcado);
        // El id sí se ve: no es secreto y sin él el volcado no serviría para depurar.
        $this->assertStringContainsString('central.apps.googleusercontent.com', $volcado);
        // Y por el camino bueno el secreto sigue llegando entero.
        $this->assertSame('GOCSPX-el-secreto-de-jumpsystem', GoogleBusinessCredentials::fresh()->secret());
    }

    // ─────────── Las dos guardas que nadie ve hasta que muerden ───────────

    public function test_solo_puede_haber_una_conexion(): void
    {
        $this->connectedRow();

        $this->expectException(QueryException::class);

        GoogleBusinessConnection::create(['status' => GoogleBusinessStatus::ReadyToConnect]);
    }

    public function test_las_credenciales_se_leen_frescas_y_no_del_memo(): void
    {
        // El caso real: un worker de cola lleva horas vivo, el owner aprovisiona por SSH con un
        // INSERT que no pasa por los eventos de Eloquent, y el memo del worker sigue siendo el de
        // cuando arrancó.

        // 1) Se ceba el memo con la tabla vacía…
        $this->assertNull(Setting::value(GoogleBusinessCredentials::CLIENT_ID_KEY));

        // 2) …y se escriben las credenciales SIN disparar `saved()`.
        DB::table('settings')->insert([
            ['key' => GoogleBusinessCredentials::CLIENT_ID_KEY, 'value' => 'id', 'group' => 'google', 'created_at' => now(), 'updated_at' => now()],
            ['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY, 'value' => 'secreto', 'group' => 'google', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3) El instrumento, comprobado ANTES de fiarse de él: el memo está rancio de verdad.
        $this->assertNull(
            Setting::value(GoogleBusinessCredentials::CLIENT_ID_KEY),
            'el memo no está rancio: este caso no está midiendo nada'
        );

        // 4) Y la lectura fresca sí las ve.
        $this->assertTrue(GoogleBusinessCredentials::fresh()->configured());
    }

    public function test_la_huella_distingue_el_token_viejo_de_una_reconexion(): void
    {
        // Entre que el worker cogió el token y que Google le dijo `invalid_grant`, el admin reconectó.
        $row = $this->connectedRow('token-viejo');

        $this->assertTrue($row->tokenStillIs('token-viejo'));

        $row->update(['refresh_token' => 'token-nuevo', 'token_fingerprint' => GoogleBusinessConnection::fingerprint('token-nuevo')]);

        $this->assertFalse(
            $row->fresh()->tokenStillIs('token-viejo'),
            'el worker viejo podría pisar la reconexión del admin'
        );
        $this->assertTrue($row->fresh()->tokenStillIs('token-nuevo'));
    }

    // ─────────── La regla del enum ───────────

    public function test_solo_la_conexion_llama_a_google(): void
    {
        foreach (GoogleBusinessStatus::cases() as $estado) {
            $this->assertSame(
                $estado === GoogleBusinessStatus::Connected,
                $estado->syncs(),
                "«{$estado->value}» no debería decidir por su cuenta si se llama a Google"
            );
        }
    }

    public function test_los_estados_derivados_no_son_averias(): void
    {
        $this->assertTrue(GoogleBusinessStatus::Unconfigured->isDerived());
        $this->assertTrue(GoogleBusinessStatus::ReadyToConnect->isDerived());
        $this->assertFalse(GoogleBusinessStatus::Unconfigured->isFailure(), 'una instalación a medio montar no es una avería que avisar');
        $this->assertFalse(GoogleBusinessStatus::Connected->isFailure());

        foreach ([GoogleBusinessStatus::Expired, GoogleBusinessStatus::Forbidden, GoogleBusinessStatus::LocationLost, GoogleBusinessStatus::NoApiAccess] as $roto) {
            $this->assertTrue($roto->isFailure(), "«{$roto->value}» debería avisarse");
            $this->assertFalse($roto->isDerived());
        }
    }
}
