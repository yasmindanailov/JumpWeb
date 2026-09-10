<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use App\Domain\Platform\Models\Setting;
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
     * ⚠️⚠️ **La caché es POR IDIOMA, y lo descubrió renderizar con datos reales.** Google devuelve
     * `relativePublishTimeDescription` —y el propio `text`— **en el idioma que se le pida**, y sin
     * pedirle ninguno contestó «a week ago» sobre una página en español. La landing es ES/EN/FR, así
     * que una sola caché serviría la fecha de un idioma a los tres.
     * ▶ Y no vale traducirlo nosotros: R4 prohíbe alterar el contenido del usuario. Se le pide a
     * Google, que es quien puede.
     */
    public const CACHE_PREFIX = 'social-proof.google.';

    /**
     * Cuánto vive la respuesta en caché.
     *
     * ⚠️ **Es la mitad del refresco (que va cada hora), no el doble.** Con un TTL más largo que la
     * cadencia, una respuesta vieja sobreviviría a un refresco fallido y la sección publicaría una
     * cifra de ayer creyéndola de hoy; con la mitad, el hueco cae al CMS, que es la conducta
     * declarada. Y sigue siendo «temporary caching», que es lo que la política permite.
     */
    public const CACHE_TTL_SECONDS = 1800;

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
     */
    public const MIN_REVIEWS = 1;

    private const ENDPOINT = 'https://places.googleapis.com/v1/places/';

    /**
     * Los campos que se piden. **Se enumeran a propósito**: la API cobra por máscara de campos, y
     * `reviews` es lo que la sube al SKU más caro. Pedir de más es pagar de más.
     */
    private const FIELD_MASK = 'id,displayName,rating,userRatingCount,googleMapsUri,reviews';

    private const TIMEOUT_SECONDS = 8;

    /** La clave de ESTE idioma. */
    public static function cacheKey(?string $locale = null): string
    {
        return self::CACHE_PREFIX.($locale ?? app()->getLocale());
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

    /** @return Collection<int, TestimonialData> */
    public function testimonials(): Collection
    {
        $datos = $this->cached();

        return collect($datos['reviews'] ?? [])->map(fn (array $r): TestimonialData => new TestimonialData(
            text: (string) $r['text'],
            author: (string) $r['author'],
            rating: $r['rating'],
            when: $r['when'],
            url: $r['url'],
            source: TestimonialData::SOURCE_GOOGLE,
            avatarUrl: $r['avatar'],
        ))->values();
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
    public function refresh(?string $locale = null): SocialProofRefresh
    {
        $locale ??= app()->getLocale();

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
                // la página. Sin él, Google elige y una página en español publica «a week ago».
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

        $datos = $this->normalize($respuesta->json() ?? []);

        if ($datos === null) {
            // ⚠️ Se OLVIDA lo que hubiera: si el sitio baja del umbral —o pierde la nota— la caché
            // no puede seguir sirviendo la cifra de antes. Es el mismo criterio que el TTL corto.
            Cache::forget(self::cacheKey($locale));

            return new SocialProofRefresh(SocialProofRefresh::BELOW_THRESHOLD);
        }

        Cache::put(self::cacheKey($locale), $datos, self::CACHE_TTL_SECONDS);

        return new SocialProofRefresh(SocialProofRefresh::CACHED, reviews: count($datos['reviews']));
    }

    /**
     * Traduce la respuesta de Places a lo que la landing necesita, o `null` si no es publicable.
     *
     * @param  array<string,mixed>  $json
     * @return array{value:float,count:int,url:?string,reviews:array<int,array<string,mixed>>}|null
     */
    private function normalize(array $json): ?array
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
            $texto = trim((string) (($r['text']['text'] ?? '') ?: ($r['originalText']['text'] ?? '')));
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
                'url' => $this->safeUrl($r['googleMapsUri'] ?? null),
                'avatar' => $this->safeUrl($r['authorAttribution']['photoUri'] ?? null),
            ];
        }

        return [
            'value' => $value,
            'count' => $count,
            'url' => $this->safeUrl($json['googleMapsUri'] ?? null),
            'reviews' => $reviews,
        ];
    }

    /**
     * Lo que hay en la caché, o `null`.
     *
     * ⚠️ **Aquí NO se llama a Google, ni siquiera si está vacía**, y ésa es la propiedad entera de
     * esta clase: un `Cache::remember` convertiría la primera visita tras una evicción en una
     * llamada de tercero dentro del render.
     *
     * @return array{value:float,count:int,url:?string,reviews:array<int,array<string,mixed>>}|null
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
