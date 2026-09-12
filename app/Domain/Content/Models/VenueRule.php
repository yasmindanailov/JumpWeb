<?php

namespace App\Domain\Content\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class VenueRule extends Model
{
    use HasTranslations;

    /**
     * **LOS TRES MOMENTOS** (`DECISIONES #533`), y son el ÚNICO orden que el visitante puede usar:
     * lo que decide **si entras** (en casa y en la puerta) y lo que pasa **dentro**.
     *
     * ⚠️ **La lista la gobierna el PRODUCTO, no el esquema**: la columna es una cadena para que
     * añadir un momento sea tocar esta constante y su rótulo, no un `ALTER` de la tabla.
     * ⚠️⚠️ **El ORDEN de este array ES el orden de la página**, y no es alfabético ni casual: una
     * norma de la puerta leída después de una de dentro llega tarde. Si alguien lo reordena, la
     * página cambia de sentido sin que falle nada — por eso lo vigila una guarda.
     */
    public const MOMENTS = ['before', 'gate', 'inside'];

    protected $table = 'park_rules';

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        // El PORQUÉ de la norma, traducible como su nombre y su descripción: lo lee el cliente.
        'reason' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * El momento al que pertenece, **solo si es uno de los declarados**.
     *
     * ⚠️⚠️ Devuelve `null` tanto si la norma no tiene momento como si tiene uno que el producto ya
     * no declara —una fila vieja, o un valor metido por la puerta de atrás—, y las dos cosas
     * significan lo mismo para la página: **se publica sin agrupar**. Lo que no puede pasar es que
     * una norma desaparezca por no encajar en la lista.
     */
    public function momentOrNull(): ?string
    {
        return in_array($this->moment, self::MOMENTS, true) ? $this->moment : null;
    }
}
