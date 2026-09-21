<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Content\Enums\GoogleReviewSuppressionReason;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\GoogleBusinessReviewSummary;
use App\Domain\Content\Models\GoogleBusinessReviewSuppression;
use App\Domain\Content\Services\GoogleReviewFilter;
use App\Domain\Content\Services\GoogleReviewImages;
use App\Domain\Content\Services\GoogleReviewSuppressions;
use App\Domain\Content\Services\IncomingGoogleReview;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\GoogleBusinessApi;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use App\Domain\Platform\Services\GoogleBusinessOAuth;
use App\Filament\Pages\GoogleBusinessProfilePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * **T2·5 · «Ocultar» una reseña, y que siga oculta**
 * (`docs/specs/google-business-profile.md` §4.3·7; `DECISIONES #524`, `#731`).
 *
 * ❗❗ Lo exigió la revisión de privacidad: aquí se publican reseñas de terceros **sin pedirles
 * permiso** —riesgo aceptado por el owner (§8·R1)— y esto es la mitigación que lo hace defendible.
 *
 * Lo que fija este caso es que ocultar sean **dos cosas**: borrar lo que hay, y que la pasada de
 * mañana no lo vuelva a traer. Con solo la primera, «ocultar» sería «ocultar hasta las 04:40».
 */
class GoogleReviewSuppressionTest extends TestCase
{
    use RefreshDatabase;

