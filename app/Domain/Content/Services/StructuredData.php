<?php

namespace App\Domain\Content\Services;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Content\Models\Faq;
use Illuminate\Support\Facades\Schema;

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
    /** schema.org day name por `weekday` de Carbon (0=domingo .. 6=sábado, igual que OpeningHour). */
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
     * @param  array<string,mixed>  $site
     * @return array<string,mixed>
     */
    public static function businessGraph(array $site): array
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
        $street = implode(', ', array_filter([
            self::clean($site['address1'] ?? null),
            self::clean($site['address2'] ?? null),
        ]));

        $address = self::prune([
            '@type' => 'PostalAddress',
            'streetAddress' => $street !== '' ? $street : null,
            'addressLocality' => self::clean($site['city'] ?? null),
            'addressCountry' => 'ES',
        ]);

        // `@type` + `addressCountry` están siempre; exige al menos un dato real más (calle o ciudad).
        return count($address) > 2 ? $address : null;
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
        foreach (OpeningHour::all() as $hour) {
            if ($hour->is_closed || empty($hour->open_time) || empty($hour->close_time)) {
                continue;
            }
            if (! array_key_exists((int) $hour->weekday, self::SCHEMA_DAYS)) {
                continue;
            }

            $key = $hour->open_time.'|'.$hour->close_time;
            $byWindow[$key]['opens'] = substr((string) $hour->open_time, 0, 5);
            $byWindow[$key]['closes'] = substr((string) $hour->close_time, 0, 5);
            $byWindow[$key]['days'][(int) $hour->weekday] = self::SCHEMA_DAYS[(int) $hour->weekday];
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
