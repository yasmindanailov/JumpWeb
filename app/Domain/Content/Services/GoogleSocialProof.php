<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Contracts\OriginalText;
use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\ReviewSelection;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\SiteLocales;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * **Las reseñas de Google** (`DECISIONES #491`, `specs/google-reviews.md`).
 *
 * ❗❗❗ **LEE DE LA CACHÉ Y NO LLAMA A GOOGLE NUNCA.** Es la regla de §4.2 y no es una optimización:
 * `PERF-02` documenta que la portada llegó a hacer ~1.900 consultas por GET anónimo, y **una llamada
 * HTTP síncrona en el render es peor que aquello** — no son milisegundos de BD local, es la latencia
 * de un tercero en el camino crítico de la página. Quien llama es {@see refresh()}, y a él solo lo
 * invoca el comando programado.
 * ▶ Lo vigila `SocialProofNeverHitsTheRenderPathTest` con el cliente HTTP falseado para **explotar
 * si alguien lo llama**, y con su guarda-de-la-guarda: un caso que comprueba que el falso SÍ explota.
 *
 * ❗❗ **La caché es CORTA a propósito, y es la única forma legal de tener esto.** La política de
 * Places prohíbe almacenar reseñas y valoraciones (R2) salvo *«temporary caching for immediate
 * performance optimization»*. Por eso no hay tabla ni columna: lo único que se persiste es el
 * `place_id`, que la política exime.
 * ⚠️⚠️ Y Redis está en `allkeys-lru` (`#137`), así que **puede evictar la clave antes de su TTL**:
 * «no está» es el caso NORMAL, no el fallo. La cascada cae al CMS y no se registra ningún error.
 *
 * ❗❗ **El UMBRAL** (`[DECIDIDO owner, 2026-09-10]`): por debajo de {@see MIN_REVIEWS} reseñas
 * Google **no se usa en absoluto**. Medido el 2026-09-10, el parque tenía **una**: publicar
 * «5,0 · 1 reseña» resta en vez de sumar, y la segunda reseña que entrara decidiría la media que ve
 * todo el mundo.
 */
class GoogleSocialProof implements SocialProof
{
    /** Clave del ajuste que guarda el identificador del sitio. Es lo único que la política exime. */
    public const PLACE_ID_SETTING = 'social.google_place_id';

    /**
     * ❗❗❗ **UNA sola caché, en el idioma de la instalación** (`[DECIDIDO owner, 2026-09-13]`, `#591`).
     *
     * Fue una por idioma desde `#491`: Google devuelve `relativePublishTimeDescription` —y el propio
     * `text`— **en el idioma que se le pida**, y sin pedirle ninguno contestó «a week ago» sobre la
     * página en español. Pero tres idiomas eran tres llamadas por pasada, la cadencia tuvo que bajar a
     * cada tres horas con un TTL de media hora, y **las reseñas se veían media hora de cada tres**. El
     * owner eligió verlas siempre en un idioma antes que a ratos en tres.
     * ▶ Las otras versiones leen esta misma caché y **no fingen otro idioma**: el texto sale como está
     * escrito y dice cuál es, y la fecha relativa se cuenta en el de la página ({@see testimonials()}).
     * Traducir el texto nosotros sigue prohibido: R4 no deja alterar el contenido del usuario.
     */
    public const CACHE_PREFIX = 'social-proof.google.';

    /**
     * Cuánto vive la respuesta en caché: **35 minutos para un refresco cada 30** (`#591`).
     *
     * ❗❗ **Tiene que ser MAYOR que el hueco entre dos refrescos, y el defecto lo vio el owner mirando
     * la portada**: hasta `#591` valía la mitad de la cadencia —30 minutos contra un refresco cada tres
     * horas— y la sección enseñaba Google media hora de cada tres. La regla de `#491` («la mitad, para
     * que una respuesta vieja no sobreviva a un refresco fallido») protegía un borde a costa del caso
     * normal.
     * ⚠️ Lo que se acepta a cambio está acotado: si un refresco falla, lo último bueno se sirve **cinco
     * minutos más** —35 de antigüedad como mucho— y después la sección cae a las opiniones propias. Los
     * cinco minutos cubren el reloj del cron y el timeout de la llamada. Sigue siendo *«temporary
     * caching»*, que es lo que la política permite.
     * ▶ `SocialProofNeverHitsTheRenderPathTest` ata este número a la cadencia REAL del scheduler.
     */
    public const CACHE_TTL_SECONDS = 2100;

