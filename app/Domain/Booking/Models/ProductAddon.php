<?php

namespace App\Domain\Booking\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivote `product_addons` con casts tipados (feature de complementos avanzados).
 *
 * Lleva la configuración de CÓMO se ofrece un complemento dentro de un producto concreto
 * (config por enganche, no global). Modelarlo como Pivot propio (`->using(ProductAddon::class)`)
 * permite leer `$addon->pivot->is_included` como `bool` real, `included_quantity` como `int`, etc.,
 * en vez de los `0/1` crudos que devuelve `withPivot` sin casts.
 *
 * La tabla tiene `id` autoincremental (no es un pivote compuesto puro) y NO tiene timestamps.
 */
class ProductAddon extends Pivot
{
    protected $table = 'product_addons';

    public $incrementing = true;

    public $timestamps = false;

    /** Modos de cantidad de un complemento incluido. */
    public const MODE_FIXED = 'fixed';       // cantidad propia + extras opcionales (p. ej. la tarta)

    public const MODE_PER_GUEST = 'per_guest'; // una unidad por invitado del pack (p. ej. el menú)

    protected $casts = [
        'position' => 'integer',
        'is_included' => 'boolean',
        'included_quantity' => 'integer',
        'is_mandatory' => 'boolean',
        'allow_extra' => 'boolean',
        'max_qty' => 'integer',
        'requires_addon_id' => 'integer',
    ];

    /** ¿La cantidad sigue al nº de invitados del pack (no la toca el cliente)? */
    public function isPerGuest(): bool
    {
        return $this->quantity_mode === self::MODE_PER_GUEST;
    }

    /**
     * La HORA EXTRA (`specs/hora-extra.md` §4.4·5 + §7·D2): un complemento que OCUPA no puede
     * engancharse con `per_guest` (impondría la hora extra a TODO el grupo, lo contrario del
     * encargo) ni como obligatorio (la auto-inyección convertiría «no cabe la hora extra» en «no se
     * puede vender el padre a esa hora», `AFORO-02` por otra puerta), ni colgar de un PACK (allí
     * «todos se quedan» es que la fiesta DURA MÁS — otro mecanismo — y el ocupante sería invisible
     * para `max_guests_per_slot`).
     *
     * Corre en `saving` del PIVOTE, así que cubre el alta y la edición del enganche por Eloquent;
     * su límite es el de siempre (`#299`: los eventos no ven `Query\Builder::update()`) y el
     * cinturón está en `AddonResolver::resolve()`, que re-valida las tres reglas al vender.
     */
    protected static function booted(): void
    {
        static::saving(function (self $pivot): void {
            $addon = TicketType::query()->find($pivot->addon_id);
            if ($addon === null || ! $addon->occupiesAfterParent()) {
                return;
            }

            if ($pivot->isPerGuest() || $pivot->is_mandatory) {
                throw new \InvalidArgumentException(
                    'Un complemento que OCUPA (hora extra) no puede ser por-invitado ni obligatorio: '
                    .'la cantidad son las entradas que SE QUEDAN y la elige el cliente '
                    .'(`specs/hora-extra.md` §4.4·5).'
                );
            }

            $parent = TicketType::query()->find($pivot->product_id);
            if ($parent !== null && $parent->isPack()) {
                throw new \InvalidArgumentException(
                    'Un complemento que OCUPA (hora extra) no puede colgar de un PACK: en un pack '
                    .'«quedarse más» es que la fiesta dura más — otro mecanismo (`specs/hora-extra.md` §7·D2).'
                );
            }
        });
    }

    /**
     * Id del complemento REQUERIDO por este enganche (dependencia «requiere»), o null si es
     * independiente. El dependiente solo es seleccionable/vendible cuando el requerido también lo
     * está (lo aplica `AddonResolver` a punto fijo, en oferta y en cobro). `0`/vacío → sin requisito.
     */
    public function requiresAddonId(): ?int
    {
        $id = (int) ($this->requires_addon_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /** Clave de grupo de elección excluyente (o null si el complemento es independiente). */
    public function choiceGroup(): ?string
    {
        $group = is_string($this->choice_group) ? trim($this->choice_group) : '';

        return $group === '' ? null : $group;
    }
}
