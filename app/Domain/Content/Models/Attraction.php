<?php

namespace App\Domain\Content\Models;

use App\Domain\Booking\Models\Zone;
use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attraction extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'age' => 'array',
        'badge' => 'array',
        'is_active' => 'boolean',
        'is_special' => 'boolean',
    ];

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
