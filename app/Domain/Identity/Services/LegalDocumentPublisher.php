<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Exceptions\DraftCannotBePublishedException;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Fase 6 · waiver — PUBLICAR es un acto con fecha, no un guardado (`specs/waiver-probatorio.md`
 * §4.2): congela el texto de un documento legal, por idioma, como una versión nueva e inmutable.
 *
 * Recibe el texto YA interpolado como datos planos (idioma → título + secciones): el snapshot es lo
 * que la persona ve, no la plantilla con tokens, y así Identity no tiene que mirar a Content, que es
 * donde se redacta (`ModuleBoundariesTest`).
 *
 * ⚠️ El bloqueante de la revisión (§8.1) es aquí un MECANISMO: un texto con marcador de borrador
 * (`[PENDIENTE…]`) no se publica. Publicar es irreversible, y grabar un borrador en la cadena
 * probatoria lo sería también.
 */
final class LegalDocumentPublisher
{
    /**
     * Marcadores de borrador, comparados en minúsculas. ⚠️ Son TRES, uno por idioma del seeder
     * —`[PENDIENTE: …]`, `[PENDING: …]`, `[À COMPLÉTER : …]`— más el `[pendiente]` neutro que deja
     * `LegalIdentity::interpolate` sin datos fiscales. Hasta `#169` §10.4 solo se cazaba el castellano
     * y los otros dos idiomas se publicaban con su marcador dentro.
     */
    public const DRAFT_MARKERS = ['[pendiente', '[pending', '[à compléter', '[a completer'];

    /**
     * Palabras que delatan un borrador SIN marcador («Este texto es un borrador y será revisado…»).
     * No bloquean —un texto definitivo puede mencionarlas—: la acción de publicar las enseña como
     * AVISO en su confirmación (`mentionsDraftWords()`).
     */
    public const DRAFT_WORDS = ['borrador', 'draft', 'brouillon'];

    /**
     * @param  array<string, array{title?:mixed, body?:mixed}>  $texts  idioma → {title, body:[{h,p}]}, ya interpolado
     * @return Collection<int, LegalDocumentVersion> una fila por idioma con cuerpo, en la misma versión
     *
     * @throws InvalidArgumentException si ningún idioma trae cuerpo
     * @throws DraftCannotBePublishedException si algún idioma lleva marcador de borrador
     */
    public function publish(string $slug, array $texts, ?User $publishedBy = null): Collection
    {
        $clean = [];
        foreach ($texts as $locale => $text) {
            $body = is_array($text) && is_array($text['body'] ?? null) ? $text['body'] : [];
            $sections = LegalDocumentVersion::normaliseBody($body);
            if ($sections === []) {
                continue;
            }
            $clean[(string) $locale] = [
                'title' => trim((string) (is_array($text) ? ($text['title'] ?? '') : '')),
                'sections' => $sections,
            ];
        }

        if ($clean === []) {
            throw new InvalidArgumentException("No hay texto que publicar para «{$slug}»: el cuerpo está vacío en todos los idiomas.");
        }

        $drafts = array_keys(array_filter($clean, fn (array $text): bool => self::looksLikeDraft($text)));
        if ($drafts !== []) {
            throw DraftCannotBePublishedException::in($slug, array_values($drafts));
        }

        return DB::transaction(function () use ($slug, $clean, $publishedBy): Collection {
            // La unicidad (slug, locale, version) es el respaldo si dos publicaciones se cruzan: la
            // segunda falla en vez de crear dos «versión 3». Publicar es un acto raro de admin; no
            // merece un lock propio.
            $version = (LegalDocuments::latestVersionNumber($slug) ?? 0) + 1;
            $now = now();

            $rows = new Collection;
            foreach ($clean as $locale => $text) {
                $rows->push(LegalDocumentVersion::create([
                    'slug' => $slug,
                    'locale' => $locale,
                    'version' => $version,
                    'title' => $text['title'],
                    'body' => $text['sections'],
                    'body_hash' => LegalDocumentVersion::hashFor($text['title'], $text['sections']),
                    'published_by' => $publishedBy?->getKey(),
                    'published_at' => $now,
                ]));
            }

            AuditLogger::log('legal.version_published', $rows->first(), [
                'slug' => $slug,
                'version' => $version,
                'locales' => array_keys($clean),
                'hashes' => $rows->mapWithKeys(fn (LegalDocumentVersion $row): array => [$row->locale => $row->body_hash])->all(),
            ]);

            return $rows;
        });
    }

    /**
     * Idiomas cuyo texto menciona «borrador»/«draft»/«brouillon» — para AVISAR antes de publicar, no
     * para bloquear. Recibe la misma forma que `publish()` (idioma → {title, body:[{h,p}]}).
     *
     * @param  array<string, array{title?:mixed, body?:mixed}>  $texts
     * @return list<string>
     */
    public static function mentionsDraftWords(array $texts): array
    {
        $locales = [];
        foreach ($texts as $locale => $text) {
            $body = is_array($text) && is_array($text['body'] ?? null) ? $text['body'] : [];
            $haystack = self::haystack([
                'title' => (string) (is_array($text) ? ($text['title'] ?? '') : ''),
                'sections' => LegalDocumentVersion::normaliseBody($body),
            ]);
            if (preg_match('/\b('.implode('|', self::DRAFT_WORDS).')\b/iu', $haystack) === 1) {
                $locales[] = (string) $locale;
            }
        }

        return $locales;
    }

    /**
     * @param  array{title:string, sections:list<array{h:string,p:string}>}  $text
     */
    private static function looksLikeDraft(array $text): bool
    {
        $haystack = mb_strtolower(self::haystack($text));

        foreach (self::DRAFT_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{title:string, sections:list<array{h:string,p:string}>}  $text
     */
    private static function haystack(array $text): string
    {
        return $text['title'].' '.implode(' ', array_map(
            fn (array $section): string => $section['h'].' '.$section['p'],
            $text['sections'],
        ));
    }
}
