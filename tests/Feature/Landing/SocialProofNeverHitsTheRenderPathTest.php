<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Services\CmsSocialProof;
use App\Domain\Content\Services\GoogleSocialProof;
use App\Domain\Content\Services\SocialProofRefresh;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Platform\Models\Setting;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

/**
 * **La portada NUNCA llama a Google, y las cinco salidas de la cascada** (`DECISIONES #491`,
 * `specs/google-reviews.md` §6·1 y §6·3).
 *
 * ❗❗❗ **El primer caso PROHÍBE EL MECANISMO, no comprueba un resultado.** Si mañana alguien mete un
 * `Cache::remember` en el camino del render —que es lo natural y lo que uno escribiría sin pensar—,
 * esto se pone rojo. `PERF-02` es la razón: la latencia de un tercero en el camino crítico de la
 * portada es peor que las ~1.900 consultas que aquella invariante existe para evitar.
 *
 * ⚠️⚠️ **Y se asevera que no se ENVIÓ NADA, no que la página aguante — la diferencia la encontró el
 * arnés.** La primera versión ponía el cliente HTTP a explotar y comprobaba que `GET /` seguía dando
 * 200; esa mutación **no mordía**, por dos motivos que se tapaban entre sí: `refresh()` se traga las
 * excepciones por diseño —sus cinco salidas son normales— y **un `Http::fake()` que lanza ni
 * siquiera llega a registrar la petición**. *Comprobar que la página no se rompe no es comprobar que
 * no ha llamado a un tercero.*
 *
 * ⚠️⚠️ **Y lleva su GUARDA-DE-LA-GUARDA**, que la spec pide expresamente: un caso que comprueba que
 * `assertNothingSent()` **falla de verdad** cuando algo se envía. Sin él, una aserción que no viera
 * las peticiones dejaría el primer caso **en verde sin mirar nada** — que es exactamente lo que
 * acababa de pasar.
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
        Cache::forget(GoogleSocialProof::cacheKey());
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

    /** Una reseña ESCRITA en español y pedida en español: la forma real de las del parque. */
    private function resenaEscritaEnEspanol(): array
    {
        $json = $this->respuestaDeGoogle();
        $json['reviews'][0]['text'] = ['text' => 'Muy limpio y seguro.', 'languageCode' => 'es'];
        $json['reviews'][0]['originalText'] = ['text' => 'Muy limpio y seguro.', 'languageCode' => 'es'];

        return $json;
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
     * **La portada NO envía ni una petición a Google, ni con la caché vacía.**
     *
     * ⚠️⚠️ **Se asevera que no se ENVIÓ NADA, no que la página no se rompa — y la diferencia la
     * encontró el arnés.** La primera versión ponía el cliente HTTP a EXPLOTAR y comprobaba que
     * `GET /` seguía dando 200; la mutación que mete un `Cache::remember` en el render **no
     * mordía**, por dos motivos que se tapaban entre sí: `refresh()` se traga las excepciones por
     * diseño —sus cinco salidas son normales— y, además, **un `Http::fake()` que lanza no llega a
     * registrar la petición**, así que ni siquiera se podía ver. *Comprobar que la página no se
     * rompe no es comprobar que no ha llamado a un tercero.*
     * ▶ Con un falso que RESPONDE, la llamada queda registrada y `assertNothingSent()` la caza.
     */
    public function test_la_portada_no_llama_a_google_ni_con_la_cache_vacia(): void
    {
        $this->conOpinionPropia();
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<section id="reviews"', (string) $html);
        Http::assertNothingSent();
    }

    /**
     * **LA GUARDA DE LA GUARDA**: `assertNothingSent()` FALLA de verdad cuando algo se envía.
     *
     * ⚠️ Sin este caso, un falso mal montado —o una aserción que no ve las peticiones— dejaría el de
     * arriba **verde sin mirar nada**, y un test que pasa por no estar comprobando nada es peor que
     * no tenerlo. Es exactamente lo que pasó con la versión anterior de este par.
     */
    public function test_la_asercion_de_la_guarda_falla_cuando_si_se_envia(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response(['ok' => true])]);

        Http::get('https://places.googleapis.com/v1/places/x');

        $this->expectException(AssertionFailedError::class);
        Http::assertNothingSent();
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
        $this->assertNull(Cache::get(GoogleSocialProof::cacheKey()));
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
        Cache::put(GoogleSocialProof::cacheKey(), ['value' => 4.8, 'count' => 99, 'url' => null, 'reviews' => []], 600);

        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle(count: GoogleSocialProof::MIN_REVIEWS - 1))]);

        $r = app(GoogleSocialProof::class)->refresh();

        $this->assertSame(SocialProofRefresh::BELOW_THRESHOLD, $r->outcome);
        $this->assertNull(Cache::get(GoogleSocialProof::cacheKey()), 'la caché conserva una cifra que ya no es publicable');
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

    /**
     * ❗❗❗ **SE LE PIDE A GOOGLE UN SOLO IDIOMA —EL DE LA INSTALACIÓN— Y LO LEEN LAS TRES VERSIONES**
     * (`[DECIDIDO owner, 2026-09-13]`, `#591`).
     *
     * ⚠️⚠️ **Este caso CAMBIÓ DE PREMISA y se reescribió** (el precedente de `#324`): hasta `#591`
     * afirmaba lo contrario —el idioma de la página y una caché por idioma—, y eso costaba tres
     * llamadas por pasada, una cadencia de tres horas y una sección que enseñaba Google media hora de
     * cada tres.
     * ⚠️ Lo que NO cambia es la mitad que encontró la reseña REAL: **se le pide un idioma a Google**
     * (sin `languageCode` contestó «a week ago» sobre la página en español).
     * ⚠️ Se refresca con la aplicación en FRANCÉS a propósito: si el idioma saliera de la petición en
     * curso y no de la instalación, el comando —que corre sin página— pediría el que le tocara.
     */
    public function test_se_pide_el_idioma_de_la_instalacion_y_lo_leen_las_tres_versiones(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->resenaEscritaEnEspanol())]);

        app()->setLocale('fr');
        app(GoogleSocialProof::class)->refresh();

        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'languageCode=es'));

        foreach (['es', 'en', 'fr'] as $idioma) {
            app()->setLocale($idioma);

            $this->assertCount(1, app(GoogleSocialProof::class)->testimonials(),
                "La versión «{$idioma}» no lee la caché de la instalación: esa página se queda sin reseñas de Google.");
        }
    }

    /**
     * ❗❗ **LA VERSIÓN QUE NO HABLA EL IDIOMA DE LA CACHÉ NO LO FINGE** (`#591`): el texto sale como
     * está escrito y DICE su idioma, y la fecha se cuenta en el de la página.
     *
     * ⚠️ Sin el idioma la tarjeta no pone `lang` y un lector de pantalla lee español con la voz
     * inglesa; sin contar la fecha aquí, «hace una semana» sale en la versión inglesa — el defecto de
     * `#491` al revés.
     */
    public function test_en_otra_version_el_texto_dice_su_idioma_y_la_fecha_es_la_de_la_pagina(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-10T12:00:00Z'));
        Http::fake(['places.googleapis.com/*' => Http::response($this->resenaEscritaEnEspanol())]);
        app(GoogleSocialProof::class)->refresh();

        app()->setLocale('en');
        $op = app(GoogleSocialProof::class)->testimonials()->first();

        $this->assertSame('Muy limpio y seguro.', $op->text, 'el texto no sale como está escrito');
        $this->assertSame('es', $op->language, 'el texto en español no dice su idioma en la versión inglesa');
        $this->assertFalse($op->isTranslated(), 'se anuncia como traducida una reseña que se enseña tal cual');
        $this->assertSame('1 week ago', $op->when, 'la fecha relativa sale en el idioma de la caché');

        // CONTROL: en la versión de la caché manda la fecha que escribió Google y no se declara idioma.
        app()->setLocale('es');
        $op = app(GoogleSocialProof::class)->testimonials()->first();

        $this->assertSame('hace una semana', $op->when);
        $this->assertNull($op->language);
    }

    /**
     * **Una reseña escrita en el idioma de la página se enseña como la escribió su autor**, no en la
     * traducción de Google al idioma de la caché (`#591`). Y en la versión de la caché sigue siendo una
     * traducción, con su aviso: es el mismo dato leído desde dos páginas.
     */
    public function test_una_resena_escrita_en_el_idioma_de_la_pagina_sale_como_la_escribio_su_autor(): void
    {
        $json = $this->respuestaDeGoogle();
        $json['reviews'][0]['text'] = ['text' => '¡Excelente parque!', 'languageCode' => 'es'];
        $json['reviews'][0]['originalText'] = ['text' => 'Excellent playground!', 'languageCode' => 'en-US'];

        Http::fake(['places.googleapis.com/*' => Http::response($json)]);
        app(GoogleSocialProof::class)->refresh();

        app()->setLocale('en');
        $op = app(GoogleSocialProof::class)->testimonials()->first();

        $this->assertSame('Excellent playground!', $op->text,
            'La versión inglesa enseña la traducción al español de una reseña escrita en inglés.');
        $this->assertFalse($op->isTranslated());
        $this->assertNull($op->language);

        app()->setLocale('es');
        $op = app(GoogleSocialProof::class)->testimonials()->first();

        $this->assertSame('¡Excelente parque!', $op->text);
        $this->assertTrue($op->isTranslated(), 'la versión española pierde el aviso de traducción');
    }

    /** **Y la TARJETA lo pinta**: `lang` en el texto, solo en la versión que no habla su idioma. */
    public function test_la_tarjeta_pone_lang_al_texto_solo_fuera_de_su_idioma(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->resenaEscritaEnEspanol())]);
        app(GoogleSocialProof::class)->refresh();

        $consentimiento = CookieConsent::encode(['maps' => true, 'social' => false]);

        foreach (['en' => true, 'es' => false] as $idioma => $debeLlevarlo) {
            $html = (string) $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, $consentimiento)
                ->withSession(['locale' => $idioma])
                ->get('/')->assertOk()->getContent();

            preg_match('#<section id="reviews".*?</section>#s', $html, $m);
            $seccion = $m[0] ?? '';

            $this->assertStringContainsString('Muy limpio y seguro.', $seccion,
                "la versión «{$idioma}» no pinta la reseña: el caso miraría el vacío");

            $this->assertSame(
                $debeLlevarlo,
                preg_match('/<p class="rev__text[^"]*"[^>]*\blang="es"/', $seccion) === 1,
                $debeLlevarlo
                    ? 'La versión inglesa pinta un texto en español sin `lang`: un lector de pantalla lo lee con la voz inglesa.'
                    : 'La versión española declara `lang` sobre un texto en su propio idioma.',
            );
        }
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
     * ❗❗❗ **SIN CONSENTIMIENTO NI OPINIONES PROPIAS YA NO DESAPARECE: QUEDA LA NOTA Y UN AVISO**
     * (`[DECIDIDO owner, 2026-09-13]`, `#592`).
     *
     * ⚠️⚠️ **Este caso CAMBIÓ DE PREMISA y se reescribió** (el precedente de `#324`): afirmaba «200 y la
     * sección AUSENTE». En producción eso era la primera visita de cualquiera —el parque no tiene
     * opiniones propias—, así que la portada escondía también la nota de Google, que no pide permiso.
     * ▶ Lo que NO cambia: sin permiso no sale ni una tarjeta de Google ni una foto de sus servidores
     * (`RGPD-05`).
     */
    public function test_sin_consentimiento_ni_opiniones_propias_queda_la_nota_y_el_aviso(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);
        app(GoogleSocialProof::class)->refresh();

        $seccion = $this->seccionDeResenas((string) $this->get('/')->assertOk()->getContent());

        $this->assertNotSame('', $seccion, 'la sección vuelve a desaparecer entera por un permiso');
        $this->assertStringContainsString('rev-score__val', $seccion, 'la nota de Google, que no pide permiso, no sale');
        $this->assertStringContainsString('class="rev__lock"', $seccion, 'no se avisa de que las reseñas esperan el permiso');
        $this->assertStringContainsString(__('landing.reviews.locked_text'), $seccion);
        $this->assertStringContainsString(__('landing.reviews.lede_google'), $seccion,
            'la entradilla habla de opiniones propias sobre unas reseñas que son de Google');
        $this->assertStringNotContainsString('rev__card', $seccion, 'sale una tarjeta de Google sin permiso');
        $this->assertStringNotContainsString('googleusercontent.com', $seccion, 'se pide una foto a Google sin permiso (RGPD-05)');
    }

    /**
     * **El aviso abre el panel de cookies y, al conceder, recarga**: las tarjetas las pinta el servidor,
     * así que sin recargar el permiso recién dado no enseñaría nada.
     */
    public function test_el_aviso_abre_el_panel_y_recarga_al_conceder(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);
        app(GoogleSocialProof::class)->refresh();

        $seccion = $this->seccionDeResenas((string) $this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('$store.cookies.openPanel()', $seccion, 'el aviso no abre el panel de cookies');
        $this->assertMatchesRegularExpression('/x-on:cookies-updated\.window="[^"]*detail\.maps[^"]*reload\(\)"/', $seccion,
            'al conceder el mapa y las reseñas la página no se recarga: el aviso se queda donde deberían salir');
    }

    /** **Con opiniones propias no hay aviso**: se enseñan ellas, como siempre (el cruce de `#491`). */
    public function test_con_opiniones_propias_no_sale_el_aviso(): void
    {
        $this->conOpinionPropia();
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);
        app(GoogleSocialProof::class)->refresh();

        $seccion = $this->seccionDeResenas((string) $this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('Nuestra propia opinión', $seccion, 'el caso se quedó sin opiniones propias: no mediría el cruce');
        $this->assertStringNotContainsString('rev__lock', $seccion, 'el aviso tapa las opiniones propias que sí se pueden enseñar');
    }

    /** **Con el permiso dado no hay aviso**: salen las reseñas de Google. */
    public function test_con_consentimiento_no_sale_el_aviso(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);
        app(GoogleSocialProof::class)->refresh();

        $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, CookieConsent::encode(['maps' => true, 'social' => false]));
        $seccion = $this->seccionDeResenas((string) $this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('rev__card', $seccion, 'con permiso no salen las reseñas de Google');
        $this->assertStringNotContainsString('rev__lock', $seccion, 'con el permiso dado sigue pidiéndolo');
    }

    /**
     * **Solo la cascada sabe que las reseñas esperan el permiso**: lo dice con reseñas de Google y sin
     * permiso, y en ningún otro caso; las fuentes sueltas no lo dicen nunca.
     */
    public function test_solo_la_cascada_dice_que_las_resenas_esperan_el_permiso(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);
        app(GoogleSocialProof::class)->refresh();

        $this->assertTrue(app(SocialProof::class)->reviewsAwaitConsent(), 'con reseñas de Google y sin permiso no lo dice');
        $this->assertFalse(app(GoogleSocialProof::class)->reviewsAwaitConsent());
        $this->assertFalse(app(CmsSocialProof::class)->reviewsAwaitConsent());

        request()->cookies->set(CookieConsent::COOKIE_NAME, CookieConsent::encode(['maps' => true, 'social' => false]));
        $this->assertFalse(app(SocialProof::class)->reviewsAwaitConsent(), 'con el permiso dado sigue diciendo que lo espera');

        request()->cookies->remove(CookieConsent::COOKIE_NAME);
        Cache::forget(GoogleSocialProof::cacheKey());
        $this->assertFalse(app(SocialProof::class)->reviewsAwaitConsent(), 'sin reseñas de Google anuncia unas que no hay');
    }

    /** El HTML de la sección 06, o `''` si no se pinta. */
    private function seccionDeResenas(string $html): string
    {
        return preg_match('#<section id="reviews".*?</section>#s', $html, $m) === 1 ? $m[0] : '';
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  4 · La cadencia
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **LA CACHÉ VIVE MÁS QUE EL HUECO ENTRE DOS REFRESCOS** (`#591`).
     *
     * ⚠️⚠️ **Es el defecto que vio el owner mirando la portada, y no fallaba nada**: con una caché de
     * 30 minutos y un refresco cada tres horas, las reseñas de Google estaban puestas media hora de
     * cada tres. Cada pieza hacía lo que decía su comentario; el defecto solo existe mirando las DOS a
     * la vez, y eso es lo que hace este caso — lee la cadencia del scheduler REAL, no una copia.
     * ⚠️ Mide el hueco MÁS LARGO del día y no el primero: una cadencia irregular tiene huecos
     * distintos, y el que manda es el mayor.
     */
    public function test_la_cache_vive_mas_que_el_hueco_entre_dos_refrescos(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn ($evento): bool => str_contains((string) $evento->command, 'social-proof:refresh'))
            ->values();

        $this->assertCount(1, $eventos, 'el refresco de las reseñas ha dejado de estar programado, o lo está dos veces');

        $hueco = $this->huecoMaximoEnMinutos($eventos[0]->expression);

        $this->assertGreaterThan(
            $hueco * 60,
            GoogleSocialProof::CACHE_TTL_SECONDS,
            "La caché dura menos que el hueco entre dos refrescos ({$hueco} min): las reseñas de Google\n".
            'desaparecen un rato de cada ciclo y la sección cae a las opiniones propias sin que falle nada.',
        );
    }

    /**
     * **El comando hace UNA llamada por pasada** (`#591`). Eran tres —una por idioma— y ésa era la
     * aritmética que obligaba a refrescar cada tres horas: tres cada media hora agotarían a media
     * mañana el tope diario de la consola.
     */
    public function test_el_comando_hace_una_sola_llamada_por_pasada(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->respuestaDeGoogle())]);

        $this->artisan('social-proof:refresh')->assertExitCode(0);

        Http::assertSentCount(1);
    }

    /** El hueco MÁS LARGO entre dos disparos de una expresión cron, a lo largo de un día. */
    private function huecoMaximoEnMinutos(string $expresion): int
    {
        $cron = new CronExpression($expresion);
        $inicio = CarbonImmutable::parse('2026-09-10 00:00:00');
        $fin = $inicio->addDay();

        $anterior = CarbonImmutable::instance($cron->getNextRunDate($inicio, 0, true));
        $hueco = 0;

        while ($anterior->lessThan($fin)) {
            $siguiente = CarbonImmutable::instance($cron->getNextRunDate($anterior));
            $hueco = max($hueco, (int) abs($anterior->diffInMinutes($siguiente)));
            $anterior = $siguiente;
        }

        return $hueco;
    }
}
