<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\ReviewSelection;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Services\CmsSocialProof;
use App\Domain\Content\Services\FallingBackSocialProof;
use App\Domain\Identity\Services\CookieConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

/**
 * **La portada nunca llama a un tercero, y la cascada con una fuente que PIDE PERMISO** (`DECISIONES #491`, `#592`,
 * `#732`; re-apuntado en `#771`).
 *
 * ▶ **De dónde viene**: era `SocialProofNeverHitsTheRenderPathTest`, cuyos casos sembraban la caché de Places. Al
 * retirar Places (`#771`, `CONVENCIONES §3.quater`) murió lo que tenía a Places por sujeto —su refresco, su caché, su
 * umbral, su lectura del JSON— y se re-apuntó lo que prueba MECANISMOS que siguen vivos: que el camino del render no
 * envía nada, y que la cascada sabe esperar el permiso del visitante cuando una fuente lo necesita. Hoy ninguna fuente
 * real lo necesita (el Perfil de Empresa y las opiniones del panel se sirven desde casa); el mecanismo se queda para
 * la que lo necesite, y por eso se prueba con una fuente de PRUEBA que pide permiso.
 */
class ReviewsCascadeConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
    }

    private function conOpinionPropia(): void
    {
        Testimonial::query()->create([
            'text' => ['es' => 'Nuestra propia opinión.'], 'author' => 'Vecina', 'position' => 1, 'is_active' => true,
        ]);
    }

    /**
     * La cascada del composition root, con una fuente de prueba DELANTE que pide permiso —como pedía Places: la foto
     * del autor la cargaría el visitante desde un tercero— y las opiniones del panel detrás. El permiso se lee igual que
     * en `AppServiceProvider`: la categoría `maps` del consentimiento.
     */
    private function conFuenteQuePidePermiso(bool $conResenas = true): void
    {
        $fuente = new class($conResenas) implements SocialProof
        {
            public function __construct(private bool $conResenas) {}

            public function rating(): ?Rating
            {
                return new Rating(value: 4.8, count: 42, url: 'https://maps.google.com/?cid=1', source: TestimonialData::SOURCE_GOOGLE);
            }

            public function testimonials(): Collection
            {
                return ! $this->conResenas ? collect() : collect([new TestimonialData(
                    text: 'Muy limpio y seguro.', author: 'anna', rating: 5, when: 'hace una semana',
                    url: 'https://www.google.com/maps/reviews/data=!4m6', source: TestimonialData::SOURCE_GOOGLE,
                    avatarUrl: 'https://lh3.googleusercontent.com/a/ACg8ocK', authorUrl: 'https://www.google.com/maps/contrib/106042760',
                    // Escrita en español: fuera de la versión española, la tarjeta lo tiene que decir (`lang`).
                    language: app()->getLocale() === 'es' ? null : 'es',
                )]);
            }

            public function reviewsAwaitConsent(): bool
            {
                return false;
            }

            public function reviewsNeedConsent(): bool
            {
                return true;
            }

            public function selection(): ?ReviewSelection
            {
                return null;
            }
        };

        $this->app->scoped(SocialProof::class, fn (): SocialProof => new FallingBackSocialProof(
            [$fuente, $this->app->make(CmsSocialProof::class)],
            static fn (): bool => (bool) (CookieConsent::state(request())['maps'] ?? false),
        ));
    }

    private function conPermiso(): void
    {
        $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, CookieConsent::encode(['maps' => true, 'social' => false]));
    }

    /** El HTML de la sección 06, o `''` si no se pinta. */
    private function seccionDeResenas(string $html): string
    {
        return preg_match('#<section id="reviews".*?</section>#s', $html, $m) === 1 ? $m[0] : '';
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  1 · El mecanismo prohibido
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La portada NO envía ni una petición a nadie.** Se asevera que no se ENVIÓ NADA —con un falso que RESPONDE,
     * así la llamada quedaría registrada—, no que la página aguante (la lección del arnés de `#491`).
     */
    public function test_la_portada_no_llama_a_ningun_tercero(): void
    {
        $this->conOpinionPropia();
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->assertStringContainsString('<section id="reviews"', (string) $this->get('/')->assertOk()->getContent());
        Http::assertNothingSent();
    }

    /** **La guarda de la guarda**: `assertNothingSent()` falla de verdad cuando algo se envía. */
    public function test_la_asercion_de_la_guarda_falla_cuando_si_se_envia(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        Http::get('https://example.test/x');

        $this->expectException(AssertionFailedError::class);
        Http::assertNothingSent();
    }

    /** ▶ La cascada real, tras `#771`: primero el Perfil de Empresa y después las del panel; nadie pide permiso. */
    public function test_la_cascada_real_no_pide_permiso_a_nadie(): void
    {
        $this->conOpinionPropia();

        $this->assertFalse(app(SocialProof::class)->reviewsNeedConsent());
        $this->assertFalse(app(SocialProof::class)->reviewsAwaitConsent());
        $this->assertSame(TestimonialData::SOURCE_CMS, app(SocialProof::class)->testimonials()->first()?->source);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  2 · La cascada con una fuente que pide permiso
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_sin_consentimiento_salen_las_opiniones_propias(): void
    {
        $this->conOpinionPropia();
        $this->conFuenteQuePidePermiso();

        $opiniones = app(SocialProof::class)->testimonials();

        $this->assertCount(1, $opiniones);
        $this->assertSame(TestimonialData::SOURCE_CMS, $opiniones->first()->source);
        $this->assertNull($opiniones->first()->avatarUrl);
    }

    /** ❗ **La cifra SÍ se sirve sin consentimiento**: la trae nuestro servidor, sin autor ni foto. */
    public function test_la_cifra_no_pide_consentimiento(): void
    {
        $this->conFuenteQuePidePermiso();

        $this->assertNotNull(app(SocialProof::class)->rating(), 'la cifra desaparece sin consentimiento y no debería');
    }

    /** ❗ **La entradilla sigue a la fuente de las OPINIONES, no a la de la chapa** (lo encontró una captura). */
    public function test_la_entradilla_sigue_a_las_opiniones_y_no_a_la_chapa(): void
    {
        $this->conOpinionPropia();
        $this->conFuenteQuePidePermiso();

        $seccion = $this->seccionDeResenas((string) $this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('rev-score__val', $seccion, 'el caso se quedó sin chapa: no mediría el cruce');
        $this->assertStringContainsString(__('landing.reviews.lede_own'), $seccion);
        $this->assertStringNotContainsString(__('landing.reviews.lede_google'), $seccion,
            'La sección dice «no las elegimos nosotros» sobre una opinión propia, que sí elegimos.');
    }

    /** Con consentimiento, las de la fuente ganan — y llegan con su atribución completa. */
    public function test_con_consentimiento_salen_las_de_google_con_su_atribucion(): void
    {
        $this->conOpinionPropia();
        $this->conFuenteQuePidePermiso();
        $this->conPermiso();

        $seccion = $this->seccionDeResenas((string) $this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('anna', $seccion, 'la reseña llega sin su autor');
        $this->assertStringContainsString('lh3.googleusercontent.com', $seccion, 'la reseña llega sin la foto del autor');
        $this->assertStringContainsString('maps/reviews', $seccion, 'falta la salida a la reseña entera');
        $this->assertStringNotContainsString('Nuestra propia opinión', $seccion, 'se mezclan las dos fuentes');
    }

    /** ❗ **Sin consentimiento ni opiniones propias no desaparece: queda la nota y un aviso** (`#592`). */
    public function test_sin_consentimiento_ni_opiniones_propias_queda_la_nota_y_el_aviso(): void
    {
        $this->conFuenteQuePidePermiso();

        $seccion = $this->seccionDeResenas((string) $this->get('/')->assertOk()->getContent());

        $this->assertNotSame('', $seccion, 'la sección vuelve a desaparecer entera por un permiso');
        $this->assertStringContainsString('rev-score__val', $seccion, 'la nota, que no pide permiso, no sale');
        $this->assertStringContainsString('class="rev__lock"', $seccion, 'no se avisa de que las reseñas esperan el permiso');
        $this->assertStringContainsString(__('landing.reviews.locked_text'), $seccion);
        $this->assertStringNotContainsString('rev__card', $seccion, 'sale una tarjeta sin permiso');
        $this->assertStringNotContainsString('googleusercontent.com', $seccion, 'se pide una foto a un tercero sin permiso (RGPD-05)');
    }

    /** **El aviso abre el panel de cookies y, al conceder, recarga**: las tarjetas las pinta el servidor. */
    public function test_el_aviso_abre_el_panel_y_recarga_al_conceder(): void
    {
        $this->conFuenteQuePidePermiso();

        $seccion = $this->seccionDeResenas((string) $this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('$store.cookies.openPanel()', $seccion, 'el aviso no abre el panel de cookies');
        $this->assertMatchesRegularExpression('/x-on:cookies-updated\.window="[^"]*detail\.maps[^"]*reload\(\)"/', $seccion,
            'al conceder la página no se recarga: el aviso se queda donde deberían salir');
    }

    /** **Con opiniones propias no hay aviso**: se enseñan ellas. */
    public function test_con_opiniones_propias_no_sale_el_aviso(): void
    {
        $this->conOpinionPropia();
        $this->conFuenteQuePidePermiso();

        $seccion = $this->seccionDeResenas((string) $this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('Nuestra propia opinión', $seccion, 'el caso se quedó sin opiniones propias');
        $this->assertStringNotContainsString('rev__lock', $seccion, 'el aviso tapa las opiniones propias que sí se pueden enseñar');
    }

    /** **Con el permiso dado no hay aviso**: salen las reseñas de la fuente. */
    public function test_con_consentimiento_no_sale_el_aviso(): void
    {
        $this->conFuenteQuePidePermiso();
        $this->conPermiso();

        $seccion = $this->seccionDeResenas((string) $this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('rev__card', $seccion, 'con permiso no salen las reseñas');
        $this->assertStringNotContainsString('rev__lock', $seccion, 'con el permiso dado sigue pidiéndolo');
    }

    /** **Solo la cascada sabe que las reseñas esperan el permiso**, y solo con reseñas que lo piden y sin permiso. */
    public function test_solo_la_cascada_dice_que_las_resenas_esperan_el_permiso(): void
    {
        $this->conFuenteQuePidePermiso();

        $this->assertTrue(app(SocialProof::class)->reviewsAwaitConsent(), 'con reseñas que piden permiso y sin él no lo dice');
        $this->assertFalse(app(CmsSocialProof::class)->reviewsAwaitConsent());

        request()->cookies->set(CookieConsent::COOKIE_NAME, CookieConsent::encode(['maps' => true, 'social' => false]));
        $this->forgetScopedInstances();
        $this->assertFalse(app(SocialProof::class)->reviewsAwaitConsent(), 'con el permiso dado sigue diciendo que lo espera');

        request()->cookies->remove(CookieConsent::COOKIE_NAME);
        $this->conFuenteQuePidePermiso(conResenas: false);
        $this->forgetScopedInstances();
        $this->assertFalse(app(SocialProof::class)->reviewsAwaitConsent(), 'sin reseñas que lo pidan anuncia unas que no hay');
    }

    /** **La tarjeta pone `lang` al texto solo fuera de su idioma**: un lector de pantalla lee con la voz de la página. */
    public function test_la_tarjeta_pone_lang_al_texto_solo_fuera_de_su_idioma(): void
    {
        $this->conFuenteQuePidePermiso();

        foreach (['en' => true, 'es' => false] as $idioma => $debeLlevarlo) {
            $html = (string) $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, CookieConsent::encode(['maps' => true, 'social' => false]))
                ->withSession(['locale' => $idioma])
                ->get('/')->assertOk()->getContent();
            $seccion = $this->seccionDeResenas($html);

            $this->assertStringContainsString('Muy limpio y seguro.', $seccion, "la versión «{$idioma}» no pinta la reseña");
            $this->assertSame($debeLlevarlo, preg_match('/<p class="rev__text[^"]*"[^>]*\blang="es"/', $seccion) === 1,
                $debeLlevarlo ? 'La versión inglesa pinta un texto en español sin `lang`.' : 'La versión española declara `lang` sobre su propio idioma.');
        }
    }

    /** Olvida la cascada memorizada de la petición (`scoped`): el caso cambia el permiso a mitad. */
    private function forgetScopedInstances(): void
    {
        $this->app->forgetScopedInstances();
    }
}