    /**
     * `[DECIDIDO owner, 2026-09-10]`. Por debajo, Google no se toca.
     *
     * ⚠️⚠️ **Estuvo en 10 y el owner lo bajó a 1 TRAS VER LA SECCIÓN RENDERIZADA con la reseña real**
     * (`#493`). El 10 salía de la volatilidad —con una sola reseña, la segunda que entre decide la
     * media que ve todo el mundo: una de 1 estrella publicaría un **3,0** al día siguiente— y esa
     * aritmética **no ha cambiado**; lo que cambió es que con el umbral en 10 la sección **no
     * enseñaba nada de Google en absoluto**, y el parque prefiere publicar lo que tiene.
     * ▶ Se deja escrito para que nadie lo «arregle» subiéndolo otra vez sin reabrir la decisión, y
     * para que quede claro qué se aceptó a cambio.
     *
     * ❗❗❗ **Y con el umbral en 1 hay un segundo efecto, MEDIDO el mismo día: la API de Google
     * devuelve instantáneas INCONSISTENTES entre llamadas consecutivas.** Cinco consultas seguidas
     * al mismo sitio dieron **cuatro veces «5,0 · 1 reseña» y una vez «3,0 · 2 reseñas»** —la
     * segunda existe y es de 1 estrella—. No es nuestra caché: es su infraestructura.
     * ⚠️ Traducido: **con dos reseñas y una cifra que salta de 5,0 a 3,0, lo que la portada publica
     * depende de qué instantánea pille el refresco de esa hora.** Con un recuento alto el efecto se
     * diluye —una reseña más no mueve una media de 300—; con dos, la mueve entera. Es la misma
     * razón que sostenía el 10, por otra puerta.
     *
     * ❗❗❗ **`[DECIDIDO owner, 2026-09-10]` — SE QUEDA EN 1 Y ES DEFINITIVO** (`#494`): *«deja el
     * umbral a 1 siempre; mínimo 1 reseña para mostrar el widget de Google»*. Re-confirmado con la
     * inconsistencia de arriba ya medida y delante, así que **no es un descuido: es el precio
     * aceptado**. ⚠️ Subirlo «para que la media sea más estable» apaga el widget entero y deshace
     * `#493` y esto — no se toca sin reabrir la decisión con el owner.
     * ▶ Lo fija `GoogleAttributionTest`, para que la constante no vuelva a moverse en silencio.
     */
    public const MIN_REVIEWS = 1;

    private const ENDPOINT = 'https://places.googleapis.com/v1/places/';

    /**
     * Los campos que se piden. **Se enumeran a propósito**: la API cobra por máscara de campos, y
     * `reviews` es lo que la sube al SKU más caro. Pedir de más es pagar de más.
     */
    private const FIELD_MASK = 'id,displayName,rating,userRatingCount,googleMapsUri,reviews';

    private const TIMEOUT_SECONDS = 8;

    /**
     * **El idioma en que se le piden las reseñas a Google**: el primero de la instalación (`#591`).
     *
     * ⚠️ Sale de {@see SiteLocales::SUPPORTED} y no de un `'es'` escrito aquí: qué idioma habla el
     * sitio es de la instalación, no del producto.
     */
    public static function sourceLocale(): string
    {
        return SiteLocales::SUPPORTED[0];
    }

    /** La clave de la caché. Es UNA y la leen las tres versiones del sitio (`#591`). */
    public static function cacheKey(): string
    {
        return self::CACHE_PREFIX.self::sourceLocale();
    }

