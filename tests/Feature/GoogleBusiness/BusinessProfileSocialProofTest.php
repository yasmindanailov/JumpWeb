<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\ReviewSelection;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\GoogleBusinessReviewSummary;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Services\BusinessProfileSocialProof;
use App\Domain\Content\Services\CmsSocialProof;
use App\Domain\Content\Services\FallingBackSocialProof;
use App\Domain\Content\Services\GoogleReviewFilter;
use App\Domain\Content\Services\GoogleReviewImages;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * **T2·6 · La fuente de la ficha y la cascada de tres**
 * (`docs/specs/google-business-profile.md` §4.3·9 y §4.3·10; `DECISIONES #524`, `#732`).
 *
 * ❗❗ Lo que fija este caso: que lo que la portada recibe salga de **nuestra base** y no de Google,
 * que **la cifra no se filtre aunque las tarjetas sí**, y que la **línea del filtro** viaje en el
 * contrato — sin ella la landing no puede cumplir la Ómnibus aunque quiera.
 */
class BusinessProfileSocialProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(GoogleReviewImages::DISK);
    }

    private function fuente(): BusinessProfileSocialProof
    {
        return app(BusinessProfileSocialProof::class);
    }

    private function resumen(array $overrides = []): GoogleBusinessReviewSummary
    {
        return GoogleBusinessReviewSummary::create(array_merge([
            'average_rating' => 4.8,
            'total_review_count' => 320,
            'maps_uri' => 'https://maps.google.com/?cid=1',
            'new_review_uri' => 'https://search.google.com/local/writereview?placeid=ChIJ',
            'fetched_at' => now(),
        ], $overrides));
    }

    /** @param array<string,mixed> $overrides */
    private function resena(string $id, array $overrides = []): GoogleBusinessReview
    {
        return GoogleBusinessReview::create(array_merge([
            'review_name' => 'accounts/1/locations/9/reviews/'.$id,
            'author_name' => 'Marta R.',
            'star_rating' => 5,
            'comment' => 'Los niños salieron encantados.',
            'review_created_at' => now()->subMonth(),
            'fetched_at' => now(),
        ], $overrides));
    }

    // ─────────── La cifra (§4.3·10) ───────────

    public function test_la_cifra_sale_de_google_con_su_fecha(): void
    {
        $this->travelTo('2026-09-21 08:00:00');
        $this->resumen();

        $cifra = $this->fuente()->rating();

        $this->assertSame(4.8, $cifra->value);
        $this->assertSame(320, $cifra->count);
        $this->assertSame(TestimonialData::SOURCE_GOOGLE, $cifra->source);
        // §4.3·10: «con *a fecha de …*». La trajo una pasada diaria, no está viva.
        $this->assertSame('2026-09-21 08:00:00', $cifra->asOf->format('Y-m-d H:i:s'));
    }

    /**
     * **La ficha se atribuye con la PALABRA, no con el logotipo de Maps** (`#734`, §4.3·10). Las dos
     * fuentes dicen `SOURCE_GOOGLE`, así que la marca no se puede deducir: la fuente la declara.
     */
    public function test_la_ficha_declara_que_se_atribuye_con_la_palabra(): void
    {
        $this->resumen();
        $this->resena('1');

        $this->assertSame(TestimonialData::ATTRIBUTION_GOOGLE_WORD, $this->fuente()->rating()->attribution);
        $this->assertSame(TestimonialData::ATTRIBUTION_GOOGLE_WORD, $this->fuente()->testimonials()->first()->attribution);
    }

    public function test_sin_pasada_reciente_no_hay_cifra(): void
    {
        $this->travelTo('2026-09-21 08:00:00');
        $this->resumen(['fetched_at' => now()->subDays(GoogleBusinessReviewSummary::FRESH_DAYS)->subSecond()]);

        // Una media que lleva días sin comprobarse es una afirmación falsa sobre un tercero.
        $this->assertNull($this->fuente()->rating());
    }

    public function test_la_cifra_no_se_filtra_aunque_las_tarjetas_si(): void
    {
        Setting::updateOrCreate(['key' => GoogleReviewFilter::MIN_STARS_KEY], ['value' => '4', 'group' => 'reviews']);
        Setting::flushMemo();
        $this->resumen(['average_rating' => 4.1, 'total_review_count' => 320]);
        $this->resena('r1', ['star_rating' => 5]);

        // ❗❗ Hay UNA tarjeta de cinco estrellas y la cifra dice 4,1 sobre 320: viene de Google
        // contada sobre TODAS. Que bajara al filtrar sería el parque cambiando la nota de su ficha.
        $this->assertSame(4.1, $this->fuente()->rating()->value);
        $this->assertSame(320, $this->fuente()->rating()->count);
        $this->assertCount(1, $this->fuente()->testimonials());
    }

    public function test_hay_cifra_aunque_el_filtro_no_deje_ninguna_tarjeta(): void
    {
        $this->resumen();

        // §4.3·10, literal: «si deja 0, las opiniones propias, **y la media se sigue enseñando**».
        $this->assertCount(0, $this->fuente()->testimonials());
        $this->assertNotNull($this->fuente()->rating());
    }

    public function test_el_umbral_del_owner_se_conserva(): void
    {
        $this->resumen(['total_review_count' => 0, 'average_rating' => null]);
        $this->assertNull($this->fuente()->rating());

        // `#494`, definitivo: basta UNA. La doc de `google-reviews.md` dice 10 y miente.
        $this->assertSame(1, BusinessProfileSocialProof::MIN_REVIEWS);
    }

    // ─────────── Las tarjetas (§4.3·9 y §4.3·10) ───────────

    public function test_la_tarjeta_trae_la_respuesta_del_parque_y_sus_fotos(): void
    {
        Storage::disk(GoogleReviewImages::DISK)->put($foto = hash('sha256', 'f').'.png', 'x');
        $this->resumen();
        $this->resena('r1', [
            'reply_comment' => '¡Gracias, Marta!',
            'author_photo_path' => $foto,
            'photos' => [$foto],
        ]);

        $op = $this->fuente()->testimonials()->first();

        $this->assertSame('¡Gracias, Marta!', $op->reply);
        // ❗❗ **Rutas NUESTRAS**: toda la T2 existe para que la portada no le pida nada a Google.
        $this->assertSame([route('resenas.foto', ['fichero' => $foto])], $op->photos);
        $this->assertSame(route('resenas.foto', ['fichero' => $foto]), $op->avatarUrl);
        $this->assertStringNotContainsString('googleusercontent', $op->avatarUrl);
    }

    public function test_sin_una_pasada_que_la_confirme_la_tarjeta_sale_anonima(): void
    {
        // ⚠️ **La reseña TIENE foto a propósito**: sin ella, leer la columna a pelo y leerla por
        // `publishablePhotoPath()` dan lo mismo —`null`— y la mutación de la guarda sobrevivía. Lo
        // destapó el arnés.
        Storage::disk(GoogleReviewImages::DISK)->put($foto = hash('sha256', 'caduca').'.png', 'x');
        $this->resumen();
        $this->resena('r1', [
            'author_photo_path' => $foto,
            'fetched_at' => now()->subDays(GoogleBusinessReview::IDENTIFIED_DAYS + 1),
        ]);

        $op = $this->fuente()->testimonials()->first();

        // El plazo corto del §4.3·4 llega hasta aquí: si la fuente leyera la columna a pelo,
        // publicaría el nombre de alguien cuya reseña puede llevar días borrada de Google.
        $this->assertSame('', $op->author);
        $this->assertNull($op->avatarUrl);
        $this->assertTrue($op->anonymous);
    }

    public function test_una_resena_pasada_de_plazo_no_se_ensena(): void
    {
        $this->resumen();
        $this->resena('r1', ['fetched_at' => now()->subDays(GoogleBusinessReview::FRESH_DAYS + 1)]);

        $this->assertCount(0, $this->fuente()->testimonials());
    }

    public function test_se_ensenan_las_mas_recientes_y_solo_seis(): void
    {
        $this->resumen();
        for ($i = 1; $i <= 9; $i++) {
            $this->resena('r'.$i, ['review_created_at' => now()->subDays(20 - $i)]);
        }

        $nombres = $this->fuente()->testimonials()->count();

        $this->assertSame(BusinessProfileSocialProof::SHOWN, $nombres);
        $this->assertSame(6, BusinessProfileSocialProof::SHOWN);
    }

    // ─────────── La línea del filtro (§4.3·10 · Ómnibus) ───────────

    public function test_la_seleccion_dice_el_minimo_y_los_dos_enlaces(): void
    {
        Setting::updateOrCreate(['key' => GoogleReviewFilter::MIN_STARS_KEY], ['value' => '4', 'group' => 'reviews']);
        Setting::flushMemo();
        $this->resumen();
        $this->resena('r1');

        $seleccion = $this->fuente()->selection();

        // ❗❗❗ Sin esto la landing NO PUEDE cumplir la Ómnibus aunque quiera: no sabría qué decir.
        $this->assertSame(4, $seleccion->minStars);
        $this->assertSame('https://maps.google.com/?cid=1', $seleccion->allReviewsUrl);
        $this->assertSame('https://search.google.com/local/writereview?placeid=ChIJ', $seleccion->writeReviewUrl);
    }

    public function test_sin_tarjetas_no_hay_nada_que_declarar(): void
    {
        $this->resumen();

        // Con la sección enseñando opiniones propias, avisar de un filtro de Google sería avisar de
        // algo que no se está aplicando a lo que se ve.
        $this->assertNull($this->fuente()->selection());
    }

    public function test_el_minimo_que_se_declara_es_el_efectivo(): void
    {
        Setting::updateOrCreate(['key' => GoogleReviewFilter::MIN_STARS_KEY], ['value' => '9', 'group' => 'reviews']);
        Setting::flushMemo();
        $this->resumen();
        $this->resena('r1');

        // ⚠️ Si alguien pone 9 en el panel, lo que se aplica es 5 — y lo que la línea tiene que
        // decir es lo que se aplica, no lo que hay escrito en la casilla.
        $this->assertSame(5, $this->fuente()->selection()->minStars);
    }

    // ─────────── El consentimiento (§4.3·9) ───────────

    public function test_estas_resenas_no_necesitan_consentimiento(): void
    {
        // ❗❗❗ Es la razón de ser de la T2 entera: se sirven enteras —imagen incluida— desde nuestro
        // servidor, así que el navegador no le pide nada a Google y no hay nada que consentir.
        $this->assertFalse($this->fuente()->reviewsNeedConsent());
        $this->assertFalse($this->fuente()->reviewsAwaitConsent());
        // 📜 El control era Places, que sí pedía permiso; se retiró en `#771`. El mecanismo, con una fuente de prueba
        // que lo pide, vive en `ReviewsCascadeConsentTest`.
    }

    // ─────────── La cascada (§4.3·9): la ficha delante de las opiniones del panel ───────────

    private function cascada(bool $permitido = true): FallingBackSocialProof
    {
        return new FallingBackSocialProof(
            [$this->fuente(), app(CmsSocialProof::class)],
            static fn (): bool => $permitido,
        );
    }

    private function opinionPropia(): void
    {
        Testimonial::create([
            'text' => ['es' => 'Repetiremos.'], 'author' => 'Una familia',
            'rating' => 5, 'is_active' => true, 'position' => 1, 'published_at' => now()->subMonth(),
        ]);
    }

    public function test_la_ficha_va_delante_de_las_opiniones_propias(): void
    {
        $this->resumen();
        $this->resena('r1');
        $this->opinionPropia();

        $op = $this->cascada()->testimonials();

        $this->assertCount(1, $op);
        $this->assertSame(TestimonialData::SOURCE_GOOGLE, $op->first()->source);
    }

    public function test_sin_ficha_la_cascada_cae_a_las_propias(): void
    {
        // El control de arriba: sin reseñas de la ficha, responde el respaldo y no un hueco.
        $this->opinionPropia();

        $op = $this->cascada()->testimonials();

        $this->assertCount(1, $op);
        $this->assertSame(TestimonialData::SOURCE_CMS, $op->first()->source);
    }

    public function test_la_ficha_responde_aunque_el_visitante_no_acepte_terceros(): void
    {
        $this->resumen();
        $this->resena('r1');
        $this->opinionPropia();

        // ❗❗❗ Lo que la T2 vino a arreglar: **sin consentimiento** la sección ya no cae a las
        // propias, porque estas reseñas no le piden nada a Google.
        $cascada = $this->cascada(permitido: false);

        $this->assertSame(TestimonialData::SOURCE_GOOGLE, $cascada->testimonials()->first()->source);
        $this->assertFalse($cascada->reviewsAwaitConsent());
    }

    public function test_la_seleccion_sale_de_la_fuente_que_responde(): void
    {
        $this->resumen();
        $this->opinionPropia();

        // Sin tarjetas de la ficha responden las propias, y entonces **no se declara filtro**: la
        // línea avisaría de algo que no se está aplicando a lo que se ve.
        $this->assertSame(TestimonialData::SOURCE_CMS, $this->cascada()->testimonials()->first()->source);
        $this->assertNull($this->cascada()->selection());
    }

    public function test_una_fuente_que_declara_filtro_pero_no_responde_no_pinta_su_linea(): void
    {
        // ❗❗❗ **Este caso lo pidió el arnés**: con las tres fuentes reales, «tener selección» y
        // «responder» coinciden siempre —la ficha declara filtro exactamente cuando tiene tarjetas—,
        // así que la regla no se podía poner en rojo. Con un doble sí, y la regla merece quedar
        // fijada: **declarar un filtro sobre contenido que no se está filtrando** convierte una
        // sección honesta en una que avisa de algo falso, y eso es justo lo que la Ómnibus persigue.
        $this->opinionPropia();

        $conFiltroPeroSinNada = new class implements SocialProof
        {
            public function rating(): ?Rating
            {
                return null;
            }

            public function testimonials(): Collection
            {
                return collect();
            }

            public function reviewsAwaitConsent(): bool
            {
                return false;
            }

            public function reviewsNeedConsent(): bool
            {
                return false;
            }

            public function selection(): ?ReviewSelection
            {
                return new ReviewSelection(4, null, null);
            }
        };

        $cascada = new FallingBackSocialProof([$conFiltroPeroSinNada, app(CmsSocialProof::class)], static fn (): bool => true);

        $this->assertSame(TestimonialData::SOURCE_CMS, $cascada->testimonials()->first()->source);
        $this->assertNull($cascada->selection(), 'se está declarando un filtro sobre opiniones que no se filtran');
    }

    public function test_la_cifra_de_la_ficha_se_ensena_aunque_respondan_las_propias(): void
    {
        $this->resumen();
        $this->opinionPropia();

        // §4.3·10: la media se sigue enseñando aunque el filtro no deje tarjetas. La cifra y las
        // opiniones no se gobiernan igual, y la cascada las recorre por separado.
        $cascada = $this->cascada();
        $this->assertSame(TestimonialData::SOURCE_CMS, $cascada->testimonials()->first()->source);
        $this->assertSame(4.8, $cascada->rating()->value);
    }

    public function test_el_contrato_lo_resuelve_una_sola_instancia_por_peticion(): void
    {
        // ⚠️ `scoped` (§4.3·9): la portada pregunta por las opiniones, la cifra, el permiso y la
        // selección. Con `bind` serían cuatro objetos y cuatro recorridos de la cascada para pintar
        // una sección (`PERF-02`).
        $this->assertSame(app(SocialProof::class), app(SocialProof::class));
    }
}
