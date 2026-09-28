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

    /**
     * **LOS NIVELES de una norma** (`DECISIONES #842`, los de `RuleCard` del diseño): el color de su tarjeta y la palabra que
     * lo dice —Obligatorio, Seguridad, Prohibido, Bueno saber—. Los elige el panel; la lista, el producto.
     */
    public const LEVELS = ['must', 'safety', 'forbidden', 'info'];

    /**
     * **LOS ICONOS que puede llevar una norma** (`#842`): nombres de Lucide, el juego del sistema de diseño
     * (`Lucide::svg()`). Una lista CERRADA y no un campo libre: un nombre mal escrito pintaría un hueco sin avisar. Son los
     * de las normas y los cuidados del diseño (Normas, Visítanos); una norma nueva que necesite otro, se añade aquí.
     */
    public const ICONS = [
        'footprints', 'shirt', 'utensils-crossed', 'person-standing', 'user', 'users', 'ban', 'rotate-ccw', 'scan-eye',
        'megaphone', 'ruler', 'heart-pulse', 'shield-check', 'hand-heart', 'eye', 'baby', 'user-check', 'smartphone',
        'qr-code', 'id-card', 'clock', 'cookie', 'cake', 'briefcase-medical', 'info',
    ];

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

    /** El icono, solo si es uno de la lista (`ICONS`); si no, `null`: «sin elegir», y quien pinta pone el suyo. */
    public function iconOrNull(): ?string
    {
        return in_array($this->icon, self::ICONS, true) ? $this->icon : null;
    }

    /** El nivel, solo si es uno de la lista (`LEVELS`); si no, `null`: «sin elegir». */
    public function levelOrNull(): ?string
    {
        return in_array($this->level, self::LEVELS, true) ? $this->level : null;
    }
}