    private const CONNECT_TIMEOUT_SECONDS = 4;

    public function rating(): ?Rating
    {
        $datos = $this->cached();

        if ($datos === null) {
            return null;
        }

        return new Rating(
            value: (float) $datos['value'],
            count: (int) $datos['count'],
            url: $datos['url'],
            source: TestimonialData::SOURCE_GOOGLE,
        );
    }

    /**
     * Las reseñas, **tal y como las puede enseñar la versión del sitio que se está pintando**.
     *
     * ❗❗ **La caché está en UN idioma y la leen las tres versiones** (`#591`), así que aquí se decide
     * qué hace una página que no habla ese idioma, y la regla es **no fingir**:
     *  - el texto sale **como está escrito** y viaja con su idioma, para que la tarjeta ponga `lang`
     *    (sin él, un lector de pantalla lee español con la voz de la página);
     *  - si la reseña se escribió en el idioma DE LA PÁGINA —una en inglés, en la versión inglesa— se
     *    enseña **lo que escribió su autor** y no la traducción de Google al idioma de la caché;
     *  - la fecha relativa se cuenta en el idioma de la página desde `publishTime`, porque la de Google
     *    viene escrita en el de la caché: «hace una semana» en la versión francesa es el defecto de
     *    `#491` al revés.
     *
     * @return Collection<int, TestimonialData>
     */
    public function testimonials(): Collection
    {
        $datos = $this->cached();
        $pagina = app()->getLocale();
        $idiomaCache = (string) ($datos['lang'] ?? self::sourceLocale());
        $mismoIdioma = self::sameLanguage($idiomaCache, $pagina);

        // ⚠️⚠️ **Las claves nuevas se leen con `?? null` porque la CACHÉ SOBREVIVE AL DESPLIEGUE.**
        // Al subir `#494` había entradas escritas por el código anterior, sin `author_url` ni
        // `original`: leerlas por acceso directo revienta la portada hasta el primer refresco. Con
        // `lang` y `published` (`#591`) pasa lo mismo. No es defensa por si acaso — es la forma de este
        // dato.
        return collect($datos['reviews'] ?? [])->map(function (array $r) use ($pagina, $idiomaCache, $mismoIdioma): TestimonialData {
            $original = $this->original($r);
            $delAutor = $original !== null && self::sameLanguage($original->language, $pagina);

            return new TestimonialData(
                text: $delAutor ? $original->text : (string) $r['text'],
                author: (string) $r['author'],
                rating: $r['rating'],
                when: $mismoIdioma ? $r['when'] : RelativeAge::of(self::instant($r['published'] ?? null)),
                url: $r['url'],
                source: TestimonialData::SOURCE_GOOGLE,
                avatarUrl: $r['avatar'],
                authorUrl: $r['author_url'] ?? null,
                originalText: $delAutor ? null : $original,
                language: ($delAutor || $mismoIdioma) ? null : $idiomaCache,
            );
        })->values();
    }

    /**
     * El original de una fila de la caché, o `null` si lo servido no es una traducción.
     *
     * ⚠️ **Exige las DOS mitades.** Una fila a medias —texto sin idioma— escribiría «Traducida del
     * ` `»; el tipo {@see OriginalText} lo impide aguas abajo y esta puerta lo impide aquí, que es
     * donde entra el dato de fuera.
     *
     * @param  array<string,mixed>  $r
     */
    private function original(array $r): ?OriginalText
    {
        $texto = trim((string) ($r['original'] ?? ''));
        $idioma = trim((string) ($r['original_lang'] ?? ''));

        return ($texto !== '' && $idioma !== '') ? new OriginalText($texto, $idioma) : null;
    }

    /**
     * Siempre `false`: esta fuente no sabe nada del permiso del visitante. Lo sabe la cascada
     * ({@see FallingBackSocialProof}), que es donde se decide qué se enseña sin él.
     */
    public function reviewsAwaitConsent(): bool
    {
        return false;
    }

