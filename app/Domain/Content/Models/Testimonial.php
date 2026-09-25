<?php

namespace App\Domain\Content\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Una opinión del panel (`DECISIONES #490`): escrita en él (`origin = own`) o **copiada de la ficha de Google del
 * propio parque** (`origin = google`, `#771`), que sale con la marca de Google y el enlace al original.
 *
 * ⚠️ **No se confunde con una reseña que sirve Google**: las de Places no se persisten nunca (R2) y las del Perfil de
 * Empresa viven en su propio modelo; ésta es un DATO del parque, que decide cuáles se publican y dónde (`tags`).
 * ⚠️ Sus imágenes (`avatar`, `photos`) son rutas NUESTRAS del disco `uploads`: se descargan al importar, así que la
 * página no le pide nada a un tercero.
 */
class Testimonial extends Model
{
    use HasTranslations;

    /** Escrita en el panel. */
    public const ORIGIN_OWN = 'own';

    /** Copiada de la ficha de Google del parque (`#771`). */
    public const ORIGIN_GOOGLE = 'google';

    /** Disco de la foto del autor y de las fotos de la reseña: el hueco de la instalación, fuera del repo. */
    public const IMAGE_DISK = 'uploads';

    protected $guarded = [];

    protected $attributes = [
        'origin' => self::ORIGIN_OWN,
    ];

    protected $casts = [
        'text' => 'array',
        'photos' => 'array',
        'tags' => 'array',
        'rating' => 'integer',
        'published_at' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        /*
         * Las imágenes que dejan de usarse se borran del disco (como la foto de la ficha del producto): `FileUpload`
         * sube la nueva y no borra la vieja, y el importador reemplaza la foto de un autor que la cambió.
         */
        static::updating(function (self $opinion): void {
            $antes = array_filter([(string) $opinion->getOriginal('avatar'), ...self::lista($opinion->getOriginal('photos'))]);
            $ahora = array_filter([(string) $opinion->avatar, ...self::lista($opinion->photos)]);
            self::olvidar(array_diff($antes, $ahora), (int) $opinion->getKey());
        });

        static::deleted(function (self $opinion): void {
            self::olvidar(array_filter([(string) $opinion->avatar, ...self::lista($opinion->photos)]), (int) $opinion->getKey());
        });
    }

    /**
     * Borra del disco las imágenes que ya NADIE usa.
     *
     * ⚠️ El nombre de una imagen importada es el hash de su contenido, así que dos opiniones pueden compartir fichero:
     * se borra solo si ninguna otra fila lo nombra.
     *
     * @param  array<int, string>  $rutas
     */
    private static function olvidar(array $rutas, int $excepto): void
    {
        foreach ($rutas as $ruta) {
            // En PHP y no con un `LIKE` sobre el JSON: la columna guarda las barras ESCAPADAS (`resenas\/x.png`) y el
            // `\` es el escape del `LIKE` en MySQL y no en SQLite. La tabla es corta.
            $enUso = self::query()->whereKeyNot($excepto)
                ->where(fn (Builder $q) => $q->where('avatar', $ruta)->orWhereNotNull('photos'))
                ->get(['id', 'avatar', 'photos'])
                ->contains(fn (self $otra): bool => $otra->avatar === $ruta || in_array($ruta, self::lista($otra->photos), true));
            if (! $enUso) {
                Storage::disk(self::IMAGE_DISK)->delete($ruta);
            }
        }
    }

    /**
     * Las publicadas en una página: activas y con esa etiqueta, en el orden del panel.
     *
     * ⚠️ La etiqueta se busca en PHP y no con `whereJsonContains`: la suite corre en SQLite y producción en MySQL, y la
     * lista es corta (decenas), así que no merece dos dialectos.
     *
     * @param  Builder<Testimonial>  $query
     * @return Builder<Testimonial>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position')->orderByDesc('published_at')->orderBy('id');
    }

    /** ¿Sale en esta página? */
    public function hasTag(string $etiqueta): bool
    {
        return in_array($etiqueta, self::lista($this->tags), true);
    }

    /** La foto del autor como URL pública, o `null`. */
    public function avatarUrl(): ?string
    {
        $ruta = trim((string) ($this->avatar ?? ''));

        return $ruta === '' ? null : asset('uploads/'.$ruta);
    }

    /**
     * Las fotos que adjuntó, como URL públicas nuestras.
     *
     * @return list<string>
     */
    public function photoUrls(): array
    {
        return array_map(fn (string $ruta): string => asset('uploads/'.$ruta), self::lista($this->photos));
    }

    /**
     * Una lista de cadenas no vacías a partir de lo que haya en una columna JSON.
     *
     * @return list<string>
     */
    private static function lista(mixed $valor): array
    {
        if (is_string($valor)) {
            $valor = json_decode($valor, true);
        }

        return array_values(array_filter(array_map(fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : '', is_array($valor) ? $valor : []), fn (string $v): bool => $v !== ''));
    }
}
