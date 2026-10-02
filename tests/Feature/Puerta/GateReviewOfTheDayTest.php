<?php

namespace Tests\Feature\Puerta;

use App\Domain\Content\Enums\GoogleReviewSuppressionReason;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Services\GateReviewOfTheDay;
use App\Domain\Content\Services\GoogleReviewFilter;
use App\Domain\Content\Services\GoogleReviewSuppressions;
use App\Domain\Content\Services\ReviewKeywords;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Platform\Models\Setting;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La reseña del día de la Puerta** (`docs/specs/puerta-nueva.md` §4.4, la P3: D16, D19, D20; el mockup «Lo que dicen de
 * vosotros.»): de qué fuente sale y en qué orden, la ventana de 30 días a la medianoche del PARQUE, el turno, lo que se pinta
 * y dónde. Cada guarda tiene su mutante en `scripts/mutar-puerta-p1.sh` («reseña · …»).
 *
 * El reloj: el 2 de octubre a las 10:00 en Madrid (las 08:00 en UTC). La ventana empieza a la medianoche del parque de hace
 * 30 días: el 2 de septiembre a las 00:00 en Madrid, que en la base (UTC) es el 1 de septiembre a las 22:00.
 */
class GateReviewOfTheDayTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-02 10:00:00', 'Europe/Madrid'));
        app()->setLocale('es');
        $this->words("monitor\nIrene");
    }

    private function words(string $raw): void
    {
        Setting::updateOrCreate(['key' => ReviewKeywords::KEY], ['value' => $raw, 'group' => 'puerta']);
    }

    /** Una del Perfil de Empresa, vista HOY por una pasada (su autor se puede pintar) salvo que se diga otra cosa. */
    private function profile(string $text, string $createdUtc, array $extra = []): GoogleBusinessReview
    {
        return GoogleBusinessReview::create($extra + [
            'review_name' => 'accounts/1/locations/1/reviews/r'.++$this->n,
            'author_name' => 'Autora '.$this->n,
            'anonymous' => false,
            'star_rating' => 5,
            'comment' => $text,
            'text_ambiguous' => false,
            'review_created_at' => Carbon::parse($createdUtc, 'UTC'),
            'fetched_at' => now(),
        ]);
    }

    /** Una COPIADA de la ficha (`#771`), activa y con cinco estrellas salvo que se diga otra cosa. */
    private function copied(string $text, string $date, array $extra = []): Testimonial
    {
        return Testimonial::create($extra + [
            'origin' => Testimonial::ORIGIN_GOOGLE,
            'source_ref' => 'copia-'.++$this->n,
            'author' => 'Laura M.',
            'rating' => 5,
            'text' => ['es' => $text],
            'published_at' => $date,
            'is_active' => true,
            'position' => 0,
        ]);
    }

    /** La del primer turno (la más nueva de las que casan). */
    private function today(): ?array
    {
        Setting::flushMemo();

        return app(GateReviewOfTheDay::class)->forGate(0);
    }

    // ─── De dónde sale (D16) ───────────────────────────────────────────────────

    public function test_the_profile_goes_first_and_the_copied_ones_only_when_nothing_there_matches(): void
    {
        $perfil = $this->profile('Los monitores, un diez.', '2026-09-28 15:00:00');
        $copiada = $this->copied('Irene estuvo pendiente de los peques toda la tarde.', '2026-09-29');

        $this->assertSame(['perfil', $perfil->id], [$this->today()['fuente'], $this->today()['id']]);

        $perfil->update(['comment' => 'Todo perfecto, repetiremos.']);
        $this->assertSame(['copiada', $copiada->id], [$this->today()['fuente'], $this->today()['id']]);
    }

    /** Una copiada sale si el parque la tiene ACTIVA, viene de Google y llega al mínimo; una opinión propia, nunca. */
    public function test_a_copied_one_must_be_active_from_google_and_reach_the_minimum(): void
    {
        $apagada = $this->copied('Los monitores, un diez.', '2026-09-29', ['is_active' => false]);
        $this->copied('Los monitores, un diez.', '2026-09-29', ['origin' => Testimonial::ORIGIN_OWN]);
        $this->copied('Los monitores, un diez.', '2026-09-29', ['rating' => null]);
        $this->copied('Los monitores, un diez.', '2026-09-29', ['rating' => 3]);
        $this->assertNull($this->today());

        $apagada->update(['is_active' => true]);
        $this->assertSame($apagada->id, $this->today()['id']);
    }

    public function test_the_window_starts_at_the_park_midnight_thirty_days_ago(): void
    {
        $justo = $this->profile('Los monitores, un diez.', '2026-09-01 21:59:59');
        $this->assertNull($this->today(), 'un segundo antes de la medianoche del parque de hace 30 días, fuera');

        $justo->update(['review_created_at' => Carbon::parse('2026-09-01 22:00:00', 'UTC')]);
        $this->assertSame($justo->id, $this->today()['id'], 'a la medianoche del parque (las 22:00 en UTC), dentro');

        $justo->delete();
        $copiada = $this->copied('Los monitores, un diez.', '2026-09-01');
        $this->assertNull($this->today(), 'una copiada del día anterior a la ventana, fuera');
        $copiada->update(['published_at' => '2026-09-02']);
        $this->assertSame($copiada->id, $this->today()['id']);
    }

    /** El plazo de Google (`withinRetention()`, 29 días desde la última pasada que la vio) y la ambigua, fuera. */
    public function test_the_profile_retention_and_an_ambiguous_text_keep_a_review_out(): void
    {
        $vieja = $this->profile('Los monitores, un diez.', '2026-09-02 23:00:00', ['fetched_at' => Carbon::parse('2026-09-03 07:59:00', 'UTC')]);
        $this->assertNull($this->today(), 'vista hace más de 29 días: fuera aunque sea de la ventana');
        $vieja->update(['fetched_at' => Carbon::parse('2026-09-03 08:01:00', 'UTC')]);
        $this->assertSame($vieja->id, $this->today()['id']);

        $vieja->update(['text_ambiguous' => true]);
        $this->assertNull($this->today(), 'puede llevar la traducción de Google: fuera');
    }

    /** El mínimo VIGENTE se reaplica al leer: la pasada solo lo aplicó al guardar. */
    public function test_the_current_minimum_of_stars_is_applied_when_reading(): void
    {
        $cuatro = $this->profile('Los monitores, un diez.', '2026-09-28 15:00:00', ['star_rating' => 4]);
        $this->assertSame($cuatro->id, $this->today()['id']);

        Setting::updateOrCreate(['key' => GoogleReviewFilter::MIN_STARS_KEY], ['value' => '5', 'group' => 'reviews']);
        $this->assertNull($this->today());
    }

    /** Sin caché: ocultar una del Perfil o apagar una copiada la quita de la Puerta en el pintado siguiente. */
    public function test_hiding_or_switching_off_a_review_takes_it_off_the_gate_at_once(): void
    {
        $perfil = $this->profile('Los monitores, un diez.', '2026-09-28 15:00:00');
        $copiada = $this->copied('Irene, un amor.', '2026-09-29');
        $this->assertSame($perfil->id, $this->today()['id']);

        app(GoogleReviewSuppressions::class)->hide($perfil, GoogleReviewSuppressionReason::Other);
        $this->assertSame($copiada->id, $this->today()['id']);

        $copiada->update(['is_active' => false]);
        $this->assertNull($this->today());
    }

    public function test_without_words_there_is_no_review_and_no_query(): void
    {
        $this->profile('Los monitores, un diez.', '2026-09-28 15:00:00');
        $this->words('');
        Setting::flushMemo();
        Setting::value(ReviewKeywords::KEY);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $resena = app(GateReviewOfTheDay::class)->forGate(0);
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertNull($resena);
        $this->assertSame(0, $consultas);
    }

    // ─── Cuál sale: por turno, una con cada cliente (D19, `#910`) ────────────────

    public function test_the_turn_walks_the_matching_ones_newest_first_and_starts_again(): void
    {
        $vieja = $this->copied('Los monitores, un diez.', '2026-09-27');
        $media = $this->copied('Los monitores, un diez.', '2026-09-28');
        $nueva = $this->copied('Los monitores, un diez.', '2026-09-29');
        Setting::flushMemo();
        $turno = fn (int $n): int => app(GateReviewOfTheDay::class)->forGate($n)['id'];

        $this->assertSame([$nueva->id, $media->id, $vieja->id, $nueva->id], [$turno(0), $turno(1), $turno(2), $turno(3)]);
    }

    /**
     * La PANTALLA lleva el turno en la sesión: abrirla (o refrescarla) y vaciarla («Nueva búsqueda», el cierre a los 5 min)
     * pasan a la siguiente; una ficha abierta no lo mueve —su velo enseña la misma que había—.
     */
    public function test_each_time_the_screen_empties_the_next_review_comes_and_a_profile_keeps_it(): void
    {
        [$staff, , $card] = $this->door();
        $vieja = $this->copied('Los monitores, un diez.', '2026-09-27');
        $media = $this->copied('Los monitores, un diez.', '2026-09-28');
        $nueva = $this->copied('Los monitores, un diez.', '2026-09-29');
        $sale = fn (Testimonial $t): string => 'data-gate-review="copiada-'.$t->id.'"';

        $page = Livewire::actingAs($staff)->test(ValidarRegistro::class);
        $page->assertSeeHtml($sale($nueva));

        $page->call('clear')->assertSeeHtml($sale($media));
        $page->set('input', $card)->call('search')->assertSeeHtml($sale($media));
        $page->call('clear')->assertSeeHtml($sale($vieja));

        Livewire::actingAs($staff)->test(ValidarRegistro::class)->assertSeeHtml($sale($nueva));

        // El turno es de cada PERSONA: otra que abre la Puerta empieza por la más nueva, y lo suyo no mueve el de la primera.
        $otra = User::factory()->create();
        $otra->roles()->sync([Role::where('name', 'staff')->value('id')]);
        Livewire::actingAs($otra)->test(ValidarRegistro::class)->assertSeeHtml($sale($nueva))->call('clear')->assertSeeHtml($sale($media));
        Livewire::actingAs($staff)->test(ValidarRegistro::class)->assertSeeHtml($sale($media));
    }

    // ─── Lo que se pinta (D20) ────────────────────────────────────────────────

    public function test_the_author_is_the_one_the_landing_paints(): void
    {
        $this->profile('Los monitores, un diez.', '2026-09-29 08:00:00', ['fetched_at' => now()->subDays(4)]);
        $resena = $this->today();
        $this->assertNull($resena['autor'], 'del Perfil sin una pasada de los últimos 3 días: sin nombre');
        $this->assertSame('hace 3 días', $resena['cuando']);

        GoogleBusinessReview::query()->delete();
        $this->copied('Irene, un amor.', '2026-09-29');
        $this->assertSame('Laura M.', $this->today()['autor']);
    }

    public function test_the_quote_is_cut_at_the_last_space_with_an_ellipsis(): void
    {
        $this->assertSame('Los monitores, un diez.', GateReviewOfTheDay::cut("  Los monitores,\n\nun   diez.  "));

        $larga = str_repeat('palabra ', 40);
        $cita = GateReviewOfTheDay::cut($larga);
        $this->assertStringEndsWith('palabra…', $cita);
        $this->assertLessThanOrEqual(GateReviewOfTheDay::CUT + 1, mb_strlen($cita));

        $sinEspacios = str_repeat('a', 300);
        $this->assertSame(str_repeat('a', GateReviewOfTheDay::CUT).'…', GateReviewOfTheDay::cut($sinEspacios));
        $this->assertSame(str_repeat('a', GateReviewOfTheDay::CUT), GateReviewOfTheDay::cut(str_repeat('a', GateReviewOfTheDay::CUT)));
    }

    // ─── Dónde sale: la pantalla ──────────────────────────────────────────────

    /** @return array{0: User, 1: User, 2: string} el empleado, el cliente y su carné */
    private function door(): array
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $holder = User::factory()->create(['email_verified_at' => now(), 'waiver_accepted_at' => now()]);
        $holder->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return [$staff, $holder, (string) app(CustomerCards::class)->ensureFor($holder)->plainToken()];
    }

    public function test_with_the_field_empty_the_review_takes_the_place_of_the_reader(): void
    {
        [$staff] = $this->door();
        $copiada = $this->copied('Los monitores, un diez: Irene estuvo pendiente de los peques toda la tarde.', '2026-09-29');

        Livewire::actingAs($staff)->test(ValidarRegistro::class)
            ->assertSeeHtml('data-gate-review="copiada-'.$copiada->id.'"')
            ->assertSeeHtml('aria-label="Lo que dicen de vosotros."')
            ->assertSee('«Los monitores, un diez: Irene estuvo pendiente de los peques toda la tarde.»')
            ->assertSee('Laura M., en Google · hace 3 días.')
            // La marca OFICIAL de Google (`#780`): su fichero, con «Google» de texto alternativo.
            ->assertSeeHtml('src="'.asset('images/providers/google-logo.svg').'" alt="Google"')
            ->assertDontSeeHtml('ppu-vacio__ico');

        $copiada->update(['is_active' => false]);
        Livewire::actingAs($staff)->test(ValidarRegistro::class)
            ->assertDontSeeHtml('data-gate-review=')
            ->assertSeeHtml('ppu-vacio__ico');
    }

    /** Con una ficha, la reseña va SOLO en el velo —sin región: el velo es un botón—; con un veredicto sin ficha, no va. */
    public function test_with_a_profile_the_review_only_goes_in_the_veil_and_never_with_a_bare_verdict(): void
    {
        [$staff, , $card] = $this->door();
        $this->copied('Los monitores, un diez.', '2026-09-29');

        $html = Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', $card)->call('search')->html();
        $this->assertSame(1, substr_count($html, 'data-gate-review='), 'una sola vez');
        $this->assertGreaterThan(strpos($html, 'data-gate-veil'), strpos($html, 'data-gate-review='), 'dentro del velo');
        $this->assertStringNotContainsString('aria-label="Lo que dicen de vosotros."', $html);

        Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', 'nadie@ejemplo.test')->call('search')
            ->assertDontSeeHtml('data-gate-review=');
    }

    /** El presupuesto: UNA consulta si el Perfil da la reseña y DOS si hay que bajar a las copiadas; nunca una por reseña. */
    public function test_the_review_costs_one_or_two_queries(): void
    {
        foreach (['2026-09-27', '2026-09-28', '2026-09-29'] as $dia) {
            $this->copied('Los monitores, un diez.', $dia);
            $this->profile('Todo perfecto.', $dia.' 12:00:00');
        }

        $contar = function (): int {
            Setting::flushMemo();
            Setting::value(ReviewKeywords::KEY);
            DB::enableQueryLog();
            DB::flushQueryLog();
            app(GateReviewOfTheDay::class)->forGate(0);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $this->assertSame(2, $contar(), 'el Perfil no da nada: baja a las copiadas');
        GoogleBusinessReview::query()->update(['comment' => 'Los monitores, de diez.']);
        $this->assertSame(1, $contar(), 'el Perfil la da: no se mira más');
    }
}
