<?php

namespace App\Domain\Content\Services;

use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Content\Models\Faq;
use App\Domain\Platform\Services\VenueAddress;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Construye los datos estructurados JSON-LD (schema.org) del sitio: marca (`Organization`),
 * negocio local (`AmusementPark` ⊂ `LocalBusiness`: dirección, teléfono, horario) y `FAQPage`.
 *
 * Es la fuente que habilita los RESULTADOS ENRIQUECIDOS de Google (horario/dirección/teléfono en
 * la ficha local, panel de marca, FAQ desplegable). Es INVISIBLE para el visitante: solo lo leen los
 * buscadores (vive en un `<script type="application/ld+json">`).
 *
 * Defensivo por diseño (los datos del negocio aún tienen placeholders `[PENDIENTE]` y campos vacíos
 * editables desde el panel): omite TODO campo vacío o con placeholder, nunca lanza y siempre emite
 * un grafo válido mínimo (al menos nombre + url). El render seguro lo garantiza {@see self::toJson()}.
 */
class StructuredData
{
    /** schema.org day name por `weekday` de Carbon (0=domingo .. 6=sábado, igual que el contrato del calendario). */
    private const SCHEMA_DAYS = [
        0 => 'https://schema.org/Sunday',
        1 => 'https://schema.org/Monday',
        2 => 'https://schema.org/Tuesday',
        3 => 'https://schema.org/Wednesday',
        4 => 'https://schema.org/Thursday',
        5 => 'https://schema.org/Friday',
        6 => 'https://schema.org/Saturday',
    ];

    /**
     * Grafo global marca + negocio local, a partir del array `$site` del composer.
     *
     * Con lo que Google recomienda para un negocio local (`docs/specs/seo.md` §1 y S4): `geo` (las coordenadas de la
     * inserción del mapa, {@see MapsEmbed::coordinates()}), `priceRange` (el «desde» de la instalación, el mismo de sus
     * llamadas a reservar) y la dirección por campos ({@see VenueAddress::parts()}). Sin reseñas: Google no da estrellas
     * a quien marca las suyas propias.
     *
     * @param  array<string,mixed>  $site
     * @param  string|null  $minPriceLabel  el «desde» ya escrito (`ctaMinPriceLabel` del composer, «6,40 €»)
     * @return array<string,mixed>
     */
    public static function businessGraph(array $site, ?string $minPriceLabel = null): array
    {
        $url = url('/');
        $name = self::clean($site['name'] ?? null) ?? config('app.name');
        $logo = asset('apple-touch-icon.png');
        $image = self::clean($site['og_image'] ?? null) ?? asset('og-image.jpg');

        // Redes oficiales (sameAs): el composer deja '#' como sentinela de "sin configurar".
        $sameAs = array_values(array_filter([
            self::externalUrl($site['instagram'] ?? null),
            self::externalUrl($site['tiktok'] ?? null),
        ]));

        $organization = self::prune([
            '@type' => 'Organization',
            '@id' => $url.'#organization',
            'name' => $name,
            'url' => $url,
            'logo' => $logo,
            'sameAs' => $sameAs,
        ]);

        $business = self::prune([
            '@type' => 'AmusementPark',
            '@id' => $url.'#business',
            'name' => $name,
            'url' => $url,
            'image' => $image,
            'logo' => $logo,
            // `phone_tel` ya viene normalizado (solo dígitos/+) y vacío si es placeholder.
            'telephone' => self::clean($site['phone_tel'] ?? null) ?? self::clean($site['phone'] ?? null),
            'email' => self::clean($site['email'] ?? null),
            'address' => self::postalAddress($site),
            'geo' => self::geo($site),
            'hasMap' => self::externalUrl($site['maps'] ?? null),
            'priceRange' => self::priceRange($minPriceLabel),
            'openingHoursSpecification' => self::openingHours(),
            'sameAs' => $sameAs,
            'parentOrganization' => ['@id' => $url.'#organization'],
        ]);

        return [
            '@context' => 'https://schema.org',
            '@graph' => [$organization, $business],
        ];
    }

    /**
     * Dirección postal. Devuelve null si no hay nada útil que declarar (evita una `PostalAddress`
     * con solo el país, que Google trata como ruido).
     *
     * @param  array<string,mixed>  $site
     * @return array<string,mixed>|null
     */
    private static function postalAddress(array $site): ?array
    {
        // ⚠️ La regla de cómo se escribe —y ahora también de cómo se PARTE— una dirección de dos líneas vive en UN sitio
        // (`#650`): aquí estaba su segunda copia, y con un filtro distinto al de la página. Una divergencia entre las dos
        // habría puesto en el JSON-LD una dirección distinta de la que lee el visitante.
        $partes = VenueAddress::parts(
            self::clean($site['address1'] ?? null),
            self::clean($site['address2'] ?? null),
        );

        $address = self::prune([
            '@type' => 'PostalAddress',
            'streetAddress' => $partes['street'],
            'postalCode' => $partes['postalCode'],
            'addressLocality' => $partes['locality'] ?? self::clean($site['city'] ?? null),
            'addressRegion' => $partes['region'],
            'addressCountry' => 'ES',
        ]);

        // `@type` + `addressCountry` están siempre; exige al menos un dato real más (calle o ciudad).
        return count($address) > 2 ? $address : null;
    }

