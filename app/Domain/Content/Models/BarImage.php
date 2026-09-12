<?php

namespace App\Domain\Content\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * **Una imagen del bar** (`DECISIONES #536`, carril de diseño Fase 3 · T3b, artboard `Bar PJP`).
 *
 * Dos tipos, un solo sitio en el panel (`[DECIDIDO owner]`):
 *   · `KIND_MENU`  — las caras de la CARTA. Varias, ordenadas.
 *   · `KIND_VENUE` — la FOTO del local (las mesas con el parque detrás).
 *
 * ❗❗ **La carta se publica como IMAGEN**, así que el `alt` no es un extra: es **lo único** que
 * encuentra un lector de pantalla, un buscador o alguien con las imágenes desactivadas. El
 * formulario lo exige; el esquema no, para que una fila sembrada no reviente.
 *
 * Molde de subida y limpieza idéntico a `Offer` (`#270`): disco `uploads` → `public/uploads/`,
 * servido nativo sin symlink, y el fichero viejo se borra al reemplazar o al borrar la fila —
 * `FileUpload` no lo hace solo y los huérfanos se acumulan en silencio.
 */
class BarImage extends Model
{
    use HasTranslations;

    /** Disco de subida (ver `config/filesystems.php`), el mismo que las ofertas. */
    public const IMAGE_DISK = 'uploads';

    /** Una cara de la carta. */
    public const KIND_MENU = 'menu';

    /** La foto del local. */
    public const KIND_VENUE = 'venue';

    /** Los tipos que el producto conoce. La lista vive aquí, que es donde se lee. */
    public const KINDS = [self::KIND_MENU, self::KIND_VENUE];

    protected $guarded = [];

    protected $casts = [
        'alt' => 'array',
        'is_active' => 'boolean',
        'position' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected static function booted(): void
    {
        /*
         * ❗ **Las dimensiones se MIDEN, no se piden.** Van al `<img>` para que el navegador reserve
         * el hueco antes de descargar la imagen: sin ellas la página salta al cargar la carta, que
         * es la imagen más grande del sitio. Nadie debería teclearlas a mano.
         * ⚠️ Se mide al CAMBIAR la imagen (alta o reemplazo), no en cada guardado: leer un fichero
         * del disco en cada `save()` para recalcular lo mismo es trabajo por nada.
         * ⚠️ Si el fichero no se puede leer, quedan en `null` y el `<img>` sale sin dimensiones: peor
         * maquetación, nunca un guardado que falla.
         */
        static::saving(function (BarImage $img): void {
            if (! $img->isDirty('image')) {
                return;
            }

            [$img->width, $img->height] = self::measure((string) $img->image);
        });

        // Limpieza de huérfanos, como `Offer`: al REEMPLAZAR la imagen se borra el fichero viejo y
        // al BORRAR la fila se borra el suyo. `delete()` sobre una ruta que no existe es un no-op.
        static::updating(function (BarImage $img): void {
            if ($img->isDirty('image') && ($old = $img->getOriginal('image'))) {
                Storage::disk(self::IMAGE_DISK)->delete($old);
            }
        });

        static::deleted(function (BarImage $img): void {
            if ($img->image) {
                Storage::disk(self::IMAGE_DISK)->delete($img->image);
            }
        });
    }

    /** URL pública de la imagen (o `null`). Servida nativa desde `public/uploads` (sin symlink). */
    public function imageUrl(): ?string
    {
        return $this->image ? asset('uploads/'.ltrim((string) $this->image, '/')) : null;
    }

    /**
     * Ancho y alto del fichero, o `[null, null]` si no se puede leer.
     *
     * ⚠️ **Va contra el DISCO y no contra una ruta local a pelo**: en producción `uploads` puede no
     * ser el sistema de ficheros de la app, y `getimagesize()` sobre una ruta inventada devuelve
     * `false` en silencio — o sea, dimensiones vacías sin que nadie se entere.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private static function measure(string $path): array
    {
        if ($path === '') {
            return [null, null];
        }

        try {
            $disk = Storage::disk(self::IMAGE_DISK);
            if (! $disk->exists($path)) {
                return [null, null];
            }

            $size = @getimagesizefromstring((string) $disk->get($path));
        } catch (\Throwable) {
            return [null, null];
        }

        return is_array($size) && ($size[0] ?? 0) > 0 && ($size[1] ?? 0) > 0
            ? [(int) $size[0], (int) $size[1]]
            : [null, null];
    }

    /**
     * ⚠️ **Un tipo que el producto ya no declara NO se publica.** Es la misma regla que
     * `VenueRule::moment` (`#533`): si alguien mete `kind = 'promo'` por SQL, esto devuelve `null` y
     * la página no lo pinta, en vez de sacar una imagen en un sitio que nadie ha diseñado.
     */
    public function kindOrNull(): ?string
    {
        return in_array($this->kind, self::KINDS, true) ? $this->kind : null;
    }

    /** @param  Builder<BarImage>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<BarImage>  $query */
    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    /** @param  Builder<BarImage>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }
}
