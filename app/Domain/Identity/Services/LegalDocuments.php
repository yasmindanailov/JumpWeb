<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\LegalDocumentVersion;
use Illuminate\Database\Eloquent\Collection;

/**
 * Fase 6 · waiver — lectura de las versiones publicadas (`specs/waiver-probatorio.md` §4.2): «cuál
 * es la versión vigente» como DATO, en vez de la constante `Consent::CURRENT_VERSION`.
 */
final class LegalDocuments
{
    public static function latestVersionNumber(string $slug): ?int
    {
        $max = LegalDocumentVersion::query()->where('slug', $slug)->max('version');

        return $max === null ? null : (int) $max;
    }

    /**
     * La versión VIGENTE en el idioma pedido. Si ese idioma no se publicó en ella, cae al de respaldo
     * y después al castellano: el snapshot que se firma es el que se ENSEÑA, así que el respaldo
     * tiene que ser el mismo que aplica la web al pintar el texto (`HasTranslations::tr`).
     */
    public static function current(string $slug, ?string $locale = null): ?LegalDocumentVersion
    {
        $version = self::latestVersionNumber($slug);
        if ($version === null) {
            return null;
        }

        $rows = self::versionsOf($slug, $version)->keyBy('locale');
        $locale ??= app()->getLocale();

        return $rows[$locale]
            ?? $rows[(string) config('app.fallback_locale')]
            ?? $rows['es']
            ?? $rows->first();
    }

    /**
     * @return Collection<int, LegalDocumentVersion>
     */
    public static function versionsOf(string $slug, int $version): Collection
    {
        return LegalDocumentVersion::query()
            ->where('slug', $slug)
            ->where('version', $version)
            ->orderBy('locale')
            ->get();
    }
}