    private const NAME = 'accounts/1/locations/9/reviews/r1';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(GoogleReviewImages::DISK);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Ana']);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin']));

        return $user->fresh();
    }

    private function staff(): User
    {
        $user = User::factory()->create(['name' => 'Leo']);
        $rol = Role::firstOrCreate(['name' => 'staff'], ['label' => 'Staff']);
        $user->roles()->attach($rol);

        return $user->fresh();
    }

    /** @param array<string,mixed> $overrides */
    private function resena(array $overrides = []): GoogleBusinessReview
    {
        return GoogleBusinessReview::create(array_merge([
            'review_name' => self::NAME,
            'author_name' => 'Marta R.',
            'star_rating' => 5,
            'comment' => 'Los niños salieron encantados.',
            'review_created_at' => now()->subMonth(),
            'fetched_at' => now(),
        ], $overrides));
    }

    private function suppressions(): GoogleReviewSuppressions
    {
        return app(GoogleReviewSuppressions::class);
    }

    // ─────────── Ocultar son DOS cosas (§4.3·7) ───────────

    public function test_ocultar_borra_en_el_acto_lo_que_hay(): void
    {
        Storage::disk(GoogleReviewImages::DISK)->put($foto = hash('sha256', 'foto').'.png', 'x');
        $resena = $this->resena([
            'author_photo_path' => $foto,
            'reply_comment' => 'Gracias, Marta.',
        ]);

        $this->suppressions()->hide($resena, GoogleReviewSuppressionReason::AuthorRequest);

        // Nombre, foto, fotos y texto —también la respuesta del parque—: todo se va con la fila.
        $this->assertSame(0, GoogleBusinessReview::query()->count());
        Storage::disk(GoogleReviewImages::DISK)->assertMissing($foto);
    }

    public function test_ocultar_apunta_la_resena_para_que_no_vuelva(): void
    {
        $this->suppressions()->hide($this->resena(), GoogleReviewSuppressionReason::Minor);

        // ❗❗ La segunda mitad, y la que importa: la reseña **sigue publicada en Google** y la pasada
        // de mañana la traería. Sin el apunte, ocultar sería aplazar hasta las 04:40.
        $oculta = GoogleBusinessReviewSuppression::query()->sole();
        $this->assertSame(GoogleReviewSuppressions::hash(self::NAME), $oculta->review_hash);
        $this->assertSame(GoogleReviewSuppressionReason::Minor, $oculta->reason);
    }

    public function test_de_una_oculta_no_se_guarda_nada_mas_que_su_huella(): void
    {
        $this->suppressions()->hide($this->resena(), GoogleReviewSuppressionReason::Other);

        // ⚠️⚠️ Es la ÚNICA tabla de la feature que no caduca a los 30 días, así que cada columna de
        // más sería un dato de un tercero guardado para siempre. Se afirma sobre la fila CRUDA, no
        // sobre el modelo: un `$fillable` no impide que exista una columna.
        $columnas = array_keys((array) DB::table('google_business_review_suppressions')->first());
        sort($columnas);

        $this->assertSame(['created_at', 'id', 'reason', 'review_hash', 'updated_at'], $columnas);
    }

    public function test_ocultar_dos_veces_la_misma_resena_no_duplica(): void
    {
        $this->suppressions()->hide($this->resena(), GoogleReviewSuppressionReason::Other);
        // La pasada la vuelve a traer... no, no la trae. Pero el admin puede pulsar dos veces.
        $this->suppressions()->hide($this->resena(), GoogleReviewSuppressionReason::Minor);

        $this->assertSame(1, GoogleBusinessReviewSuppression::query()->count());
        // Y el motivo se queda con el último, que es el que acaba de decir una persona.
        $this->assertSame(GoogleReviewSuppressionReason::Minor, GoogleBusinessReviewSuppression::query()->sole()->reason);
    }

    // ─────────── Que no vuelva (§4.3·2 y §4.3·7) ───────────

    public function test_la_pasada_no_trae_una_resena_oculta(): void
    {
        $this->suppressions()->hide($this->resena(), GoogleReviewSuppressionReason::AuthorRequest);

        $filtro = (new GoogleReviewFilter(1))->withSuppressed($this->suppressions()->hashes());
        $candidata = $this->incoming(self::NAME);
        $otra = $this->incoming('accounts/1/locations/9/reviews/r2');

        $this->assertFalse($filtro->accepts($candidata));
        // El control: sin él, un filtro que lo rechazara todo pasaría el caso de arriba.
        $this->assertTrue($filtro->accepts($otra));
    }

    public function test_sin_lista_de_ocultas_el_filtro_deja_pasar(): void
    {
        // La otra mitad del control: el filtro por defecto no oculta nada.
        $this->assertTrue((new GoogleReviewFilter(1))->accepts($this->incoming(self::NAME)));
    }

    // ─────────── Dejar de ocultar (añadido a sabiendas, `#731`) ───────────

    public function test_dejar_de_ocultar_retira_el_apunte(): void
    {
        $hash = $this->suppressions()->hide($this->resena(), GoogleReviewSuppressionReason::Other);

        $this->assertTrue($this->suppressions()->unhide($hash));
        $this->assertSame(0, GoogleBusinessReviewSuppression::query()->count());
        // ⚠️ Y NO restaura nada: el texto y las imágenes se borraron al ocultar. La reseña vuelve
        // solo si sigue publicada en Google, y la trae la pasada como cualquier otra.
        $this->assertSame(0, GoogleBusinessReview::query()->count());
    }

    public function test_dejar_de_ocultar_lo_que_no_estaba_oculto_no_miente(): void
    {
        $this->assertFalse($this->suppressions()->unhide(hash('sha256', 'nada')));
    }

    // ─────────── El rastro (§4.3·7 y `RGPD-02`) ───────────

    public function test_el_rastro_lleva_el_hash_y_el_motivo_y_nada_mas(): void
    {
        $resena = $this->resena();

        $this->actingAs($this->admin())->post(route('admin.google_business.hide_review'), [
            'review' => $resena->id,
            'reason' => GoogleReviewSuppressionReason::Minor->value,
        ])->assertRedirect();

        $rastro = AuditLog::query()->where('action', GoogleReviewSuppressions::ACTION_HIDDEN)->sole();

        $this->assertSame([
            'hash' => GoogleReviewSuppressions::hash(self::NAME),
            'reason' => 'minor',
        ], $rastro->payload);

        // ❗❗ `audit_logs` **sobrevive a la reseña que lo causó**, así que lo que se escriba aquí dura
        // mucho más que el dato que lo originó. Ni texto, ni nombre, ni el identificador legible.
        $todo = json_encode($rastro->payload);
        $this->assertStringNotContainsString('Marta', $todo);
        $this->assertStringNotContainsString('encantados', $todo);
        $this->assertStringNotContainsString('reviews/r1', $todo);
    }

    public function test_dejar_de_ocultar_tambien_deja_rastro(): void
    {
        $hash = $this->suppressions()->hide($this->resena(), GoogleReviewSuppressionReason::Other);

        $this->actingAs($this->admin())
            ->post(route('admin.google_business.unhide_review'), ['hash' => $hash])
            ->assertRedirect();

        $this->assertSame(
            ['hash' => $hash],
            AuditLog::query()->where('action', GoogleReviewSuppressions::ACTION_UNHIDDEN)->sole()->payload,
        );
    }

    public function test_las_dos_acciones_estan_en_el_catalogo(): void
    {
        // Fuera de producción, una acción sin catalogar lanza: el rastro no puede nacer huérfano.
        $this->assertContains(GoogleReviewSuppressions::ACTION_HIDDEN, AuditLog::ACTIONS);
        $this->assertContains(GoogleReviewSuppressions::ACTION_UNHIDDEN, AuditLog::ACTIONS);
    }

    // ─────────── Quién puede (§4.2·1) ───────────

    public function test_sin_permiso_de_ajustes_no_se_puede_ocultar(): void
    {
        $resena = $this->resena();

        // ⚠️ `panel_role` deja pasar a `staff`: el permiso se comprueba EN EL CONTROLADOR, igual que
        // en conectar y desconectar.
        $this->actingAs($this->staff())->post(route('admin.google_business.hide_review'), [
            'review' => $resena->id,
            'reason' => GoogleReviewSuppressionReason::Other->value,
        ])->assertForbidden();

        $this->assertSame(1, GoogleBusinessReview::query()->count());
    }

    public function test_sin_permiso_de_ajustes_no_se_puede_dejar_de_ocultar(): void
    {
        $hash = $this->suppressions()->hide($this->resena(), GoogleReviewSuppressionReason::Other);

        $this->actingAs($this->staff())
            ->post(route('admin.google_business.unhide_review'), ['hash' => $hash])
            ->assertForbidden();

        $this->assertSame(1, GoogleBusinessReviewSuppression::query()->count());
    }

    public function test_un_permiso_suelto_de_ajustes_basta(): void
    {
        // El control positivo de los dos de arriba: si `settings.manage` no bastara, saldrían verdes
        // por el motivo equivocado.
        $usuario = $this->staff();
        $usuario->roles()->first()->permissions()->attach(
            Permission::firstOrCreate(['name' => 'settings.manage'], ['label' => 'Ajustes'])
        );

        $resena = $this->resena();

        $this->actingAs($usuario->fresh())->post(route('admin.google_business.hide_review'), [
            'review' => $resena->id,
            'reason' => GoogleReviewSuppressionReason::Other->value,
        ])->assertRedirect();

        $this->assertSame(0, GoogleBusinessReview::query()->count());
    }

    // ─────────── Lo que no se acepta ───────────

    public function test_un_motivo_que_no_esta_tasado_no_entra(): void
    {
        $resena = $this->resena();

        // ⚠️ Un texto libre en una tabla que no caduca acaba con el nombre de alguien dentro.
        $this->actingAs($this->admin())->post(route('admin.google_business.hide_review'), [
            'review' => $resena->id,
            'reason' => 'porque la madre de Lucia lo pidio',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(0, GoogleBusinessReviewSuppression::query()->count());
        $this->assertSame(1, GoogleBusinessReview::query()->count());
    }

    public function test_ocultar_una_resena_que_ya_no_esta_no_rompe(): void
    {
        // La pasada pudo retirarla entre que se pintó la pantalla y se pulsó el botón.
        $this->actingAs($this->admin())->post(route('admin.google_business.hide_review'), [
            'review' => 9999,
            'reason' => GoogleReviewSuppressionReason::Other->value,
        ])->assertRedirect()->assertSessionHas('status', 'google-business-review-missing');

        $this->assertSame(0, GoogleBusinessReviewSuppression::query()->count());
    }

    // ─────────── Lo que «Ocultar» NO toca (§4.3·7) ───────────

    public function test_ocultar_no_toca_la_media_ni_el_total(): void
    {
        GoogleBusinessReviewSummary::create([
            'average_rating' => 4.8, 'total_review_count' => 320, 'fetched_at' => now(),
        ]);

        $this->suppressions()->hide($this->resena(), GoogleReviewSuppressionReason::Minor);

        // ❗❗ Son de Google, contados sobre TODAS. Que «ocultar» bajara el recuento sería el parque
        // cambiando la nota de su propia ficha, que es justo el dato engañoso que persigue la
        // Ómnibus 2019/2161.
        $resumen = GoogleBusinessReviewSummary::current();
        $this->assertSame(4.8, $resumen->average_rating);
        $this->assertSame(320, $resumen->total_review_count);
    }

    // ─────────── La pantalla (§4.2·1) ───────────

    public function test_la_pantalla_lista_las_resenas_con_su_boton_de_ocultar(): void
    {
        $this->conectada();
        $this->resena();
        Http::preventStrayRequests();
        // Sin el doble, el render pide fichas a Google y este caso mediría la red, no la pantalla.
        $this->fakeLocations();

        $this->actingAs($this->admin())
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertOk()
            ->assertSee('Los niños salieron encantados.', false)
            ->assertSee('Marta R.')
            ->assertSee(route('admin.google_business.hide_review'), false)
            // Los cuatro motivos tasados, en el desplegable.
            ->assertSee(GoogleReviewSuppressionReason::Minor->label());
    }

    public function test_la_pantalla_no_ensena_una_resena_pasada_de_plazo(): void
    {
        $this->conectada();
        // ⚠️ El MISMO filtro que la portada: si aquí saliera una que la web ya no enseña, el admin
        // gastaría una entrada de una lista que no caduca en algo que ya no se veía.
        $this->resena(['fetched_at' => now()->subDays(GoogleBusinessReview::FRESH_DAYS + 1)]);
        Http::preventStrayRequests();
        $this->fakeLocations();

        $this->actingAs($this->admin())
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertOk()
            ->assertDontSee('Los niños salieron encantados.', false);
    }

    public function test_la_pantalla_ensena_lo_oculto_sin_decir_que_era(): void
    {
        $hash = $this->suppressions()->hide($this->resena(), GoogleReviewSuppressionReason::Minor);
        Http::preventStrayRequests();

        $respuesta = $this->actingAs($this->admin())->get(GoogleBusinessProfilePage::getUrl())->assertOk();

        $respuesta->assertSee(GoogleReviewSuppressionReason::Minor->label());
        $respuesta->assertSee(route('admin.google_business.unhide_review'), false);
        // ❗❗ Y no puede decir CUÁL era, porque no lo sabemos: solo guardamos la huella. Saberlo
        // obligaría a conservar su texto en la única tabla que no caduca.
        $respuesta->assertDontSee('Los niños salieron encantados.', false);
        $respuesta->assertDontSee('Marta R.');
        $respuesta->assertSee(substr($hash, 0, 12), false);
    }

    private function conectada(): GoogleBusinessConnection
    {
        return GoogleBusinessConnection::create([
            'status' => GoogleBusinessStatus::Connected,
            'refresh_token' => '1//el-refresco',
            'token_fingerprint' => GoogleBusinessConnection::fingerprint('1//el-refresco'),
            'location_name' => 'locations/9',
            'account_name' => 'accounts/1',
            'location_title' => 'PlayJump',
            'connected_at' => now(),
        ]);
    }

    private function fakeLocations(): void
    {
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_ID_KEY], ['value' => 'central.apps.googleusercontent.com', 'group' => 'google']);
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY], ['value' => 'GOCSPX-secreto', 'group' => 'google']);
        Setting::flushMemo();

        Http::fake([
            GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.x', 'expires_in' => 3600]),
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['accounts' => [['name' => 'accounts/1']]]),
            GoogleBusinessApi::LOCATIONS_BASE.'*' => Http::response(['locations' => []]),
        ]);
    }

    private function incoming(string $name): IncomingGoogleReview
    {
        return IncomingGoogleReview::fromApi([
            'name' => $name,
            'starRating' => 'FIVE',
            'comment' => 'Bien.',
            'createTime' => '2026-09-01T10:00:00Z',
        ]);
    }
}