    /**
     * Las coordenadas, de la inserción del mapa que el operador pegó en el panel. Sin ellas, nada.
     *
     * @param  array<string,mixed>  $site
     * @return array<string,mixed>|null
     */
    private static function geo(array $site): ?array
    {
        $c = MapsEmbed::coordinates(is_string($site['maps_embed'] ?? null) ? $site['maps_embed'] : null);

        return $c === null ? null : ['@type' => 'GeoCoordinates', 'latitude' => $c['latitude'], 'longitude' => $c['longitude']];
    }

    /** «Desde 6,40 €»: el «desde» de la instalación con el texto de sus llamadas a reservar (Google: < 100 caracteres). */
    private static function priceRange(?string $minPriceLabel): ?string
    {
        $label = trim((string) $minPriceLabel);

        return $label === '' ? null : Str::ucfirst((string) __('landing.nav.cta_buy_from', ['amount' => $label]));
    }

    /**
     * Horario semanal recurrente → `openingHoursSpecification`, agrupando los días con la MISMA
     * ventana en una sola entrada (p. ej. lunes-viernes 16:00-22:00). Solo horario regular; las
     * excepciones puntuales (temporadas/festivos) no se exponen aquí.
     *
     * @return list<array<string,mixed>>
     */
    public static function openingHours(): array
    {
        if (! Schema::hasTable('opening_hours')) {
            return [];
        }

        // Agrupa por ventana [open,close]; conserva qué días la comparten (índice = weekday).
        $byWindow = [];
        foreach (app(OperatingCalendar::class)->weeklyOpenings() as $hour) {
            if ($hour->isClosed || empty($hour->opensAt) || empty($hour->closesAt)) {
                continue;
            }
            if (! array_key_exists($hour->weekday, self::SCHEMA_DAYS)) {
                continue;
            }

            $key = $hour->opensAt.'|'.$hour->closesAt;
            $byWindow[$key]['opens'] = substr((string) $hour->opensAt, 0, 5);
            $byWindow[$key]['closes'] = substr((string) $hour->closesAt, 0, 5);
            $byWindow[$key]['days'][$hour->weekday] = self::SCHEMA_DAYS[$hour->weekday];
        }

        ksort($byWindow); // orden determinista (por hora de apertura)

        $specs = [];
        foreach ($byWindow as $window) {
            ksort($window['days']); // domingo..sábado por índice
            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => array_values($window['days']),
                'opens' => $window['opens'],
                'closes' => $window['closes'],
            ];
        }

        return $specs;
    }

    /**
     * `FAQPage` a partir de las preguntas activas. Null si no hay ninguna utilizable (la home
     * entonces no emite el bloque).
     *
     * @param  iterable<int,Faq>  $faqs
     * @return array<string,mixed>|null
     */
    public static function faqPage(iterable $faqs): ?array
    {
        $entities = [];
        foreach ($faqs as $faq) {
            $question = self::clean($faq->tr('question'));
            $answer = self::clean($faq->tr('answer'));
            if ($question === null || $answer === null) {
                continue;
            }

            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ];
        }

        if ($entities === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * Serializa a JSON seguro para incrustar en `<script type="application/ld+json">`.
     *
     * `JSON_HEX_TAG` escapa `<`, `>` y `&` → un valor editable (settings o una respuesta de FAQ) que
     * contuviera `</script>` NO puede romper el bloque (anti-XSS). `UNESCAPED_UNICODE` deja ñ/á
     * legibles; `UNESCAPED_SLASHES`, las URLs limpias.
     *
     * @param  array<string,mixed>  $data
     */
    public static function toJson(array $data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * Quita las claves con valor vacío (`null`, `''` o `[]`) de un nodo schema.org, conservando el
     * orden. Mantiene los escalares `0`/`false` por si algún día se usan (hoy no hay ninguno).
     *
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    private static function prune(array $node): array
    {
        return array_filter($node, static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);
    }

    /** Trim + descarta vacío o placeholder `[PENDIENTE]` (datos de negocio aún sin rellenar). */
    private static function clean(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return ($value === '' || str_contains($value, '[PENDIENTE]')) ? null : $value;
    }

    /** URL externa http(s) que NO sea el sentinela '#' del composer; si no, null. */
    private static function externalUrl(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return ($value !== '' && $value !== '#' && preg_match('#^https?://#i', $value) === 1) ? $value : null;
    }
}
