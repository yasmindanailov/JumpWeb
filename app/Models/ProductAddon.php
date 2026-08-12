<?php

namespace App\Models;

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
