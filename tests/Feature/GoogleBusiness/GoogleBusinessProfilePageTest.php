<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Content\Enums\GoogleReviewSuppressionReason;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\GoogleBusinessReviewSummary;
use App\Domain\Content\Models\GoogleBusinessReviewSuppression;
use App\Domain\Content\Services\GoogleReviewImages;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\GoogleBusinessApi;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use App\Domain\Platform\Services\GoogleBusinessOAuth;
use App\Filament\Pages\GoogleBusinessProfilePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * **Lo que el admin LEE en «Ficha de Google»** — el ojo del owner del 2026-09-21 (`#733`).
 *
 * La pantalla se vio por primera vez con datos y dijo cosas falsas o a medias: la hora en UTC, «queda
 * elegir la ficha» con la ficha ya elegida, «37 publicadas» siendo 37 las de Google, y ni rastro de la
 * respuesta del parque ni de las fotos, que son justo lo que hay que mirar para decidir si se oculta
 * una reseña por un menor. Cada caso de aquí es una de esas frases.
 *
 * ⚠️ `Http::preventStrayRequests()`: con la conexión en pie la pantalla PIDE la lista de fichas a
 * Google al pintarse, así que cada caso le da un doble.
 */
class GoogleBusinessProfilePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Storage::fake(GoogleReviewImages::DISK);

        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_ID_KEY], ['value' => 'central.apps.googleusercontent.com', 'group' => 'google']);
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY], ['value' => 'GOCSPX-secreto', 'group' => 'google']);

        Http::fake([
            GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.x', 'expires_in' => 3600]),
            GoogleBusinessApi::ACCOUNTS_ENDPOINT.'*' => Http::response(['accounts' => [['name' => 'accounts/1']]]),
            GoogleBusinessApi::LOCATIONS_BASE.'*' => Http::response(['locations' => []]),
        ]);
    }

    private function admin(string $nombre = 'Ana'): User
    {
        $user = User::factory()->create(['name' => $nombre]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin']));

        return $user->fresh();
    }

    private function conectada(array $overrides = []): GoogleBusinessConnection
    {
        return GoogleBusinessConnection::create(array_merge([
            'status' => GoogleBusinessStatus::Connected,
            'refresh_token' => '1//el-refresco',
            'token_fingerprint' => GoogleBusinessConnection::fingerprint('1//el-refresco'),
            'location_name' => 'locations/9',
            'account_name' => 'accounts/1',
            'location_title' => 'Parque de prueba',
        ], $overrides));
    }

    private function resena(string $id, array $overrides = []): GoogleBusinessReview
    {
        return GoogleBusinessReview::create(array_merge([
            'review_name' => 'accounts/1/locations/9/reviews/'.$id,
            'author_name' => 'Autor '.$id,
            'star_rating' => 5,
            'comment' => 'Texto de la reseña '.$id,
            'review_created_at' => now()->subDays(1),
            'fetched_at' => now(),
        ], $overrides));
    }

    private function pantalla(?User $quien = null): TestResponse
    {
        return $this->actingAs($quien ?? $this->admin())
            ->get(GoogleBusinessProfilePage::getUrl())
            ->assertOk();
    }

    // ─────────── La hora es la del PARQUE, no la del servidor ───────────

    public function test_quien_conecto_sale_con_la_hora_del_parque(): void
    {
        // 22:30 UTC del 19 = 00:30 del 20 en Madrid (horario de verano): cambia hasta el DÍA.
        $admin = $this->admin('Ana');
        $this->conectada([
            'connected_by_user_id' => $admin->id,
            'connected_at' => Carbon::parse('2026-09-19 22:30:00', 'UTC'),
        ]);

        $this->pantalla($admin)
            ->assertSee('20/09/2026 00:30')
            ->assertDontSee('19/09/2026 22:30');
    }

    public function test_la_ultima_sincronizacion_completa_se_dice_con_la_hora_del_parque(): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00', 'UTC'));
        $this->conectada();
        GoogleBusinessReviewSummary::create([
            'average_rating' => 4.6, 'total_review_count' => 37,
            'fetched_at' => Carbon::parse('2026-09-20 22:40:00', 'UTC'),
        ]);

        $this->pantalla()
            ->assertSee(__('admin.google_business.last_sync', ['date' => '21/09/2026 00:40']))
            ->assertDontSee(__('admin.google_business.last_sync_stale', ['days' => GoogleBusinessReviewSummary::FRESH_DAYS]));
    }

    public function test_una_sincronizacion_de_hace_mas_de_tres_dias_se_avisa(): void
    {
        // Pasado ese plazo la web deja de enseñar nombres, fotos y media (§4.3·4): si el admin no
        // lo lee aquí, no lo lee en ningún sitio, porque la web no da error — solo enseña menos.
        $this->conectada();
        GoogleBusinessReviewSummary::create([
            'average_rating' => 4.6, 'total_review_count' => 37,
            'fetched_at' => now()->subDays(GoogleBusinessReviewSummary::FRESH_DAYS + 1),
        ]);

        $this->pantalla()
            ->assertSee(__('admin.google_business.last_sync_stale', ['days' => GoogleBusinessReviewSummary::FRESH_DAYS]));
    }

    public function test_sin_ninguna_sincronizacion_se_dice_en_vez_de_callar(): void
    {
        $this->conectada();

        $this->pantalla()->assertSee(__('admin.google_business.last_sync_never'));
    }

    // ─────────── El estado dice lo que QUEDA por hacer, no lo que ya se hizo ───────────

    public function test_con_la_ficha_elegida_el_estado_no_pide_elegirla(): void
    {
        $this->conectada();

        $this->pantalla()
            ->assertSee(__('admin.google_business.states.connected.linked'))
            ->assertDontSee(__('admin.google_business.states.connected.what_to_do'));
    }

    public function test_sin_ficha_elegida_el_estado_sigue_pidiendola(): void
    {
        // El CONTROL del caso de arriba: sin él, un texto que no se pinta nunca pasaría igual.
        $this->conectada(['location_name' => null, 'account_name' => null, 'location_title' => null]);

        $this->pantalla()
            ->assertSee(__('admin.google_business.states.connected.what_to_do'))
            ->assertDontSee(__('admin.google_business.states.connected.linked'))
            ->assertDontSee(__('admin.google_business.last_sync_never'));
    }

    // ─────────── La cifra es de Google ───────────

    public function test_la_cifra_dice_que_es_de_google_y_no_que_la_publicamos(): void
    {
        // 22:30 UTC del 21 = 00:30 del 22 en Madrid: la fecha de la cifra también es la del parque.
        $this->travelTo(Carbon::parse('2026-09-21 22:30:00', 'UTC'));
        $this->conectada();
        $this->resena('1');
        GoogleBusinessReviewSummary::create([
            'average_rating' => 4.6, 'total_review_count' => 37, 'fetched_at' => now(),
        ]);

        $this->pantalla()
            ->assertSee(__('admin.google_business.reviews_count', ['count' => 37, 'rating' => '4,6', 'date' => '22/09/2026']))
            ->assertDontSee('37 publicadas');
    }

    // ─────────── Qué se ve en la web y qué es reserva ───────────

    public function test_el_panel_dice_cuales_se_ven_en_la_web_y_cuales_son_de_reserva(): void
    {
        $this->conectada();
        $antigua = null;

        foreach (range(1, 7) as $n) {
            // La 7 es la MÁS ANTIGUA: la portada enseña las 6 más recientes.
            $r = $this->resena((string) $n, ['review_created_at' => now()->subDays($n)]);
            $antigua = $r;
        }

        $html = $this->pantalla()->getContent();

        $this->assertSame(6, substr_count($html, 'data-shown="1"'));
        $this->assertSame(1, substr_count($html, 'data-shown="0"'));
        $this->assertStringContainsString('data-review="'.$antigua->id.'" data-shown="0"', $html);
    }

    // ─────────── Lo que hay que ver para decidir si se oculta ───────────

    public function test_el_panel_ensena_la_respuesta_y_las_fotos_de_cada_resena(): void
    {
        $this->conectada();
        $disco = Storage::disk(GoogleReviewImages::DISK);
        $cara = hash('sha256', 'cara').'.jpg';
        $foto1 = hash('sha256', 'foto-1').'.jpg';
        $foto2 = hash('sha256', 'foto-2').'.png';
        foreach ([$cara, $foto1, $foto2] as $f) {
            $disco->put($f, 'x');
        }

        $this->resena('1', [
            'author_photo_path' => $cara,
            'reply_comment' => 'Gracias por venir, os esperamos.',
            'photos' => [$foto1, $foto2],
        ]);

        $this->pantalla()
            ->assertSee('Gracias por venir, os esperamos.')
            ->assertSee(route('resenas.foto', ['fichero' => $foto1]), false)
            ->assertSee(route('resenas.foto', ['fichero' => $foto2]), false)
            ->assertSee(route('resenas.foto', ['fichero' => $cara]), false);
    }

    public function test_el_panel_no_pinta_una_imagen_que_no_tiene_nuestro_nombre(): void
    {
        // La guarda del modelo solo para URLs; un nombre suelto que no es nuestro no llega al `src`.
        $this->conectada();
        Storage::disk(GoogleReviewImages::DISK)->put($buena = hash('sha256', 'buena').'.jpg', 'x');
        $this->resena('1', ['author_photo_path' => 'cara-ajena.jpg', 'photos' => ['foto-ajena.jpg', $buena]]);

        $this->pantalla()
            ->assertDontSee('cara-ajena.jpg')
            ->assertDontSee('foto-ajena.jpg')
            ->assertSee(route('resenas.foto', ['fichero' => $buena]), false);
    }

    public function test_la_fecha_de_una_oculta_es_la_del_parque(): void
    {
        $this->conectada();
        $oculta = GoogleBusinessReviewSuppression::create([
            'review_hash' => hash('sha256', 'accounts/1/locations/9/reviews/x'),
            'reason' => GoogleReviewSuppressionReason::Other,
        ]);
        $oculta->forceFill(['created_at' => Carbon::parse('2026-09-19 23:15:00', 'UTC')])->save();

        $this->pantalla()
            ->assertSee(__('admin.google_business.hidden_since', ['date' => '20/09/2026']))
            ->assertDontSee(__('admin.google_business.hidden_since', ['date' => '19/09/2026']));
    }

    public function test_la_fecha_de_una_resena_es_la_del_parque(): void
    {
        $this->conectada();
        $this->resena('1', ['review_created_at' => Carbon::parse('2026-09-19 23:15:00', 'UTC')]);

        $this->pantalla()
            ->assertSee('20/09/2026')
            ->assertDontSee('19/09/2026');
    }

    // ─────────── El panel también está en chino ───────────

    /**
     * ⚠️⚠️ El panel habla `es` y `zh_CN` (`SetAdminLocale::SUPPORTED`) y el idioma de respaldo es
     * `en`, **que no tiene `admin.php`**: una clave que falte en chino se pinta EN CRUDO. Así salieron
     * las catorce de «Ocultar» (T2·5, `#731`) hasta `#733`.
     */
    public function test_ninguna_clave_de_la_ficha_falta_en_chino(): void
    {
        $es = array_keys(Arr::dot((require lang_path('es/admin.php'))['google_business']));
        $zh = array_keys(Arr::dot((require lang_path('zh_CN/admin.php'))['google_business']));

        // El instrumento, antes de fiarse de él: la lista española trae lo que se sabe que tiene.
        $this->assertContains('results.unreachable', $es);
        $this->assertContains('reasons.minor', $es);

        $this->assertSame([], array_values(array_diff($es, $zh)), 'claves de la ficha sin traducir al chino');
    }

    public function test_los_motivos_de_ocultar_salen_en_el_idioma_del_panel(): void
    {
        app()->setLocale('zh_CN');

        foreach (GoogleReviewSuppressionReason::cases() as $motivo) {
            $this->assertNotSame('admin.google_business.reasons.'.$motivo->value, $motivo->label());
            $this->assertSame(__('admin.google_business.reasons.'.$motivo->value, [], 'zh_CN'), $motivo->label());
        }
    }
}
