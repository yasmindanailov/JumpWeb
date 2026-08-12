<?php

namespace App\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de tarifa por día (#59). v1: `normal` y `special` (festivo/finde/víspera).
 * Qué tarifa aplica a una fecha lo decide App\Support\RateResolver.
 */
class RateType extends Model
{
    use HasTranslations;

    public const KEY_NORMAL = 'normal';

    public const KEY_SPECIAL = 'special';

    protected $guarded = [];

    protected $casts = [
        'label' => 'array',
        'weekdays' => 'array',
        'is_special' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<Price, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    /**
     * Fechas especiales (festivos/vísperas) que apuntan a esta tarifa. La FK es
     * `nullOnDelete`: borrar la tarifa dejaría esas fechas sin tarifa asignada (caerían
     * a la regla por día de la semana). Por eso el borrado se bloquea si existe alguna.
     *
     * @return HasMany<SpecialDate, $this>
     */
    public function specialDates(): HasMany
    {
        return $this->hasMany(SpecialDate::class);
    }

    /**
     * ¿Es la tarifa base (`normal`)? RateResolver la usa como último recurso con
     * `firstOrFail()` (App\Support\RateResolver) y la web la lee para el precio "desde X €"
     * (TicketType::displayPriceCents). Borrarla rompería la resolución de precios en toda
     * la web → su borrado está prohibido y su `key` es inmutable.
     */
    public function isFallback(): bool
    {
        return $this->key === self::KEY_NORMAL;
    }

    /**
     * Motivo por el que NO se puede borrar esta tarifa, o null si se puede borrar sin
     * riesgo. Autoridad única reutilizada por la UI (visibilidad de la acción) y por el
     * handler de borrado (re-verificación con datos frescos), en ese orden de gravedad:
     *  - `fallback_normal`: es la tarifa base (RateResolver depende de que exista).
     *  - `has_prices`: tiene precios (la FK `prices.rate_type_id` es `cascadeOnDelete` →
     *    borrarla borraría silenciosamente esos precios y dejaría productos sin tarifa).
     *  - `referenced_by_special_dates`: hay fechas especiales que la referencian.
     */
    public function deleteBlockedReason(): ?string
    {
        if ($this->isFallback()) {
            return 'fallback_normal';
        }

        if ($this->prices()->exists()) {
            return 'has_prices';
        }

        if ($this->specialDates()->exists()) {
            return 'referenced_by_special_dates';
        }

        return null;
    }

    /** ¿Se puede borrar físicamente esta tarifa sin riesgo? Ver deleteBlockedReason(). */
    public function canBeDeleted(): bool
    {
        return $this->deleteBlockedReason() === null;
    }
}