    /**
     * **Siempre `true`** (`#732`, §4.3·9): sus opiniones **sí** necesitan el permiso del visitante.
     *
     * ❗❗ El motivo es concreto y no una cautela: R3 obliga a mostrar la foto del autor, esa foto vive
     * en `lh3.googleusercontent.com`, y cargarla **es una petición del visitante a Google** — que es
     * justo lo que `RGPD-05` gestiona. Es la diferencia con `BusinessProfileSocialProof`, que trae la
     * foto a casa y por eso no necesita permiso.
     *
     * ▶ **Y es lo que impide hoy sacar a Google de `img-src`** (§4.3·12): mientras esta fuente siga
     * enlazada, el navegador tiene que poder cargar de ahí. El cambio de CSP va con la retirada de
     * Places, que el owner aún no ha decidido.
     */
    public function reviewsNeedConsent(): bool
    {
        return true;
    }

    /**
     * Siempre `null` (`#732`): **Places no filtra por estrellas.** Devuelve cinco por relevancia y
     * se publican las que vengan, así que no hay selección que declarar — y por eso la línea del
     * §4.3·10 **no aparece** mientras responda esta fuente. Si algún día se filtrara aquí, habría
     * que declararlo: la obligación es del que filtra.
     */
    public function selection(): ?ReviewSelection
    {
        return null;
    }

    /** ¿Está configurada esta instalación? Sin las dos cosas, no hay nada que traer. */
    public function configured(): bool
    {
        return $this->apiKey() !== '' && $this->placeId() !== '';
    }

