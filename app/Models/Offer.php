<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Oferta promocional INFORMATIVA (#270, `docs/PLAN-OFERTAS-WIDGET.md`). Título (i18n) + imagen,
 * gestionadas desde el panel (CMS). Gobiernan el widget flotante «caja de regalo» de la landing:
 * solo se muestra si hay ofertas activas (`scopeActive`). Sin lógica de dinero.
 *
 * La imagen se sube (FileUpload) al disco `uploads` (public/uploads/ofertas, servido nativo sin
 * symlink) y se sirve con `asset('uploads/'.$image)`. Al reemplazar/borrar la oferta se borra el
 * fichero antiguo (limpieza de huérfanos: FileUpload no lo hace por sí solo).
 */
class Offer extends Model
{
    use HasTranslations;

    /** Disco de subida de imágenes (ver `config/filesystems.php` + `PLAN-OFERTAS-WIDGET.md` §4). */
    public const IMAGE_DISK = 'uploads';

    protected $guarded = [];

    protected $casts = [
        'title' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Limpieza de huérfanos: al REEMPLAZAR la imagen borra el fichero viejo; al BORRAR la oferta
        // borra su fichero. `delete()` sobre una ruta inexistente es un no-op seguro.
        static::updating(function (Offer $offer): void {
            if ($offer->isDirty('image') && ($old = $offer->getOriginal('image'))) {
                Storage::disk(self::IMAGE_DISK)->delete($old);
            }
        });

        static::deleted(function (Offer $offer): void {
            if ($offer->image) {
                Storage::disk(self::IMAGE_DISK)->delete($offer->image);
            }
        });
    }

    /** URL pública de la imagen (o `null`). Servida nativa desde public/uploads (sin symlink). */
    public function imageUrl(): ?string
    {
        return $this->image ? asset('uploads/'.ltrim((string) $this->image, '/')) : null;
    }

    /** @param  Builder<Offer>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Offer>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position');
    }
}
