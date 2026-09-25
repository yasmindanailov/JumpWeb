<?php

namespace App\Domain\Content\Models;

use App\Domain\Booking\Models\Zone;
use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attraction extends Model
{
    use HasTranslations;

    /**
     * Disco del VÍDEO de la atracción: `public/uploads`, el hueco de la instalación (gitignorado y fuera del
     * `rsync --delete`), el mismo que la foto de la ficha del producto (`TicketType::IMAGE_DISK`).
     */
    public const VIDEO_DISK = 'uploads';

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'age' => 'array',
        'badge' => 'array',
        'is_active' => 'boolean',
        'is_special' => 'boolean',
    ];

    /**
     * La zona a la que pertenece el juego.
     *
     * ⚠️ El genérico no es adorno: sin él, quien lea `$juego->zone?->slug` está leyendo un `Model` a
     * secas y Larastan lo canta como `property.notFound` —lo cazó al servir `/attractions` (`#674`)—.
     * La salida es que el código DIGA de qué es la relación, no una entrada más en la línea base, que
     * solo encoge.
     *
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * **URL pública de la foto de la atracción, o `null` si no tiene** (F5 · T2b, `#657`).
     *
     * ⚠️⚠️ **La regla estaba escrita en las VISTAS, y las vistas se van** (`paquete-de-instancia.md`
     * §4.7): `asset($ride->image)` aparecía en `/atracciones` y dos veces en el mosaico de la portada,
     * sin un sitio donde consultarla. El día que una landing es de otro repo, quien la escriba tiene
     * que adivinar cómo se resuelve esa ruta — y el propio producto ofrece la respuesta equivocada al
     * lado: `TicketType::imageUrl()` antepone `uploads/` porque **aquello sí es una subida**.
     *
     * Aquí no: la foto de una atracción es una **ruta relativa a `public/`** escrita en el panel
     * (`images/attractions/jump_saltos_libres.webp`), igual que la de la zona — misma herencia, misma
     * resolución, y por eso este método es letra por letra {@see Zone::imageUrl()}.
     *
     * ⚠️ Devolver `null` con la ruta vacía es parte de la regla, no un detalle: la vista pinta la foto
     * solo si hay, y «sin foto no se reserva hueco» es decisión del canvas. Un `asset('')` daría la
     * raíz del sitio y pintaría un cuadrado roto.
     */
    public function imageUrl(): ?string
    {
        $ruta = trim((string) ($this->image ?? ''));

        return $ruta === '' ? null : asset($ruta);
    }

    /**
     * **URL pública del VÍDEO de la atracción, o `null` si no tiene** (el owner, 25-09: el «play» de las atracciones).
     *
     * ⚠️ Al revés que la foto, el vídeo SÍ es una subida del panel: vive en el disco `uploads` y se resuelve como la foto
     * de la ficha del producto (`asset('uploads/'.…)`, que el servidor web sirve de forma nativa sin el symlink de
     * `public/storage`). Con él, la web pone el triángulo de «play»; sin él, la foto va sin play (`ClipTile.jsx`).
     */
    public function videoUrl(): ?string
    {
        $ruta = trim((string) ($this->video ?? ''));

        return $ruta === '' ? null : asset('uploads/'.$ruta);
    }

    protected static function booted(): void
    {
        /*
         * La limpieza de huérfanos del vídeo, la misma que la foto de la ficha (`TicketType::booted()`): `FileUpload`
         * sube el nuevo y NO borra el viejo, y un vídeo pesa lo que cien fotos.
         */
        static::updating(function (self $juego): void {
            if ($juego->isDirty('video') && ($anterior = $juego->getOriginal('video'))) {
                Storage::disk(self::VIDEO_DISK)->delete($anterior);
            }
        });

        static::deleted(function (self $juego): void {
            if ($juego->video) {
                Storage::disk(self::VIDEO_DISK)->delete($juego->video);
            }
        });
    }

    /*
     * 📜 **AQUÍ VIVÍAN `complementIsPurchasable()` Y `complementPriceCents()`** —el complemento
     * que una atracción podía vender desde su ficha de la landing (`#228`)— y se retiran en
     * `#668` (F5 · T3). `#632`·P3 lo decidió midiendo: las 23 atracciones son PRESENTACIÓN
     * (nombre, foto, chapa, edad en texto), **0 de 23** tenían complemento vinculado y las
     * restricciones que de verdad importan viven en Normas.
     * ⚠️ La columna `ticket_type_id` sigue en la tabla: las migraciones que borran van juntas
     * en la tanda que sube el MAYOR de la versión (T4/T5), no sueltas.
     */
}
