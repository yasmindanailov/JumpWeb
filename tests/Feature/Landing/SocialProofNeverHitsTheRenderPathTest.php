<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Services\GoogleSocialProof;
use App\Domain\Content\Services\SocialProofRefresh;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * **La portada NUNCA llama a Google, y las cinco salidas de la cascada** (`DECISIONES #491`,
 * `specs/google-reviews.md` §6·1 y §6·3).
 *
 * ❗❗❗ **El primer caso PROHÍBE EL MECANISMO, no comprueba un resultado.** El cliente HTTP se falsea
 * para **explotar si alguien lo llama**, y la portada tiene que seguir dando 200. Si mañana alguien
 * mete un `Cache::remember` en el camino del render —que es lo natural y lo que uno escribiría sin
 * pensar—, esto se pone rojo. `PERF-02` es la razón: la latencia de un tercero en el camino crítico
 * de la portada es peor que las ~1.900 consultas que aquella invariante existe para evitar.
 *
 * ⚠️⚠️ **Y lleva su GUARDA-DE-LA-GUARDA**, que la spec pide expresamente: un caso que comprueba que
 * el falso SÍ explota cuando se le llama. Sin él, un `Http::fake()` mal montado dejaría el primer
 * caso **en verde sin mirar nada**.
 *
 * ⚠️ **El JSON de los casos tiene la forma de una respuesta REAL de Places**, capturada el
 * 2026-09-10 contra el sitio del parque (la salida está en `DECISIONES #491`). Lo único que se
 * cambia es `userRatingCount`, porque el sitio real tiene **una** reseña y hace falta cruzar el
 * umbral para ejercitar el camino feliz. Inventarse la forma es cómo se prueba un parseo contra un
 * mundo que no existe.
 */
class SocialProofNeverHitsTheRenderPathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
        Cache::forget(GoogleSocialProof::CACHE_KEY);
        config()->set('services.google_places.key', 'clave-de-prueba');
        Setting::query()->updateOrCreate(
            ['key' => GoogleSocialProof::PLACE_ID_SETTING],
            ['value' => 'ChIJ5a3Lti7nZA0RuFby_cNNPqE', 'group' => 'general'],
        );
        Setting::flushMemo();
    }

    /** La forma REAL de Places, con `userRatingCount` subido para cruzar el umbral. */
    private function respuestaDeGoogle(int $count = 42, float $rating = 4.8): array
    {
        return [
            'id' => 'ChIJ5a3Lti7nZA0RuFby_cNNPqE',
            'displayName' => ['text' => 'Play Jump Park'],
            'rating' => $rating,
            'userRatingCount' => $count,
            'googleMapsUri' => 'https://maps.google.com/?cid=11618809592836937400',
            'reviews' => [[
                'name' => 'places/X/reviews/Y',
                'rating' => 5,
                'text' => ['text' => 'Excellent playground! Very clean, safe, and fun.', 'languageCode' => 'en-US'],
                'originalText' => ['text' => 'Excellent playground!', 'languageCode' => 'en-US'],
                'relativePublishTimeDescription' => 'hace una semana',
                'publishTime' => '2026-09-03T10:00:00Z',
                'googleMapsUri' => 'https://www.google.com/maps/reviews/data=!4m6',
                'authorAttribution' => [
                    'displayName' => 'anna',
                    'uri' => 'https://www.google.com/maps/contrib/106042760',
                    'photoUri' => 'https://lh3.googleusercontent.com/a/ACg8ocK',
                ],
            ]],
        ];
    }

    private function conOpinionPropia(): void
    {
        Testimonial::query()->create([
            'text' => ['es' => 'Nuestra propia opinión.'], 'author' => 'Vecina', 'position' => 1, 'is_active' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  1 · El mecanismo prohibido
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Con el cliente HTTP puesto para explotar, la portada sigue dando 200 y pinta la sección.**
     */
    public function test_la_portada_no_llama_a_google_ni_con_la_cache_vacia(): void
    {
        $this->conOpinionPropia();
        Http::fake(function (): void {
            throw new \RuntimeException('La portada ha llamado a un tercero en el camino del render.');
        });

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<section id="reviews"', (string) $html);
    }

    /**
     * **LA GUARDA DE LA GUARDA**: el falso SÍ explota si alguien lo llama.
     *
     * ⚠️ Sin este caso, un `Http::fake()` mal montado dejaría el de arriba verde sin mirar nada — y
     * un test que pasa por no estar comprobando nada es peor que no tenerlo.
     */
    public function test_el_falso_explota_de_verdad_cuando_se_le_llama(): void
    {
        Http::fake(function (): void {
            throw new \RuntimeException('boom');
        });

        $this->expectException(\RuntimeException::class);
        Http::get('https://places.googleapis.com/v1/places/x');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  2 · Las cinco salidas del refresco
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_sin_configurar_no_se_llama_a_nadie(): void
    {
        config()->set('services.google_places.key', '');
        Http::fake(function (): void {
            throw new \RuntimeException('no debería llamarse sin clave');
        });

        $this->assertSame(SocialProofRefresh::NOT_CONFIGURED, app(GoogleSocialProof::class)->refresh()->outcome);
    }

    public function test_con_google_caido_la_salida_es_inalcanzable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $r = app(GoogleSocialProof::class)->refresh();

        $this->assertSame(SocialProofRefresh::UNREACHABLE, $r->outcome);
        $this->assertNull(Cache::get(GoogleSocialProof::CACHE_KEY));
    }

    /**
     * 403 (clave o API), 429 (cuota) y 5xx son la MISMA salida, y el código viaja: es lo que dice a
     * qué pestaña de la consola hay que ir.
     */
    public function test_cuando_google_rechaza_la_salida_lleva_el_codigo(): void
    {
        // ⚠️⚠️ **`Http::fake()` ACUMULA stubs y gana el PRIMERO** (la trampa de `#347`): con un
        // `Http::fake(...)` dentro del bucle, las tres vueltas recibían el 403 y el caso fallaba
        // diciendo «403 no es 429» — parecía un defecto del producto y era el arnés. La secuencia
        // devuelve una respuesta distinta por llamada, que es lo que este caso necesita.
        Http::fakeSequence('places.googleapis.com/*')
            ->push(['error' => ['message' => 'x']], 403)
            ->push(['error' => ['message' => 'x']], 429)
            ->push(['error' => ['message' => 'x']], 500);

        foreach ([403, 429, 500] as $status) {
            $r = app(GoogleSocialProof::class)->refresh();

            $this->assertSame(SocialProofRefresh::REJECTED, $r->outcome, "HTTP {$status}");
            $this->assertSame($status, $r->status);
        }
    }

    /**
     * **Por debajo del umbral, Google no se usa en absoluto** — y la caché se OLVIDA, para que una
     * cifra de antes no sobreviva a una caída por debajo.
     */
    public function test_por_debajo_del_umbral_no_hay_media_publicable(): void
    {
        Cache::put(GoogleSocialProof::CACHE_KEY, ['value' => 4.8, 'count' => 99, 'url' => null, 'reviews' => []], 600);

        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle(count: GoogleSocialProof::MIN_REVIEWS - 1))]);

        $r = app(GoogleSocialProof::class)->refresh();

        $this->assertSame(SocialProofRefresh::BELOW_THRESHOLD, $r->outcome);
        $this->assertNull(Cache::get(GoogleSocialProof::CACHE_KEY), 'la caché conserva una cifra que ya no es publicable');
    }

    public function test_con_datos_suficientes_queda_en_la_cache_corta(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);

        $r = app(GoogleSocialProof::class)->refresh();

        $this->assertSame(SocialProofRefresh::CACHED, $r->outcome);
        $this->assertSame(1, $r->reviews);

        $rating = app(GoogleSocialProof::class)->rating();
        $this->assertNotNull($rating);
        $this->assertSame(4.8, $rating->value);
        $this->assertSame(42, $rating->count);
        $this->assertSame(TestimonialData::SOURCE_GOOGLE, $rating->source);
    }

    /**
     * **Una URL de tercero que no sea `http(s)` no llega al DOM** (`SEC-07`).
     *
     * ⚠️ Estas URL vienen de fuera y acaban en un `href` y en un `src`. Se sanean **donde nace el
     * dato** y no en la plantilla: así la siguiente superficie que las consuma —la API, un correo—
     * recibe lo mismo sin tener que acordarse.
     */
    public function test_una_url_de_tercero_con_esquema_raro_no_llega_al_dom(): void
    {
        $json = $this->respuestaDeGoogle();
        $json['reviews'][0]['googleMapsUri'] = 'javascript:alert(1)';
        $json['reviews'][0]['authorAttribution']['photoUri'] = 'data:text/html;base64,PHN2Zz4=';
        $json['googleMapsUri'] = 'javascript:alert(2)';

        Http::fake(['places.googleapis.com/*' => Http::response($json)]);
        app(GoogleSocialProof::class)->refresh();

        $op = app(GoogleSocialProof::class)->testimonials()->first();

        $this->assertNull($op->url, 'un `javascript:` de un tercero llega al `href`');
        $this->assertNull($op->avatarUrl, 'un `data:` de un tercero llega al `src`');
        $this->assertNull(app(GoogleSocialProof::class)->rating()?->url);
    }

    /**
     * **Una reseña SIN AUTOR no se publica.** R3 exige acreditar al autor, y una anónima incumple
     * la atribución obligatoria: es preferible enseñar una menos.
     */
    public function test_una_resena_sin_autor_no_se_publica(): void
    {
        $json = $this->respuestaDeGoogle();
        $json['reviews'][0]['authorAttribution']['displayName'] = '   ';

        Http::fake(['places.googleapis.com/*' => Http::response($json)]);
        $r = app(GoogleSocialProof::class)->refresh();

        $this->assertSame(SocialProofRefresh::CACHED, $r->outcome, 'la cifra sigue siendo publicable');
        $this->assertSame(0, $r->reviews, 'se publica una reseña sin acreditar a su autor');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  3 · La cascada
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Sin consentimiento no se sirve ni una reseña de Google**, y en su lugar salen las propias.
     *
     * R3 obliga a la foto del autor y cargarla es una petición del visitante a Google (`RGPD-05`);
     * servirlas sin foto incumple R3. No hay término medio.
     */
    public function test_sin_consentimiento_salen_las_opiniones_propias(): void
    {
        $this->conOpinionPropia();
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);
        app(GoogleSocialProof::class)->refresh();

        $opiniones = app(SocialProof::class)->testimonials();

        $this->assertCount(1, $opiniones);
        $this->assertSame(TestimonialData::SOURCE_CMS, $opiniones->first()->source);
        $this->assertNull($opiniones->first()->avatarUrl);
    }

    /**
     * ❗❗❗ **PERO LA CIFRA SÍ SE SIRVE SIN CONSENTIMIENTO**, y es la resolución de una ambigüedad que
     * la spec tenía escrita sin argumentar: la trae nuestro servidor —el visitante no habla con
     * Google—, no lleva autor ni foto, y una media de un negocio no es dato personal.
     */
    public function test_la_cifra_no_pide_consentimiento(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);
        app(GoogleSocialProof::class)->refresh();

        $this->assertNotNull(app(SocialProof::class)->rating(), 'la cifra desaparece sin consentimiento y no debería');
    }

    /**
     * ❗❗❗ **LA ENTRADILLA SIGUE A LA FUENTE DE LAS OPINIONES, NO A LA DE LA CHAPA.**
     *
     * Y las dos pueden no coincidir: la cifra se sirve sin consentimiento y las reseñas no, así que
     * **el caso más frecuente es exactamente éste** — chapa de Google encima de opiniones propias.
     * Atada a la chapa, la sección decía *«no las elegimos nosotros»* **sobre una opinión que sí
     * elegimos**. ⚠️ Lo encontró la CAPTURA, no la suite: es un texto correcto en un sitio correcto
     * diciendo algo falso, y ninguna aserción de marcado lo veía.
     */
    public function test_la_entradilla_sigue_a_las_opiniones_y_no_a_la_chapa(): void
    {
        $this->conOpinionPropia();
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);
        app(GoogleSocialProof::class)->refresh();

        // Sin consentimiento: la chapa SÍ, las opiniones son las propias.
        $html = (string) $this->get('/')->assertOk()->getContent();
        preg_match('#<section id="reviews".*?</section>#s', $html, $m);
        $seccion = $m[0] ?? '';

        $this->assertStringContainsString('rev-score__val', $seccion, 'el caso se quedó sin chapa: no mediría el cruce');
        $this->assertStringContainsString(__('landing.reviews.lede_own'), $seccion);
        $this->assertStringNotContainsString(
            __('landing.reviews.lede_google'),
            $seccion,
            'La sección dice «no las elegimos nosotros» sobre una opinión propia, que sí elegimos.',
        );
    }

    /** Con consentimiento, las de Google ganan — y llegan con su atribución completa. */
    public function test_con_consentimiento_salen_las_de_google_con_su_atribucion(): void
    {
        $this->conOpinionPropia();
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);
        app(GoogleSocialProof::class)->refresh();

        $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, CookieConsent::encode(['maps' => true, 'social' => false]));
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match('#<section id="reviews".*?</section>#s', $html, $m);
        $seccion = $m[0] ?? '';

        $this->assertStringContainsString('anna', $seccion, 'la reseña llega sin su autor: R3 lo prohíbe');
        $this->assertStringContainsString('lh3.googleusercontent.com', $seccion, 'la reseña llega sin la foto del autor');
        $this->assertStringContainsString('maps/reviews', $seccion, 'falta la salida a la reseña entera');
        $this->assertStringNotContainsString('Nuestra propia opinión', $seccion, 'se mezclan las dos fuentes');
    }

    /**
     * **Sin consentimiento Y sin opiniones propias → 200 y la sección AUSENTE**, sin hueco ni
     * esqueleto. Es la sexta salida, la que se olvida.
     */
    public function test_sin_consentimiento_y_sin_opiniones_propias_la_seccion_desaparece(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);
        app(GoogleSocialProof::class)->refresh();

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('rev__card', $html, 'queda un hueco donde no hay nada que enseñar');
    }
}