    /**
     * **Habla con Google y deja la respuesta en la caché corta.** Lo llama el comando programado.
     *
     * ⚠️⚠️ **Devuelve CUÁL de las cinco salidas fue, y no un `?int`.** Ninguna de las cuatro vacías
     * es un error del producto —la sección cae al CMS, que es su conducta declarada—, pero **piden
     * cosas distintas**: un ajuste, tiempo, la consola de Google o la red. Con un `null` para las
     * cuatro, distinguirlas obligaba a mirar el log y deducirlo de una ausencia.
     */
    public function refresh(): SocialProofRefresh
    {
        // ⚠️ El idioma sale de la INSTALACIÓN y no de la petición en curso: quien llama es un comando
        // que corre sin página, y con `app()->getLocale()` pediría el que le tocara (`#591`).
        $locale = self::sourceLocale();

        if (! $this->configured()) {
            return new SocialProofRefresh(SocialProofRefresh::NOT_CONFIGURED);
        }

        try {
            $respuesta = Http::withHeaders([
                'X-Goog-Api-Key' => $this->apiKey(),
                'X-Goog-FieldMask' => self::FIELD_MASK,
            ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                // ⚠️ `languageCode` es lo que hace que la fecha y el texto lleguen en el idioma de
                // la caché. Sin él, Google elige y la versión en español publica «a week ago».
                ->get(self::ENDPOINT.$this->placeId(), ['languageCode' => $locale]);
        } catch (\Throwable $e) {
            // Un timeout o un DNS caído no son un fallo nuestro: se anota y se cae al CMS.
            Log::info('social_proof.google_unreachable', ['reason' => $e::class]);

            return new SocialProofRefresh(SocialProofRefresh::UNREACHABLE);
        }

        if (! $respuesta->successful()) {
            // 403 (clave inválida o API sin habilitar), 429 (cuota), 5xx… Se anota el CÓDIGO y
            // nunca el cuerpo: puede traer texto de reseñas, y `RGPD-02` no lo quiere en el log.
            Log::warning('social_proof.google_failed', ['status' => $respuesta->status()]);

            return new SocialProofRefresh(SocialProofRefresh::REJECTED, status: $respuesta->status());
        }

        $datos = $this->normalize($respuesta->json() ?? [], $locale);

        if ($datos === null) {
            // ⚠️ Se OLVIDA lo que hubiera: si el sitio baja del umbral —o pierde la nota— la caché
            // no puede seguir sirviendo la cifra de antes. Es el mismo criterio que el TTL corto.
            Cache::forget(self::cacheKey());

            return new SocialProofRefresh(SocialProofRefresh::BELOW_THRESHOLD);
        }

        Cache::put(self::cacheKey(), $datos, self::CACHE_TTL_SECONDS);

        return new SocialProofRefresh(SocialProofRefresh::CACHED, reviews: count($datos['reviews']));
    }

    /**
     * Traduce la respuesta de Places a lo que la landing necesita, o `null` si no es publicable.
     *
     * @param  array<string,mixed>  $json
     * @param  ?string  $locale  el idioma en que se pidió; `null` es el de la instalación
     * @return array{value:float,count:int,url:?string,lang:string,reviews:array<int,array<string,mixed>>}|null
     */
    private function normalize(array $json, ?string $locale = null): ?array
    {
        $count = (int) ($json['userRatingCount'] ?? 0);
        $value = (float) ($json['rating'] ?? 0);

        // ❗❗ El UMBRAL se aplica AQUÍ y no al pintar: así ni siquiera llega a la caché una cifra que
        // no se puede publicar, y ninguna superficie puede saltárselo por su cuenta.
        if ($count < self::MIN_REVIEWS || $value <= 0) {
            return null;
        }

        $reviews = [];
        foreach ($json['reviews'] ?? [] as $r) {
            [$texto, $original, $idiomaOriginal] = $this->texts($r);
            $autor = trim((string) ($r['authorAttribution']['displayName'] ?? ''));

            // ⚠️⚠️ **Sin autor no se publica.** R3 exige acreditar al autor al mostrar una reseña, y
            // una reseña anónima incumple la atribución obligatoria: es preferible una menos.
            if ($texto === '' || $autor === '') {
                continue;
            }

            $reviews[] = [
                'text' => $texto,
                'author' => $autor,
                'rating' => isset($r['rating']) ? (int) $r['rating'] : null,
                'when' => ($r['relativePublishTimeDescription'] ?? null) ?: null,
                // El INSTANTE, para contar la fecha en las versiones que no hablan el idioma de la
                // caché (`#591`): la descripción de arriba viene escrita en ése.
                'published' => self::instant($r['publishTime'] ?? null)?->toIso8601String(),
                'url' => $this->safeUrl($r['googleMapsUri'] ?? null),
                'avatar' => $this->safeUrl($r['authorAttribution']['photoUri'] ?? null),
                // ❗❗ **La tercera pata de la atribución de R3**, que hasta `#494` no se leía: *«author's
                // avatar image, name, and profile link»*. Ya venía en esta misma respuesta.
                'author_url' => $this->safeUrl($r['authorAttribution']['uri'] ?? null),
                // Presentes **solo si lo servido es una traducción** (ver {@see texts()}).
                'original' => $original,
                'original_lang' => $idiomaOriginal,
            ];
        }

        return [
            'value' => $value,
            'count' => $count,
            'url' => $this->safeUrl($json['googleMapsUri'] ?? null),
            'lang' => $locale ?? self::sourceLocale(),
            'reviews' => $reviews,
        ];
    }

    /**
     * **Qué texto se enseña, y si es una traducción de otro** (`#494`).
     *
     * ❗❗❗ **La política obliga a avisarlo**: *«Make end users aware when a review has been translated
     * from its original language»*. Y aquí es el caso NORMAL, no un borde: medido contra la API real
     * el 2026-09-10, las dos reseñas del parque están escritas en español, así que **en inglés y en
     * francés Google devuelve las dos traducidas**. Dos de los tres idiomas del sitio.
     *
     * ⚠️⚠️ **La señal es el IDIOMA, nunca comparar los dos textos.** Google devuelve `originalText`
     * SIEMPRE, traducida o no —en español los dos vienen con `languageCode: es` y el mismo
     * contenido—, así que «hay `originalText`» no significa «está traducida». Lo que lo significa es
     * que los dos códigos difieran, que es además lo que la política nombra.
     *
     * ⚠️ **Y si Google no traduce, lo servido ES el original**: entonces no hay «otro» texto que
     * ofrecer y no se avisa de nada. Sin esta rama, una reseña en el idioma de la página se anunciaría
     * como traducida de sí misma.
     *
     * @param  array<string,mixed>  $r
     * @return array{0:string,1:?string,2:?string} [lo que se enseña, el original o null, su idioma o null]
     */
    private function texts(array $r): array
    {
        $servido = trim((string) ($r['text']['text'] ?? ''));
        $idiomaServido = $this->lang($r['text']['languageCode'] ?? null);

        $original = trim((string) ($r['originalText']['text'] ?? ''));
        $idiomaOriginal = $this->lang($r['originalText']['languageCode'] ?? null);

        // Google no ha traducido nada: se enseña el original y no queda un segundo texto que dar.
        if ($servido === '') {
            return [$original, null, null];
        }

        $traducida = $original !== ''
            && $idiomaServido !== null
            && $idiomaOriginal !== null
            && $idiomaServido !== $idiomaOriginal;

        return $traducida
            ? [$servido, $original, $idiomaOriginal]
            : [$servido, null, null];
    }

    /** El código de idioma de la fuente, o `null` si no lo declara. No se normaliza. */
    private function lang(mixed $valor): ?string
    {
        $valor = trim((string) ($valor ?? ''));

        return $valor !== '' ? $valor : null;
    }

    /**
     * ¿Hablan el mismo idioma dos códigos? Compara la subetiqueta PRIMARIA: Google declara `en-US` y
     * la página es `en`.
     */
    private static function sameLanguage(string $a, string $b): bool
    {
        $primaria = static fn (string $codigo): string => strtolower(explode('-', str_replace('_', '-', trim($codigo)))[0]);

        return $primaria($a) !== '' && $primaria($a) === $primaria($b);
    }

    /**
     * Un instante de la fuente, o `null` si no se puede leer.
     *
     * ⚠️ Se valida al ENTRAR y otra vez al LEER: la caché sobrevive al despliegue y puede traer lo que
     * escribiera otra versión del código.
     */
    private static function instant(mixed $valor): ?CarbonImmutable
    {
        $valor = trim((string) ($valor ?? ''));

        if ($valor === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Lo que hay en la caché, o `null`.
     *
     * ⚠️ **Aquí NO se llama a Google, ni siquiera si está vacía**, y ésa es la propiedad entera de
     * esta clase: un `Cache::remember` convertiría la primera visita tras una evicción en una
     * llamada de tercero dentro del render.
     *
     * @return array{value:float,count:int,url:?string,lang?:string,reviews:array<int,array<string,mixed>>}|null
     */
    private function cached(): ?array
    {
        $datos = Cache::get(self::cacheKey());

        return is_array($datos) ? $datos : null;
    }

    /**
     * Solo `http(s)`, o `null`.
     *
     * ⚠️⚠️ **`SEC-07` se aplica DONDE NACE EL DATO, no en la plantilla.** Estas URL vienen de un
     * tercero y acaban en un `href` y en un `src`; sanearlas aquí es lo que garantiza que **todas**
     * las superficies que las consuman —la portada hoy, la API o un correo mañana— reciban lo mismo.
     * Hacerlo en la vista deja la siguiente superficie sin defensa y nadie se entera.
     */
    private function safeUrl(mixed $valor): ?string
    {
        $valor = trim((string) ($valor ?? ''));

        return ($valor !== '' && preg_match('#^https?://#i', $valor) === 1) ? $valor : null;
    }

    private function apiKey(): string
    {
        return trim((string) config('services.google_places.key', ''));
    }

    private function placeId(): string
    {
        return trim((string) Setting::value(self::PLACE_ID_SETTING, ''));
    }
}
