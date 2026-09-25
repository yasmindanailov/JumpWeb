<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Content\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * **`GET /api/v1/reviews` — LAS OPINIONES PUBLICADAS, cada una con sus páginas** (`DECISIONES #771`).
 *
 * El parque elige en el panel qué opiniones salen y en qué páginas (`tags`: «kids», «jump», «cumpleanos»…); quien pinta
 * una página toma las de su etiqueta, en el orden que viene (el del panel). Cada una dice de dónde es (`origin`):
 * `google` —copiada de la ficha del parque: va con la marca de Google y su enlace (`url`)— u `own` —escrita en el panel,
 * sin marca ni enlace—.
 *
 * ⚠️ Las imágenes (`avatar_url`, `photos`) son NUESTRAS: se descargaron al importar, así que pintarlas no le pide nada
 * a un tercero ni necesita consentimiento. Sin foto del autor, la clave falta y se pinta su inicial.
 * ⚠️ `date` es un DÍA (`2026-07-12`): quien pinta escribe «hace 2 meses» en su idioma, y así la frase envejece bien.
 * ⚠️ El texto es el de la reseña, ENTERO: no se recorta en el servidor.
 */
class ReviewsFactsResource extends JsonResource
{
    public static $wrap = null;

    /** @param Collection<int, Testimonial> $opiniones */
    public function __construct(private readonly Collection $opiniones)
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        return [
            'lang' => app()->getLocale(),
            'reviews' => $this->opiniones
                ->filter(fn (Testimonial $o): bool => trim((string) $o->tr('text')) !== '' && trim((string) $o->author) !== '')
                ->map(fn (Testimonial $o): array => array_filter([
                    'id' => $o->id,
                    'origin' => (string) $o->origin,
                    'author' => trim((string) $o->author),
                    'author_meta' => $o->author_meta,
                    'avatar_url' => $o->avatarUrl(),
                    'rating' => $o->rating,
                    'date' => $o->published_at?->toDateString(),
                    'text' => trim((string) $o->tr('text')),
                    'photos' => $o->photoUrls(),
                    'url' => $o->origin === Testimonial::ORIGIN_GOOGLE ? $o->source_url : null,
                    'reply' => $o->reply,
                    'tags' => array_values((array) ($o->tags ?? [])),
                ], fn ($valor, string $clave): bool => in_array($clave, ['photos', 'tags'], true) || ($valor !== null && $valor !== ''), ARRAY_FILTER_USE_BOTH))
                ->values()
                ->all(),
        ];
    }
}
