<?php

namespace App\Domain\Booking\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de tarifa por día (#59). v1: `normal` y `special` (festivo/finde/víspera).
 * Qué tarifa aplica a una fecha lo decide App\Domain\Booking\Services\RateResolver.
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
     * `firstOrFail()` (App\Domain\Booking\Services\RateResolver) y la web la lee para el precio "desde X €"
     * (TicketType::displayPriceCents). Borrarla rompería la resolución de precios en toda
     * la web → su borrado está prohibido y su `key` es inmutable.
     */
    public function isFallback(): bool
    {
        return $this->key === self::KEY_NORMAL;
    }

    /**
     * **La tarifa ESPECIAL que la web nombra**, o `null` si esta instalación no tiene ninguna.
     *
     * `DECISIONES #479`. Es la que da nombre a los días —hoy «Viernes, findes y festivos»— en la
     * nota que cada sección escribe UNA vez. Cuando hay varias manda la de menor `priority`, que es
     * el mismo criterio con el que `RateResolver` decide cuál se aplica: la sección no puede nombrar
     * una tarifa distinta de la que se cobra.
     *
     * ⚠️ **Memoizado por PETICIÓN a propósito.** La nota la pinta cada zona de la sección de tarifas
     * y también la banda de cumpleaños, así que sin esto serían tres o cuatro consultas idénticas
     * por página para leer una tabla de dos filas. `null` se cachea igual: una instalación sin
     * tarifa especial no puede pagar una consulta por cada sitio donde NO se pinta nada.
     */
    public static function firstSpecial(): ?self
    {
        if (! self::$specialResolved) {
            self::$special = self::query()->where('is_active', true)->where('is_special', true)
                ->orderBy('priority')->first();
            self::$specialResolved = true;
        }

        return self::$special;
    }

    /**
     * Olvida el memo de {@see firstSpecial()}.
     *
     * ⚠️ **Existe porque el memo es de PROCESO, no de petición, y en la suite el proceso dura toda
     * la clase.** Un caso que siembra una tarifa especial después de que otro haya preguntado se
     * quedaría con la respuesta del anterior — y con `RefreshDatabase` esa respuesta apunta a una
     * fila que ya no existe. Es la trampa que `#268` pagó con `GuestAgeMixReader` y `#465` con
     * `OperatingSchedule`: *un memo acierta hasta que alguien siembra en medio.*
     * ▶ Por eso lo llama el `setUp()` de `TestCase`, y no cada caso: una limpieza que hay que
     * recordar no es una limpieza.
     */
    public static function forgetSpecialMemo(): void
    {
        self::$special = null;
        self::$specialResolved = false;
    }

    /** @internal memo de {@see firstSpecial()} */
    private static ?self $special = null;

    /** @internal ¿se ha resuelto ya? Se distingue de `null`, que es una respuesta válida. */
    private static bool $specialResolved = false;

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
