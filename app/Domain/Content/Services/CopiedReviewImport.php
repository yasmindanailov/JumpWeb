<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\Testimonial;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * **Importa las reseñas copiadas de la ficha de Google** (`DECISIONES #771`): el JSON que escribe
 * `scripts/resenas-google.mjs` → opiniones del panel con `origin = google`, sus imágenes en casa y su fecha.
 *
 * ▶ **Reimportar es seguro**: la clave es el id de la reseña en Google (`source_ref`). Una que ya estaba actualiza lo
 * que viene de Google —texto, estrellas, autor, imágenes, respuesta— y **conserva lo que decidió el parque**: si está
 * publicada, en qué páginas y en qué orden. Una nueva entra APAGADA y sin páginas: publicar es una elección.
 * ▶ Una reseña sin texto (solo estrellas) no se importa: no hay nada que enseñar en una tarjeta.
 * ⚠️ Google dice la fecha en relativo («Hace 2 meses»): se guarda el día que eso significa desde el momento de la copia,
 * y la página vuelve a escribirla en relativo, así envejece bien. En una reseña que ya estaba se conserva la primera,
 * que es la más precisa.
 */
final class CopiedReviewImport
{
    public function __construct(private readonly CopiedReviewImages $imagenes, private readonly CopiedRating $nota) {}

    /**
     * ▶ **La NOTA de la ficha** (`rating: {value, count}` de la copia) se guarda también ({@see CopiedRating}): es la que
     * enseñan las páginas mientras no haya Perfil de Empresa.
     * ▶ **La ELECCIÓN puede venir en el fichero**: una reseña con `tags`, `position` o `active` nace publicada así. Es
     * como se lleva a otro servidor la misma selección que se hizo en local; en una que ya estaba no se toca.
     *
     * @param  array<string, mixed>  $copia  el JSON de la herramienta de copia
     * @return array{nuevas: int, actualizadas: int, sin_texto: int, imagenes: int, nota: bool}
     */
    public function import(array $copia): array
    {
        $resenas = $copia['reviews'] ?? null;
        if (! is_array($resenas)) {
            throw new InvalidArgumentException('El fichero no trae «reviews»: no es una copia de la herramienta.');
        }

        $momento = isset($copia['copied_at']) ? Carbon::parse((string) $copia['copied_at']) : now();
        $ficha = self::url($copia['place_url'] ?? $copia['source'] ?? null);
        $cuenta = ['nuevas' => 0, 'actualizadas' => 0, 'sin_texto' => 0, 'imagenes' => 0, 'nota' => false];

        $media = (float) str_replace(',', '.', (string) ($copia['rating']['value'] ?? 0));
        $total = (int) ($copia['rating']['count'] ?? $copia['total'] ?? 0);
        if ($media >= 1 && $media <= 5 && $total >= 1) {
            $this->nota->put($media, $total, $ficha, $momento);
            $cuenta['nota'] = true;
        }

        foreach ($resenas as $r) {
            $id = trim((string) ($r['id'] ?? ''));
            $texto = trim((string) ($r['text'] ?? ''));
            if ($id === '' || $texto === '') {
                $cuenta['sin_texto']++;

                continue;
            }

            $avatar = $this->imagenes->fetch($r['avatar_url'] ?? null);
            $fotos = array_values(array_filter(array_map(fn (mixed $u): ?string => is_string($u) ? $this->imagenes->fetch($u) : null, (array) ($r['photos'] ?? []))));
            $cuenta['imagenes'] += count($fotos) + ($avatar !== null ? 1 : 0);

            $datos = [
                'origin' => Testimonial::ORIGIN_GOOGLE,
                'source_url' => $ficha,
                'author' => mb_substr(trim((string) ($r['author'] ?? '')), 0, 120),
                'author_meta' => ($meta = trim((string) ($r['author_meta'] ?? ''))) === '' ? null : mb_substr($meta, 0, 160),
                'avatar' => $avatar,
                'photos' => $fotos === [] ? null : $fotos,
                'rating' => isset($r['rating']) && (int) $r['rating'] >= 1 && (int) $r['rating'] <= 5 ? (int) $r['rating'] : null,
                'text' => ['es' => $texto],
                'reply' => ($respuesta = trim((string) ($r['reply'] ?? ''))) === '' ? null : $respuesta,
            ];

            DB::transaction(function () use ($id, $datos, $r, $momento, &$cuenta): void {
                $opinion = Testimonial::query()->where('source_ref', $id)->lockForUpdate()->first();
                if ($opinion !== null) {
                    $opinion->update($datos);
                    $cuenta['actualizadas']++;

                    return;
                }

                $etiquetas = array_values(array_unique(array_filter(array_map(
                    fn (mixed $t): string => is_scalar($t) ? mb_strtolower(trim((string) $t)) : '',
                    (array) ($r['tags'] ?? []),
                ), fn (string $t): bool => $t !== '')));
                Testimonial::create($datos + [
                    'source_ref' => $id,
                    'published_at' => self::fecha($r['when'] ?? null, $momento)?->toDateString(),
                    'is_active' => (bool) ($r['active'] ?? false),
                    'tags' => $etiquetas === [] ? null : $etiquetas,
                    'position' => (int) ($r['position'] ?? 0),
                ]);
                $cuenta['nuevas']++;
            });
        }

        return $cuenta;
    }

    /**
     * El día que significa una fecha relativa de Google en el momento de la copia, o `null` si no se entiende.
     *
     * Entiende «Hace un día», «Hace 3 semanas», «Hace un mes», «Hace 2 años», «Editado hace…» y los mismos en inglés.
     */
    public static function fecha(?string $cuando, Carbon $momento): ?Carbon
    {
        $texto = mb_strtolower(trim((string) $cuando));
        if ($texto === '') {
            return null;
        }

        $cifra = preg_match('/(\d+)/', $texto, $m) === 1 ? (int) $m[1] : 1;
        $unidades = [
            'minuto' => 'subMinutes', 'minute' => 'subMinutes', 'hora' => 'subHours', 'hour' => 'subHours',
            'día' => 'subDays', 'dia' => 'subDays', 'day' => 'subDays', 'semana' => 'subWeeks', 'week' => 'subWeeks',
            'mes' => 'subMonths', 'month' => 'subMonths', 'año' => 'subYears', 'ano' => 'subYears', 'year' => 'subYears',
        ];
        foreach ($unidades as $palabra => $metodo) {
            if (str_contains($texto, $palabra)) {
                return $momento->copy()->startOfDay()->{$metodo}($cifra);
            }
        }

        return null;
    }

    /** Una URL de Google en `https`, o `null` (`SEC-07`: el dato de fuera se sanea donde nace). */
    private static function url(mixed $url): ?string
    {
        $url = is_string($url) ? trim($url) : '';
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_starts_with($url, 'https://') && ($host === 'www.google.com' || $host === 'maps.google.com' || $host === 'maps.app.goo.gl')
            ? mb_substr($url, 0, 500)
            : null;
    }
}
